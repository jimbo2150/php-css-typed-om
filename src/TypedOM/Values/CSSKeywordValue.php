<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

class CSSKeywordValue extends CSSStyleValue
{

    public function __construct(string $value)
    {
        parent::__construct($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function clone(): self
    {
        return new self($this->value);
    }

	protected static function validateValue(mixed $value): bool
    {
        return '' !== trim($value);
    }
}
