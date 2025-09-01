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
	private const OPERATORS = [
		'+' => ['precedence' => 1, 'associativity' => 'left'],
		'-' => ['precedence' => 1, 'associativity' => 'left'],
		'*' => ['precedence' => 2, 'associativity' => 'left'],
		'/' => ['precedence' => 2, 'associativity' => 'left'],
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
		// Remove 'calc()' wrapper and trim whitespace
		$expression = trim(substr($calcString, 5, -1));

		// Tokenization: Split the expression into operators, operands, and parentheses
		preg_match_all(
			'/(-?[0-9]+(?:\.[0-9]*)?(?:[a-zA-Z%]+)?)|([+\-*\/()])|([^+\-*\/()\s]+)/i',
			$expression,
			$matches,
			PREG_SET_ORDER
		);

		$tokens = [];
		foreach ($matches as $match) {
			if (!empty($match[1])) { // Number with optional unit
				$tokens[] = $match[1];
			} elseif (!empty($match[2])) { // Operator or parenthesis
				$tokens[] = $match[2];
			} elseif (!empty($match[3])) { // Unknown token
				$tokens[] = $match[3];
			}
		}

		// Shunting-yard algorithm: Convert infix to Reverse Polish Notation (RPN)
		$outputQueue = [];
		$operatorStack = [];

		foreach ($tokens as $token) {
			if (is_numeric($token) || preg_match('/^-?[0-9]+(?:\.[0-9]*)?(?:[a-zA-Z%]+)?$/i', $token)) { // Operand (number with unit)
				$outputQueue[] = $token;
			} elseif (isset(self::OPERATORS[$token])) { // Operator
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
			if (isset(self::OPERATORS[$token])) { // Operator
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
}
