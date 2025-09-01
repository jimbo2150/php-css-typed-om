<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Trait for simple value classes (color, keyword, image).
 * 
 * Provides common functionality for simple CSS values that don't need complex operations.
 */
trait SimpleValueTrait
{

    /** @var string|float|null The value */
    public protected(set) string|float|null $value = null {
		get {
			return $this->value;
		}
	}

    /**
     * Set the value.
     * 
     * @param mixed $value The new value
     * @throws \InvalidArgumentException When value type is invalid
     */
    protected function setValue(string|float $value): void
    {
        $this->validateValue($value);
        $this->value = $value;
    }
   
    /**
     * Check if the value is valid.
     *
     * @param mixed $value The value to check
     * @return bool True if valid
     */
    protected static function isValueValid(mixed $value): bool {
		return is_numeric($value);
	}

    /**
     * Validate the value type.
     * 
     * @param mixed $value The value to validate
     * @return bool True if valid
     */
    protected static function validateValue(mixed $value): bool
    {
        if (!static::isValueValid($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid value for %s.',
                static::class
            ));
        }
		return true;
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