<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

/**
 * Trait for simple value classes (color, keyword, image).
 * 
 * Provides common functionality for simple CSS values that don't need complex operations.
 */
trait SimpleValueTrait
{
    public protected(set) string $value {
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
    protected function setValue(mixed $value): void
    {
        if (!$this->validateValue($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid value type for %s. Expected %s, got %s',
                static::class,
                $this->valueType,
                gettype($value)
            ));
        }
        $this->value = $value;
    }

    /**
     * Validate the value type.
     * 
     * @param mixed $value The value to validate
     * @return bool True if valid
     */
    protected static function validateValue(mixed $value): bool
    {
        return true;
    }

    /**
     * Convert to string representation.
     */
    public function toString(): string
    {
        return $this->value;
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