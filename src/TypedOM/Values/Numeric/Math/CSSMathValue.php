<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MultiValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS math value.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathValue
 */
abstract class CSSMathValue extends CSSNumericValue
{
	use CSSMathOperatorTrait, MultiValueTrait;

    public function __toString(): string
    {
        // TODO: Implement
    }

    public function clone(): static
    {
        $clonedValues = [];
        foreach ($this->values as $value) {
            $clonedValues[] = clone $value;
        }
        return new static($clonedValues);
    }
}