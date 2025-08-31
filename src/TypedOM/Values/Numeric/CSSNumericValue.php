<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\Process\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\LengthTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\SimpleValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TypeableUnitTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
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

	public function to(string $unit): CSSUnitValue
	{
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
		$pxValue = $this->convertToPx($currentValue, $currentUnit);
		$targetValue = $this->convertFromPx($pxValue, $unit);

		return new CSSUnitValue($targetValue, $unit);
	}

	private function convertToPx(float $value, string $unit): float
	{
		return match ($unit) {
			'px' => $value,
			'pt' => $value * 1.333, // 1pt = 1.333px
			'pc' => $value * 16,    // 1pc = 16px
			'in' => $value * 96,    // 1in = 96px
			'cm' => $value * 37.795, // 1cm ≈ 37.795px
			'mm' => $value * 3.7795, // 1mm ≈ 3.7795px
			'em' => $value * 16,    // Assume 1em = 16px (default font-size)
			'rem' => $value * 16,   // Assume 1rem = 16px
			'vw' => $value * 9.6,   // Assume 1vw = 9.6px (viewport width / 100)
			'vh' => $value * 5.4,   // Assume 1vh = 5.4px (viewport height / 100)
			'vmin' => $value * 5.4, // Assume vmin = vh for simplicity
			'vmax' => $value * 9.6, // Assume vmax = vw for simplicity
			default => $value,      // Unknown unit, return as-is
		};
	}

	private function convertFromPx(float $pxValue, string $unit): float
	{
		return match ($unit) {
			'px' => $pxValue,
			'pt' => $pxValue / 1.333,
			'pc' => $pxValue / 16,
			'in' => $pxValue / 96,
			'cm' => $pxValue / 37.795,
			'mm' => $pxValue / 3.7795,
			'em' => $pxValue / 16,
			'rem' => $pxValue / 16,
			'vw' => $pxValue / 9.6,
			'vh' => $pxValue / 5.4,
			'vmin' => $pxValue / 5.4,
			'vmax' => $pxValue / 9.6,
			default => $pxValue,    // Unknown unit, return px value
		};
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


	public function add(CSSNumericValue $value): CSSMathSum
	{
		return (new CSSMathSum([
			...$this->_getCurrentValues(), $value
		]));
	}

	public function sub(CSSMathSum $value): CSSMathSum
	{
  		return (new CSSMathSum([
			...$this->_getCurrentValues(), $value
		]));
	}

	public function mul(CSSNumericValue $value): CSSMathProduct
	{
		return (new CSSMathProduct([
			...$this->_getCurrentValues(), $value
		]));
	}

	public function div(CSSNumericValue $value): CSSMathProduct
	{
		return (new CSSMathProduct([
			...$this->_getCurrentValues(), $value
		]));
	}

	public function min(CSSNumericArray $values): CSSMathMin
	{
		return (new CSSMathMin([
			...$this->_getCurrentValues(), ...$values->values
		]));
	}

	public function max(CSSNumericArray $values): CSSMathMax
	{
		return (new CSSMathMax([
			...$this->_getCurrentValues(), ...$values->values
		]));
	}

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

	protected function _getCurrentValues(): array {
		return isset($this->length) && isset($this->inner_values) &&
			is_array($this->inner_values) ?
				$this->inner_values :
				($this->value ? [$this] : []);
	}
}
