<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;

/**
 * CSSCalcParser parses CSS calc() expressions and returns CSSNumericValue objects.
 *
 * This parser implements the Shunting-yard algorithm to convert infix notation
 * of mathematical expressions into Reverse Polish Notation (RPN), then evaluates
 * the RPN to build a tree of CSSMathValue objects representing the calculation.
 *
 * Supported operators: +, -, *, /
 * Supports parentheses for grouping.
 * Operands can be numbers with optional CSS units (e.g., 10px, 50%, 2em).
 *
 * @example
 * $result = CSSCalcParser::parse('calc(10px + 5px)');
 * // Returns a CSSMathSum object
 */
class CSSCalcParser
{
	/** @var array<string, array{precedence: int, associativity: string}> Operator precedence and associativity */
	private const OPERATORS = [
		'+' => ['precedence' => 1, 'associativity' => 'left'],
		'-' => ['precedence' => 1, 'associativity' => 'left'],
		'*' => ['precedence' => 2, 'associativity' => 'left'],
		'/' => ['precedence' => 2, 'associativity' => 'left'],
		'u+' => ['precedence' => 3, 'associativity' => 'right'],
		'u-' => ['precedence' => 3, 'associativity' => 'right'],
	];

	/**
	 * Parses a CSS calc() expression and returns the corresponding CSSNumericValue.
	 *
	 * @param string $calcString The CSS calc() expression to parse (e.g., 'calc(10px + 5px)')
	 * @return CSSNumericValue The parsed numeric value, which may be a CSSUnitValue or a CSSMathValue
	 * @throws \InvalidArgumentException If the expression is malformed or contains invalid tokens
	 */
	public static function parse(string $calcString): CSSNumericValue
	{
		$calcString = trim($calcString);
		if (!preg_match('/^calc\s*\(/i', $calcString, $wrapper)) {
			throw new \InvalidArgumentException('Expected a calc() expression.');
		}
		$open = strpos($calcString, '(');
		$close = CSSSyntaxScanner::findMatchingDelimiter($calcString, $open);
		if ($close === null || ($close < strlen($calcString) - 1 && $calcString[$close + 1] === ')')) {
			throw new \InvalidArgumentException('Mismatched parentheses.');
		}
		if ($close !== strlen($calcString) - 1) {
			throw new \InvalidArgumentException('Invalid expression.');
		}
		$expression = substr($calcString, $open + 1, $close - $open - 1);
		$tokens = self::tokenize($expression);

		// Shunting-yard algorithm: Convert infix to Reverse Polish Notation (RPN)
		$outputQueue = [];
		$operatorStack = [];

		foreach ($tokens as $token) {
			if (self::isOperand($token)) {
				$outputQueue[] = $token;
			} elseif (isset(self::OPERATORS[$token])) {
				$op1 = $token;
				while (
					!empty($operatorStack) &&
					($op2 = end($operatorStack)) &&
					isset(self::OPERATORS[$op2]) &&
					(
						('left' === self::OPERATORS[$op1]['associativity'] && self::OPERATORS[$op1]['precedence'] <= self::OPERATORS[$op2]['precedence']) ||
						('right' === self::OPERATORS[$op1]['associativity'] && self::OPERATORS[$op1]['precedence'] < self::OPERATORS[$op2]['precedence'])
					)
				) {
					$outputQueue[] = array_pop($operatorStack);
				}
				$operatorStack[] = $op1;
			} elseif ('(' === $token) {
				$operatorStack[] = $token;
			} elseif (')' === $token) {
				while (!empty($operatorStack) && '(' !== end($operatorStack)) {
					$outputQueue[] = array_pop($operatorStack);
				}
				if (empty($operatorStack)) {
					throw new \InvalidArgumentException('Mismatched parentheses.');
				}
				array_pop($operatorStack); // Pop the '('
			} else {
				throw new \InvalidArgumentException('Unknown token: '.$token);
			}
		}

		while (!empty($operatorStack)) {
			$op = array_pop($operatorStack);
			if ('(' === $op || ')' === $op) {
				throw new \InvalidArgumentException('Mismatched parentheses.');
			}
			$outputQueue[] = $op;
		}

		// Evaluation of RPN: Build the CSSMathValue tree
		$valueStack = [];
		foreach ($outputQueue as $token) {
			if (isset(self::OPERATORS[$token])) {
				if ($token === 'u-' || $token === 'u+') {
					if ($valueStack === []) {
						throw new \InvalidArgumentException('Insufficient operands for operator: ' . substr($token, 1));
					}
					$value = array_pop($valueStack);
					$valueStack[] = $token === 'u-' ? new CSSMathNegate([$value]) : $value;
					continue;
				}
				if (count($valueStack) < 2) {
					throw new \InvalidArgumentException('Insufficient operands for operator: '.$token);
				}
				$operand2 = array_pop($valueStack);
				$operand1 = array_pop($valueStack);

				switch ($token) {
					case '+':
						$valueStack[] = new CSSMathSum([$operand1, $operand2]);
						break;
					case '-':
						$valueStack[] = new CSSMathSum([$operand1, new CSSMathNegate([$operand2])]);
						break;
					case '*':
						$valueStack[] = new CSSMathProduct([$operand1, $operand2]);
						break;
					case '/':
						$valueStack[] = new CSSMathProduct([$operand1, new CSSMathInvert([$operand2])]);
						break;
				}
			} else { // Operand
				$valueStack[] = CSSNumericValue::parse($token);
			}
		}

		if (1 !== count($valueStack)) {
			throw new \InvalidArgumentException('Invalid expression.');
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

			if (str_contains('+-*/', $character)) {
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
						throw new \InvalidArgumentException('Mismatched parentheses.');
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
				&& !str_contains('()+-*/', $expression[$offset])) {
				$offset++;
			}
			$tokens[] = substr($expression, $start, $offset - $start);
			$expectsOperand = false;
		}

		return $tokens;
	}

	private static function isOperand(string $token): bool
	{
		return (bool) preg_match('/^[+-]?(?:\d+\.?\d*|\.\d+)(?:[eE][+-]?\d+)?(?:[a-zA-Z]+|%)?$/D', $token)
			|| preg_match('/^[a-zA-Z][a-zA-Z0-9-]*\s*\(/', $token) === 1;
	}
}
