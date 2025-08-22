<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Trait for simple value classes (color, keyword, image).
 * 
 * Provides common functionality for simple CSS values that don't need complex operations.
 */
trait MultiValueTrait
{
	/** @var array<CSSUnitValue> */
    public protected(set) array $values {
		get {
			return $this->values;
		}
	}

	protected function add(CSSUnitValue $value) {
		$this->values[] = $value;
	}

    /**
     * Convert to string representation.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }

    /**
     * Clone the value.
     */
    public function clone(): static
    {
        $clone = clone $this;
        return $clone;
    }
}