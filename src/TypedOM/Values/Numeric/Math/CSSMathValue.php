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
	use CSSMathOperationTrait, MultiValueTrait;

	/**
	 * Convert to string representation.
	 *
	 * @return string The CSS string representation
	 */
    public function __toString(): string
    {
        $values = array_map(fn($v) => (string)$v, $this->values->values);
        $count = count($values);

        if ($count === 1) {
            if (static::operator === 'invert') {
                return 'calc(1 / ' . $values[0] . ')';
            } elseif (static::operator === 'negate') {
                return 'calc(-' . $values[0] . ')';
            }
        }

        if (defined(static::class . '::sign')) {
            return 'calc(' . implode(' ' . static::sign . ' ', $values) . ')';
        } else {
            return static::operator . '(' . implode(', ', $values) . ')';
        }
    }
   
    /**
     * Clone this math value.
     *
     * @return static The cloned value
     */
       public function clone(): static
    {
        $clonedValues = [];
        foreach ($this->values->values as $value) {
            $clonedValues[] = clone $value;
        }
        return new static($clonedValues);
    }
}