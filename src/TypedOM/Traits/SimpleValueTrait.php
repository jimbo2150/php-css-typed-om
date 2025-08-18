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
    /** @var mixed The stored value */
    private mixed $value;

    /** @var string The value type */
    private string $valueType;

    /**
     * Initialize simple value.
     * 
     * @param mixed $value The value to store
     * @param string $valueType The type of value (for validation)
     */
    private function initializeSimpleValue(mixed $value, string $valueType): void
    {
        $this->value = $value;
        $this->valueType = $valueType;
    }

    /**
     * Get the value.
     */
    public function getValue(): mixed
    {
        return $this->value;
    }

    /**
     * Set the value.
     * 
     * @param mixed $value The new value
     * @throws \InvalidArgumentException When value type is invalid
     */
    public function setValue(mixed $value): void
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
    private function validateValue(mixed $value): bool
    {
        return match ($this->valueType) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            'color' => is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$|^#[0-9a-fA-F]{3}$|^rgb\(|^rgba\(|^hsl\(|^hsla\(/', $value),
            'url' => is_string($value) && str_starts_with($value, 'url('),
            'keyword' => is_string($value) && preg_match('/^[a-zA-Z-]+$/', $value),
            default => true,
        };
    }

    /**
     * Convert to string representation.
     */
    public function toString(): string
    {
        return match ($this->valueType) {
            'color' => $this->formatColor(),
            'url' => $this->formatUrl(),
            'keyword' => (string) $this->value,
            default => (string) $this->value,
        };
    }

    /**
     * Format color value for string output.
     */
    private function formatColor(): string
    {
        if (is_string($this->value)) {
            return $this->value;
        }
        
        if (is_array($this->value)) {
            // Handle RGB/RGBA arrays
            if (isset($this->value['r'], $this->value['g'], $this->value['b'])) {
                $alpha = $this->value['a'] ?? 1;
                if ($alpha < 1) {
                    return sprintf('rgba(%d, %d, %d, %s)', 
                        $this->value['r'], $this->value['g'], $this->value['b'], $alpha);
                }
                return sprintf('rgb(%d, %d, %d)', 
                    $this->value['r'], $this->value['g'], $this->value['b']);
            }
            
            // Handle HSL/HSLA arrays
            if (isset($this->value['h'], $this->value['s'], $this->value['l'])) {
                $alpha = $this->value['a'] ?? 1;
                if ($alpha < 1) {
                    return sprintf('hsla(%s, %s%%, %s%%, %s)', 
                        $this->value['h'], $this->value['s'], $this->value['l'], $alpha);
                }
                return sprintf('hsl(%s, %s%%, %s%%)', 
                    $this->value['h'], $this->value['s'], $this->value['l']);
            }
        }
        
        return (string) $this->value;
    }

    /**
     * Format URL value for string output.
     */
    private function formatUrl(): string
    {
        if (is_string($this->value)) {
            return $this->value;
        }
        
        if (is_array($this->value) && isset($this->value['url'])) {
            return 'url("' . $this->value['url'] . '")';
        }
        
        return 'url("' . (string) $this->value . '")';
    }

    /**
     * Check if this value is valid.
     */
    public function isValid(): bool
    {
        return $this->validateValue($this->value);
    }

    /**
     * Clone the value.
     */
    public function clone(): static
    {
        $clone = clone $this;
        if (is_array($this->value)) {
            $clone->value = array_map(fn($v) => is_object($v) ? clone $v : $v, $this->value);
        }
        return $clone;
    }

    /**
     * Check if this value equals another.
     * 
     * @param mixed $other The value to compare with
     * @return bool True if equal
     */
    public function equals(mixed $other): bool
    {
        if (!$other instanceof static) {
            return false;
        }

        return $this->value === $other->getValue();
    }
}