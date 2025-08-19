<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\SimpleValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

class CSSKeywordValue extends CSSStyleValue
{
    use SimpleValueTrait;

    public function __construct(string $value)
    {
        $this->initializeSimpleValue($value, 'keyword');
        parent::__construct('keyword');
    }

    public function toString(): string
    {
        return $this->getValue();
    }

    public function clone(): CSSStyleValue
    {
        return new self($this->getValue());
    }

    public function isValid(): bool
    {
        return '' !== trim($this->getValue());
    }
}
