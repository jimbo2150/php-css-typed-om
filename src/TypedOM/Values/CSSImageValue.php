<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\SimpleValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

/**
 * Represents a CSS image value.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSImageValue
 */
class CSSImageValue extends CSSStyleValue
{
    use SimpleValueTrait;
    use MagicPropertyAccessTrait;

    public function __construct(string $url)
    {
        $this->initializeSimpleValue($url, 'image');
        parent::__construct('image');
    }

    public function getUrl(): string
    {
        return $this->getValue();
    }

    public function toString(): string
    {
        return 'url('.$this->getValue().')';
    }

    public function isValid(): bool
    {
        // Basic validation for URL format.
        return false !== filter_var($this->getValue(), FILTER_VALIDATE_URL);
    }

    public function clone(): self
    {
        return new self($this->getValue());
    }
}
