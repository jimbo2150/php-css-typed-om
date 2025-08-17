<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

class CSSKeywordValue extends CSSStyleValue
{
    private string $keywordString;

    public function __construct(string $keywordString)
    {
        $this->keywordString = $keywordString;
        parent::__construct('keyword');
    }

    public static function parse(string $cssText): self
    {
        $cssText = trim($cssText);

        if ($cssText !== '') {
            return new self($cssText);
        }

        throw new \InvalidArgumentException('Invalid CSS keyword value: ' . $cssText);
    }

    public function toString(): string
    {
        return $this->keywordString;
    }

    public function isValid(): bool
    {
        // A keyword should not be empty.
        // Further validation would depend on the CSS property it's used for.
        return trim($this->value) !== '';
    }

    public function clone(): CSSStyleValue
    {
        return new self($this->value);
    }
}
