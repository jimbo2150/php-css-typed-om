<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

/**
 * CSSKeywordValue represents a CSS keyword value (e.g., 'auto', 'none', 'inherit').
 * 
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSKeywordValue
 */
class CSSKeywordValue extends CSSStyleValue
{
    /** @var string The keyword value */
    private string $keyword;

    /**
     * CSSKeywordValue constructor.
     *
     * @param string $keyword The CSS keyword
     */
    public function __construct(string $keyword)
    {
        $this->keyword = $keyword;
    }

    /**
     * Get the keyword value.
     *
     * @return string The keyword
     */
    public function getKeyword(): string
    {
        return $this->keyword;
    }

    /**
     * Convert to string representation.
     *
     * @return string The keyword as string
     */
    public function __toString(): string
    {
        return $this->keyword;
    }

    /**
     * Clone this keyword value.
     *
     * @return static The cloned value
     */
    public function clone(): static
    {
        return new static($this->keyword);
    }
}