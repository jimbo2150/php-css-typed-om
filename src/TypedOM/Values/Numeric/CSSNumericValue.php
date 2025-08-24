<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\Process\CSSCalcParser;
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
	use SimpleValueTrait, TypeableUnitTrait;

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
		// TODO: Implement
		return $this === $numericValue;
	}

	protected function _getCurrentValues(): array {
		return isset($this->length) && isset($this->inner_values) &&
			is_array($this->inner_values) ?
				$this->inner_values :
				($this->value ? [$this] : []);
	}
}
