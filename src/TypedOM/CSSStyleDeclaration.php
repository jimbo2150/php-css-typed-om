<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

/**
 * CSS Typed OM Style Declaration
 * Provides a typed interface for CSS style properties
 */
class CSSStyleDeclaration
{
    private array $properties = [];
    private string $cssText = '';
    
    public function __construct(string $cssText = '')
    {
        $this->cssText = $cssText;
        $this->parseCSS($cssText);
    }
    
    /**
     * Parse CSS text and populate properties
     */
    private function parseCSS(string $cssText): void
    {
        if (empty($cssText)) {
            return;
        }
        
        // Simple parsing for now - can be enhanced with full CSS parser
        $declarations = explode(';', $cssText);
        
        foreach ($declarations as $declaration) {
            $declaration = trim($declaration);
            if (empty($declaration)) {
                continue;
            }
            
            $parts = explode(':', $declaration, 2);
            if (count($parts) !== 2) {
                continue;
            }
            
            $property = trim($parts[0]);
            $value = trim($parts[1]);
            
            $this->properties[$property] = CSSStyleValue::parse($value);
        }
    }
    
    /**
     * Get a CSS property value
     */
    public function getPropertyValue(string $property): ?CSSStyleValue
    {
        return $this->properties[$property] ?? null;
    }
    
    /**
     * Set a CSS property value
     */
    public function setProperty(string $property, CSSStyleValue $value): void
    {
        $this->properties[$property] = $value;
        $this->updateCSSText();
    }
    
    /**
     * Remove a CSS property
     */
    public function removeProperty(string $property): void
    {
        unset($this->properties[$property]);
        $this->updateCSSText();
    }
    
    /**
     * Get all properties
     */
    public function getProperties(): array
    {
        return $this->properties;
    }
    
    /**
     * Update the CSS text representation
     */
    private function updateCSSText(): void
    {
        $declarations = [];
        
        foreach ($this->properties as $property => $value) {
            $declarations[] = $property . ': ' . $value->toString();
        }
        
        $this->cssText = implode('; ', $declarations);
    }
    
    /**
     * Get CSS text
     */
    public function getCssText(): string
    {
        return $this->cssText;
    }
    
    /**
     * Set CSS text
     */
    public function setCssText(string $cssText): void
    {
        $this->cssText = $cssText;
        $this->properties = [];
        $this->parseCSS($cssText);
    }
    
    /**
     * Get length of properties
     */
    public function length(): int
    {
        return count($this->properties);
    }
    
    /**
     * Get property at index
     */
    public function item(int $index): ?string
    {
        $keys = array_keys($this->properties);
        return $keys[$index] ?? null;
    }
    
    /**
     * Check if property exists
     */
    public function hasProperty(string $property): bool
    {
        return isset($this->properties[$property]);
    }
    
    /**
     * Get parent rule
     */
    public function getParentRule(): ?CSSRule
    {
        return null; // Can be implemented when CSSRule is available
    }
}