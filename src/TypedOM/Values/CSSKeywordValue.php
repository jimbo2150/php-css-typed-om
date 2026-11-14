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
		$this->setValue($keyword);
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

    public function getValue(): string
    {
        return $this->keyword;
    }

    public function setValue(string $value): void
    {
        $value = trim($value);
		if (!preg_match('/^(?:--|[-_a-zA-Z]|[^\x00-\x7F]|\\\\(?:[0-9a-fA-F]{1,6}\s?|[^\r\n\f]))(?:[-_a-zA-Z0-9]|[^\x00-\x7F]|\\\\(?:[0-9a-fA-F]{1,6}\s?|[^\r\n\f]))*$/uD', $value)) {
			throw new \InvalidArgumentException('CSS keyword value must be a non-empty identifier.');
        }
        $this->keyword = $value;
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

    public function __get(string $name): mixed
    {
        if ($name === 'value') {
            return $this->getValue();
        }
        throw new \Error('Undefined property: ' . static::class . '::$' . $name);
    }

    public function __set(string $name, mixed $value): void
    {
        if ($name === 'value' && is_string($value)) {
            $this->setValue($value);
            return;
        }
        throw new \Error('Cannot set property ' . static::class . '::$' . $name);
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
