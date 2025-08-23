<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;

/**
 * Trait for simple value classes (color, keyword, image).
 * 
 * Provides common functionality for simple CSS values that don't need complex operations.
 */
trait SimpleValueTrait
{
    public protected(set) string|float $value {
		get {
			return $this->value;
		}
	}

	public private(set) CSSNumericType $type {
		get {
			return $this->type;
		}
	}

	public function type(): CSSNumericType {
		return $this->type;
	}

    /**
     * Set the value.
     * 
     * @param mixed $value The new value
     * @throws \InvalidArgumentException When value type is invalid
     */
    protected function setValue(mixed $value, ?CSSNumericType $type = null): void
    {
        $this->validateValue($value, $type);
        $this->value = $value;
    }

	protected static function isValueValid(mixed $value): bool {
		return false;
	}

    /**
     * Validate the value type.
     * 
     * @param mixed $value The value to validate
     * @return bool True if valid
     */
    protected static function validateValue(mixed $value, CSSNumericType $type): bool
    {
        if (!static::isValueValid($value)) {
            throw new \InvalidArgumentException(sprintf(
                'Invalid value type for %s. Expected %s, got %s',
                static::class,
                $type,
                gettype($value)
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