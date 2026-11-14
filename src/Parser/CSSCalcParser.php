<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathValue;

/**
 * Recursive/shunting-yard parser for CSS `calc()` expressions.
 */
class CSSCalcParser
{
	/** @var array<string, array{precedence: int, associativity: string}> */
	private const array OPERATORS = [
		'+' => ['precedence' => 1, 'associativity' => 'left'],
		'-' => ['precedence' => 1, 'associativity' => 'left'],
		'*' => ['precedence' => 2, 'associativity' => 'left'],
		'/' => ['precedence' => 2, 'associativity' => 'left'],
		'u+' => ['precedence' => 3, 'associativity' => 'right'],
		'u-' => ['precedence' => 3, 'associativity' => 'right'],
	];

	/**
	 * Parses a CSS `calc(...)` expression into a CSSMathValue tree.
	 *
	 * @throws InvalidArgumentException When the expression is malformed, dimensionally incompatible, or exceeds maxMathDepth
	 */
	public static function parse(string $calcString, ?CSSParserOptions $options = null, int $depth = 0): CSSNumericValue
	{
		$options ??= new CSSParserOptions();
		if ($options->maxMathDepth !== null && $depth > $options->maxMathDepth) {
			throw new InvalidArgumentException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $options->maxMathDepth));
		}

		$calcString = trim($calcString);
		if (!preg_match('/^calc\s*\(/i', $calcString)) {
			throw new InvalidArgumentException('Expected a calc() expression.');
		}
		$open = strpos($calcString, '(');
		$close = $open === false ? null : CSSSyntaxScanner::findMatchingDelimiter($calcString, $open);
		if ($close === null || ($close < strlen($calcString) - 1 && $calcString[$close + 1] === ')')) {
			throw new InvalidArgumentException('Mismatched parentheses.');
		}
		if ($close !== strlen($calcString) - 1) {
			throw new InvalidArgumentException('Invalid expression.');
		}

		$expression = substr($calcString, $open + 1, $close - $open - 1);
		$tokens = self::tokenize($expression);

		$outputQueue = [];
		$operatorStack = [];

		foreach ($tokens as $token) {
			if (self::isOperand($token)) {
				$outputQueue[] = $token;
			} elseif (isset(self::OPERATORS[$token])) {
				$op1 = $token;
				while ($operatorStack !== []) {
					$op2 = end($operatorStack);
					if (
						isset(self::OPERATORS[$op2]) && (
							(self::OPERATORS[$op1]['associativity'] === 'left' && self::OPERATORS[$op1]['precedence'] <= self::OPERATORS[$op2]['precedence']) ||
							(self::OPERATORS[$op1]['associativity'] === 'right' && self::OPERATORS[$op1]['precedence'] < self::OPERATORS[$op2]['precedence'])
						)
					) {
						$outputQueue[] = array_pop($operatorStack);
					} else {
						break;
					}
				}
				$operatorStack[] = $op1;
			} elseif ($token === '(') {
				$operatorStack[] = $token;
			} elseif ($token === ')') {
				while ($operatorStack !== [] && end($operatorStack) !== '(') {
					$outputQueue[] = array_pop($operatorStack);
				}
				if ($operatorStack === []) {
					throw new InvalidArgumentException('Mismatched parentheses.');
				}
				array_pop($operatorStack);
			} else {
				throw new InvalidArgumentException('Unknown token: ' . $token);
			}
		}

		while ($operatorStack !== []) {
			$op = array_pop($operatorStack);
			if ($op === '(' || $op === ')') {
				throw new InvalidArgumentException('Mismatched parentheses.');
			}
			$outputQueue[] = $op;
		}

		$valueStack = [];
		foreach ($outputQueue as $token) {
			if (isset(self::OPERATORS[$token])) {
				if ($token === 'u-' || $token === 'u+') {
					if ($valueStack === []) {
						throw new InvalidArgumentException('Insufficient operands for operator: ' . substr($token, 1));
					}
					$value = array_pop($valueStack);
					$valueStack[] = $token === 'u-' ? new CSSMathNegate([$value]) : $value;
					continue;
				}
				if (count($valueStack) < 2) {
					throw new InvalidArgumentException('Insufficient operands for operator: ' . $token);
				}
				$operand2 = array_pop($valueStack);
				$operand1 = array_pop($valueStack);

				$valueStack[] = match ($token) {
					'+' => new CSSMathSum([$operand1, $operand2]),
					'-' => new CSSMathSum([$operand1, new CSSMathNegate([$operand2])]),
					'*' => new CSSMathProduct([$operand1, $operand2]),
					'/' => new CSSMathProduct([$operand1, new CSSMathInvert([$operand2])]),
				};
			} else {
				$valueStack[] = CSSNumericValue::parse($token, null, $options, $depth + 1);
			}
		}

		if (count($valueStack) !== 1) {
			throw new InvalidArgumentException('Invalid expression.');
		}

		return $valueStack[0];
	}

	/** @return list<string> */
	private static function tokenize(string $expression): array
	{
		$tokens = [];
		$offset = 0;
		$length = strlen($expression);
		$expectsOperand = true;
		$numberPattern = '/\G[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?(?:[a-zA-Z]+|%)?/';

		while ($offset < $length) {
			$character = $expression[$offset];
			if (ctype_space($character)) {
				$offset++;
				continue;
			}

			if ($character === '/' && ($offset + 1 < $length) && $expression[$offset + 1] === '*') {
				$commentEnd = strpos($expression, '*/', $offset + 2);
				if ($commentEnd === false) {
					throw new InvalidArgumentException('Unclosed comment in calc() expression.');
				}
				$offset = $commentEnd + 2;
				continue;
			}

			if (!$expectsOperand && ($character === '+' || $character === '-' || $character === '*' || $character === '/')) {
				$tokens[] = $character;
				$offset++;
				$expectsOperand = true;
				continue;
			}

			if (($character === '+' || $character === '-') && $expectsOperand) {
				if (preg_match($numberPattern, $expression, $number, 0, $offset)) {
					$tokens[] = $number[0];
					$offset += strlen($number[0]);
					$expectsOperand = false;
					continue;
				}
				$tokens[] = $character === '-' ? 'u-' : 'u+';
				$offset++;
				continue;
			}

			if (preg_match($numberPattern, $expression, $number, 0, $offset)) {
				$tokens[] = $number[0];
				$offset += strlen($number[0]);
				$expectsOperand = false;
				continue;
			}

			if ($character === '(' || $character === ')') {
				$tokens[] = $character;
				$offset++;
				$expectsOperand = $character === '(';
				continue;
			}

			if ($character === '+' || $character === '-' || $character === '*' || $character === '/') {
				$tokens[] = $character;
				$offset++;
				$expectsOperand = true;
				continue;
			}

			if (preg_match('/\G[a-zA-Z][a-zA-Z0-9-]*/', $expression, $identifier, 0, $offset)) {
				$name = $identifier[0];
				$afterName = $offset + strlen($name);
				$open = $afterName;
				while ($open < $length && ctype_space($expression[$open])) {
					$open++;
				}
				if (($expression[$open] ?? '') === '(') {
					$close = CSSSyntaxScanner::findMatchingDelimiter($expression, $open);
					if ($close === null) {
						throw new InvalidArgumentException('Mismatched parentheses.');
					}
					$tokens[] = substr($expression, $offset, $close - $offset + 1);
					$offset = $close + 1;
					$expectsOperand = false;
					continue;
				}
				$tokens[] = $name;
				$offset = $afterName;
				$expectsOperand = false;
				continue;
			}

			$start = $offset++;
			while ($offset < $length && !ctype_space($expression[$offset])
				&& $expression[$offset] !== '(' && $expression[$offset] !== ')'
				&& $expression[$offset] !== '+' && $expression[$offset] !== '-'
				&& $expression[$offset] !== '*' && $expression[$offset] !== '/') {
				$offset++;
			}
			$tokens[] = substr($expression, $start, $offset - $start);
			$expectsOperand = false;
		}

		return $tokens;
	}

	private static function isOperand(string $token): bool
	{
		return preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?(?:[a-zA-Z]+|%)?$/D', $token) === 1
			|| preg_match('/^[a-zA-Z][a-zA-Z0-9-]*\s*\(/', $token) === 1;
	}
}
