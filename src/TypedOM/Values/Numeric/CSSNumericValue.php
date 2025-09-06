<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;

use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\LengthTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\SimpleValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TypeableUnitTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
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
	use SimpleValueTrait, TypeableUnitTrait, LengthTrait;

	/**
	 * CSSNumericValue constructor.
	 */
	public function __construct() {
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

	/**
	 * Converts the numeric value to the specified unit.
	 *
	 * @param string $unit The target unit
	 * @param CSSContext|null $context Optional context for relative unit conversions (default: new CSSContext())
	 * @return CSSUnitValue The converted value
	 */
	public function to(string $unit, ?CSSContext $context = null): CSSUnitValue
	{
		$context = $context ?? new CSSContext();
		$currentValue = $this->value;
		$currentUnit = $this->unit;

		// If same unit, return a copy
		if ($currentUnit === $unit) {
			return new CSSUnitValue($currentValue, $currentUnit);
		}

		// Special case for percent: keep value as-is
		if ($unit === 'percent') {
			return new CSSUnitValue($currentValue, $unit);
		}

		// Convert to base unit (px) first, then to target unit
		$pxValue = self::convertToPx($currentValue, $currentUnit, $context);
		$targetValue = self::convertFromPx($pxValue, $unit, $context);

		return new CSSUnitValue($targetValue, $unit);
	}

	/**
	 * Converts a value from the given unit to pixels.
	 *
	 * @param float $value The value to convert
	 * @param string $unit The source unit
	 * @return float The value in pixels
	 */
	private static function convertToPx(float $value, string $unit, CSSContext $context): float
	{
		$factor = $context->getToPxFactor($unit);
		return $value * $factor;
	}

	/**
	 * Converts a pixel value to the specified unit.
	 *
	 * @param float $pxValue The value in pixels
	 * @param string $unit The target unit
	 * @return float The converted value
	 */
	private static function convertFromPx(float $pxValue, string $unit, CSSContext $context): float
	{
		$factor = $context->getFromPxFactor($unit);
		return $pxValue * $factor;
	}

	/**
	 * Converts the current value to each specified unit and returns their sum.
	 *
	 * @param string ...$units The units to convert to and sum
	 * @return CSSMathSum A CSSMathSum containing the sum of all converted values
	 */
	public function toSum(string ...$units): CSSMathSum
	{
		$convertedValues = [];
		$currentValues = $this->_getCurrentValues();

		// Convert each current value to the corresponding unit
		foreach ($units as $i => $unit) {
			$value = $currentValues[$i] ?? $this;
			$convertedValues[] = $value->to($unit);
		}

		// Return CSSMathSum with all converted values
		return new CSSMathSum($convertedValues);
	}


	/**
	 * Adds the given value to this numeric value.
	 *
	 * @param CSSNumericValue|CSSUnitValue $value The value to add
	 * @return CSSNumericValue The result of the addition
	 */
	public function add(CSSNumericValue|CSSUnitValue $value): CSSNumericValue
	{
		return (new CSSMathSum([
			...$this->_getCurrentValues(), $value
		]));
	}

	/**
	 * Subtracts the given value from this numeric value.
	 *
	 * @param CSSNumericValue|CSSUnitValue $value The value to subtract
	 * @return CSSNumericValue The result of the subtraction
	 */
	public function sub(CSSNumericValue|CSSUnitValue $value): CSSNumericValue
	{
		return (new CSSMathSum([
			...$this->_getCurrentValues(), new CSSMathNegate([$value])
		]));
	}

	/**
	 * Multiplies this numeric value by the given value.
	 *
	 * @param CSSNumericValue|CSSUnitValue $value The multiplier
	 * @return CSSMathProduct The result of the multiplication
	 */
	public function mul(CSSNumericValue|CSSUnitValue $value): CSSMathProduct
	{
		return (new CSSMathProduct([
			...$this->_getCurrentValues(), $value
		]));
	}

	/**
	 * Divides this numeric value by the given value.
	 *
	 * @param CSSNumericValue|CSSUnitValue $value The divisor
	 * @return CSSMathProduct The result of the division
	 */
	public function div(CSSNumericValue|CSSUnitValue $value): CSSMathProduct
	{
		return (new CSSMathProduct([
			...$this->_getCurrentValues(), new CSSMathInvert([$value])
		]));
	}

	/**
	 * Returns the minimum value between this and the given values.
	 *
	 * @param CSSNumericArray $values The values to compare
	 * @return CSSMathMin The minimum value
	 */
	public function min(CSSNumericArray $values): CSSMathMin
	{
		return (new CSSMathMin([
			...$this->_getCurrentValues(), ...$values->values
		]));
	}

	/**
	 * Returns the maximum value between this and the given values.
	 *
	 * @param CSSNumericArray $values The values to compare
	 * @return CSSMathMax The maximum value
	 */
	public function max(CSSNumericArray $values): CSSMathMax
	{
		return (new CSSMathMax([
			...$this->_getCurrentValues(), ...$values->values
		]));
	}

	/**
	 * Checks if this numeric value is equal to another.
	 *
	 * @param self $numericValue The value to compare
	 * @return bool True if equal, false otherwise
	 */
	public function equals(self $numericValue): bool
	{
		if ($this instanceof CSSUnitValue && $numericValue instanceof CSSUnitValue) {
			if ($this->unit === $numericValue->unit) {
				return abs($this->value - $numericValue->value) < 0.0001;
			} else {
				// Check if units are of the same type (e.g., both length)
				if ($this->type() === $numericValue->type()) {
					try {
						$converted = $numericValue->to($this->unit);
						return abs($this->value - $converted->value) < 0.0001;
					} catch (\Exception $e) {
						return false;
					}
				} else {
					return false;
				}
			}
		} else {
			// For other cases, compare recursively if same type
			if (get_class($this) === get_class($numericValue)) {
				$thisValues = $this->_getCurrentValues();
				$otherValues = $numericValue->_getCurrentValues();
				if (count($thisValues) === count($otherValues)) {
					foreach ($thisValues as $i => $value) {
						if (!$value->equals($otherValues[$i])) {
							return false;
						}
					}
					return true;
				}
			}
			return false;
		}
	}

	/**
	 * Gets the current values for this numeric value.
	 *
	 * @return array The current values
	 */
	protected function _getCurrentValues(): array {
		return property_exists($this, 'values') && $this->values instanceof \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray
			? $this->values->values
			: [$this];
	}
}
