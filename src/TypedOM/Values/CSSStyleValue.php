<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

/**
 * Base class for CSS Typed OM values
 * Represents a CSS value that can be manipulated through the Typed OM API
 */
abstract class CSSStyleValue
{
    protected string $value;
    protected string $type;
    
    public function __construct(string $value, string $type)
    {
        $this->value = $value;
        $this->type = $type;
    }
    
    /**
     * Get the string representation of the value
     */
    abstract public function toString(): string;
    
    /**
     * Get the type of the value
     */
    public function getType(): string
    {
        return $this->type;
    }
    
    /**
     * Get the raw value
     */
    public function getValue(): string
    {
        return $this->value;
    }
    
    /**
     * Parse a CSS value and return appropriate CSSStyleValue instance
     */
    public static function parse(string $cssText): CSSStyleValue
    {
        $cssText = trim($cssText);
        
        // Handle different value types
        if (preg_match('/^(-?\d*\.?\d+)([a-zA-Z%]+)$/', $cssText, $matches)) {
            $value = (float) $matches[1];
            $unit = $matches[2];
            
            if ($unit === '%') {
                return new CSSUnitValue($value, '%');
            }
            
            return new CSSUnitValue($value, $unit);
        }
        
        if (is_numeric($cssText)) {
            return new CSSUnitValue((float) $cssText, 'number');
        }
        
        if (preg_match('/^#([0-9a-fA-F]{3,8})$/', $cssText)) {
            return new CSSColorValue($cssText);
        }
        
        if (preg_match('/^rgb\(/', $cssText) || preg_match('/^hsl\(/', $cssText)) {
            return new CSSColorValue($cssText);
        }
        
        if (strpos($cssText, ' ') !== false) {
            return new CSSKeywordValue($cssText);
        }
        
        return new CSSKeywordValue($cssText);
    }
    
    /**
     * Create a new instance from a string
     */
    public static function fromString(string $cssText): CSSStyleValue
    {
        return static::parse($cssText);
    }
    
    /**
     * Check if this value is valid
     */
    abstract public function isValid(): bool;
    
    /**
     * Clone this value
     */
    abstract public function clone(): CSSStyleValue;
}