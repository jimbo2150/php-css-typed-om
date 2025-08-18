<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\Process\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;

/**
 * Represents a CSS value that can be expressed as a number, or a number and a unit.
 * It is the base class for CSSUnitValue and other future math-related CSS classes.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericValue
 */
abstract class CSSNumericValue extends CSSStyleValue
{
	public private(set) CSSNumericType $type {
		get {
			return $this->type;
		}
	}

	public function type(): CSSNumericType {
		return $this->type;
	}

	public function __construct() {
		$this->type = new CSSNumericType();
	}

	/**
	 * The parse() method of the CSSNumericValue interface creates a new CSSUnitValue object from a CSS numeric value.
	 *
	 * @param string $cssText the CSS text to parse
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericValue/parse
	 */
	public static function parse(string $cssText): static
	{
		$cssText = trim($cssText);

		// Handle calc() expressions
		if (str_starts_with($cssText, 'calc(') && str_ends_with($cssText, ')')) {
			return CSSCalcParser::parse($cssText);
		}

		// This is a simplified parser. A real one would need a proper tokenizer
		// and handle calc() expressions.
		if (preg_match('/^(-?\d*\.?\d+)([a-zA-Z%]*)$/', $cssText, $matches)) {
			$value = (float) $matches[1];
			$unit = $matches[2];

			if (empty($unit)) {
				$unit = 'number';
			}

			return new CSSUnitValue($value, $unit);
		}

		throw new \InvalidArgumentException('Invalid CSS numeric value: '.$cssText);
	}

	/**
	 * Creates a new CSSUnitValue object from a CSS numeric value.
	 *
	 * @param string $cssText the CSS text to parse
	 */
	public function from(string $cssText): static
	{
		return static::parse($cssText);
	}

	public function to(string $unit): CSSMathSum
	{
		// TODO: Implement
	}

	/**
	 * Adds all the values in the values list and returns the result.
	 *
	 * @param string[] $values
	 */
	public function toSum(string ...$units): CSSMathSum
	{
		// TODO: Implement
	}


	public function add(string|CSSMathSum $value): CSSMathSum
	{
		// TODO: Implement
	}

	public function sub(string|CSSMathSum $value): CSSMathSum
	{
		// TODO: Implement
	}

	public function mul(string|CSSNumericValue $value): CSSMathProduct
	{
		// TODO: Implement
	}

	public function div(string|CSSNumericValue $value): CSSMathProduct
	{
		// TODO: Implement
	}

	public function min(string|CSSNumericValue ...$values): CSSUnitValue
	{
		// TODO: Implement
	}

	public function max(string|CSSNumericValue ...$values): CSSUnitValue
	{
		// TODO: Implement
	}

	public function equals(string|CSSNumericValue $value): bool
	{
		// TODO: Implement
	}
}
