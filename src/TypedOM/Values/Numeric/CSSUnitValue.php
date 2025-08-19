<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

/**
 * CSS Unit Value for Typed OM
 * Represents a CSS value with a unit (e.g., 10px, 50%, 2em).
 */
class CSSUnitValue extends CSSNumericValue
{

	public private(set) float $value {
		get {
			return $this->value;
		}
	}

 	public private(set) string $unit {
		get {
			return $this->unit;
		}
	}

	public function __construct(float $value, string $unit)
	{
		$this->value = $value;
		$this->unit = $unit;
	}

	/**
	 * Convert to string representation.
	 */
	public function toString(): string
	{
		if ('number' === $this->unit) {
			return (string) $this->value;
		}

		return $this->value.$this->unit;
	}

	/**
	 * Clone this value.
	 */
	public function clone(): CSSStyleValue
	{
		return $this->cloneToSelf();
	}

	private function cloneToSelf(?float $value = null, ?string $unit = null): self {
		return new self($value ?? $this->value, $unit ?? $this->unit);
	}
}
