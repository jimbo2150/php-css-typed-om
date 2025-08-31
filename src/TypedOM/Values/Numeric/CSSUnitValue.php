<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Stringable;

/**
 * CSS Unit Value for Typed OM
 * Represents a CSS value with a unit (e.g., 10px, 50%, 2em).
 */
class CSSUnitValue extends CSSNumericValue implements Stringable
{

	public function __construct(string|float $value, string|CSSUnitEnum $unit)
	{
		$this->setValue(floatval($value));
		$this->setUnit($unit);
	}

	/**
	 * Add another CSSNumericValue.
	 * If both are CSSUnitValue with same unit, return a simple CSSUnitValue.
	 */
	public function add(CSSNumericValue|CSSUnitValue $value): CSSNumericValue
	{
		if ($value instanceof CSSUnitValue && $this->unit === $value->unit) {
			return new self($this->value + $value->value, $this->unit);
		}
		return parent::add($value);
	}

	/**
	 * Subtract another CSSNumericValue.
	 * If both are CSSUnitValue with same unit, return a simple CSSUnitValue.
	 */
	public function sub(CSSNumericValue|CSSUnitValue $value): CSSNumericValue
	{
		if ($value instanceof CSSUnitValue && $this->unit === $value->unit) {
			return new self($this->value - $value->value, $this->unit);
		}
		return parent::sub($value);
	}

	/**
	 * Convert to string representation.
	 */
	public function __toString(): string {
		return "{$this->value}{$this->unit}";
	}

	/**
	 * Clone this value.
	 */
	public function clone(): static
	{
		return $this->cloneToSelf();
	}

	private function cloneToSelf(?float $value = null, ?string $unit = null): self {
		return new self($value ?? $this->value, $unit ?? $this->unit);
	}
}
