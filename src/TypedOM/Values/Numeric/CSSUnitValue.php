<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\SimpleValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

/**
 * CSS Unit Value for Typed OM
 * Represents a CSS value with a unit (e.g., 10px, 50%, 2em).
 */
class CSSUnitValue extends CSSNumericValue
{

	public function __construct(string|float $value, string|CSSUnitEnum $unit)
	{
		$this->setValue(floatval($value));
		$this->setUnit($unit);
	}

	/**
	 * Convert to string representation.
	 */
	public function toString(): string
	{
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
