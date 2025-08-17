<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

/**
 * Base class for CSS Typed OM values
 * Represents a CSS value that can be manipulated through the Typed OM API
 */
abstract class CSSStyleValue
{
    protected string $type;
    
    public function __construct(string $type)
    {
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
     * Parse a CSS value and return appropriate CSSStyleValue instance.
     * This is a simplified parser and should be replaced with a more robust solution.
     */
    public static function createFromCssText(string $cssText): CSSStyleValue
    {
        $cssText = trim($cssText);

        try {
            return CSSColorValue::parse($cssText);
        } catch (\InvalidArgumentException $e) {
            // Not a color, try next
        }

        try {
            return CSSNumericValue::parse($cssText);
        } catch (\InvalidArgumentException $e) {
            // Not a numeric value, try next
        }

        try {
            return CSSKeywordValue::parse($cssText);
        } catch (\InvalidArgumentException $e) {
            // Not a keyword, try next
        }

        // If none of the above, it's an unparsed value
        return new CSSUnparsedValue([$cssText]);
    }

    public static function parse(string $cssText): CSSStyleValue
    {
        return self::createFromCssText($cssText);
    }

    /**
     * Parses a string containing one or more CSS values and returns an array of CSSStyleValue objects.
     * This is a simplified implementation and may not handle all complex CSS value strings.
     *
     * @param string $cssText The CSS value string to parse.
     * @return CSSStyleValue[] An array of CSSStyleValue objects.
     */
    public static function parseAll(string $cssText): array
    {
        $cssText = trim($cssText);
        if (empty($cssText)) {
            return [];
        }

        $values = [];
        // Simple split by space. This will need to be more robust for complex values.
        $parts = preg_split('/\s+/', $cssText, -1, PREG_SPLIT_NO_EMPTY);

        foreach ($parts as $part) {
            $values[] = self::createFromCssText($part);
        }

        return $values;
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