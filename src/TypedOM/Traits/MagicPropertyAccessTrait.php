<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

/**
 * Trait for common magic getter/setter functionality.
 * 
 * Provides consistent property access patterns across Typed OM classes.
 */
trait MagicPropertyAccessTrait
{
    /**
     * @var array<string, mixed> Map of property names to their values
     */
    private array $properties = [];

    /**
     * @var array<string, string> Map of property names to their types for validation
     */
    private array $propertyTypes = [];

    /**
     * Initialize the property access system.
     * 
     * @param array<string, mixed> $properties Initial properties
     * @param array<string, string> $propertyTypes Property type validation map
     */
    private function initializeProperties(array $properties, array $propertyTypes): void
    {
        $this->properties = $properties;
        $this->propertyTypes = $propertyTypes;
    }

    /**
     * Magic getter for property access.
     * 
     * @param string $name Property name
     * @return mixed Property value
     * @throws \Error When property is undefined
     */
    public function __get(string $name): mixed
    {
        if (isset($this->properties[$name])) {
            return $this->properties[$name];
        }

        // Allow access to type property from parent classes
        if ($name === 'type' && property_exists($this, 'type')) {
            return $this->type;
        }

        throw new \Error(sprintf('Undefined property: %s::$%s', static::class, $name));
    }

    /**
     * Magic setter for property access.
     * 
     * @param string $name Property name
     * @param mixed $value Property value
     * @throws \Error When property is read-only or undefined
     */
    public function __set(string $name, mixed $value): void
    {
        // Check if property exists
        if (!array_key_exists($name, $this->properties)) {
            throw new \Error(sprintf('Cannot set property %s::$%s', static::class, $name));
        }

        // Validate type if specified
        if (isset($this->propertyTypes[$name])) {
            $expectedType = $this->propertyTypes[$name];
            $actualType = gettype($value);
            
            // Handle specific type checks
            if ($expectedType === 'float' && is_numeric($value)) {
                $value = (float) $value;
            } elseif ($expectedType === 'int' && is_numeric($value)) {
                $value = (int) $value;
            } elseif ($expectedType === 'string') {
                $value = (string) $value;
            } elseif ($expectedType === 'bool') {
                $value = (bool) $value;
            } elseif ($actualType !== $expectedType && !is_a($value, $expectedType)) {
                throw new \TypeError(sprintf(
                    'Property %s::$%s must be of type %s, %s given',
                    static::class,
                    $name,
                    $expectedType,
                    $actualType
                ));
            }
        }

        $this->properties[$name] = $value;
    }

    /**
     * Check if a property exists.
     * 
     * @param string $name Property name
     * @return bool True if property exists
     */
    public function __isset(string $name): bool
    {
        return isset($this->properties[$name]) || ($name === 'type' && property_exists($this, 'type'));
    }

    /**
     * Get all properties as an array.
     * 
     * @return array<string, mixed> All properties
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * Set multiple properties at once.
     * 
     * @param array<string, mixed> $properties Properties to set
     */
    public function setProperties(array $properties): void
    {
        foreach ($properties as $name => $value) {
            $this->__set($name, $value);
        }
    }
}