<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

/**
 * Represents a CSS math value.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathValue
 */
abstract class CSSMathValue extends CSSNumericValue
{
    protected array $values;

    public function __construct(string $type, array $values)
    {
        $this->values = $values;
        parent::__construct($type);
    }

    /**
     * Get the values in this math expression.
     *
     * @return CSSNumericValue[]
     */
    public function getValues(): array
    {
        return $this->values;
    }

    public function toString(): string
    {
        return $this->buildString();
    }

    abstract protected function buildString(): string;

    public function clone(): CSSStyleValue
    {
        $clonedValues = [];
        foreach ($this->values as $value) {
            $clonedValues[] = clone $value;
        }
        return new static($this->type, $clonedValues);
    }
}