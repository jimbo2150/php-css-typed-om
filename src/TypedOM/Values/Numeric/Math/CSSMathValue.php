<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MultiValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use InvalidArgumentException;

/**
 * Represents a CSS math value.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathValue
 */
abstract class CSSMathValue extends CSSNumericValue
{
	use CSSMathOperationTrait, MultiValueTrait;

	public function __construct(CSSNumericValue|CSSNumericArray|array $values)
	{
		if ($values instanceof CSSNumericArray) {
			$this->values = $values;
		} elseif ($values instanceof CSSNumericValue) {
			$this->values = new CSSNumericArray([$values]);
		} else {
			$this->values = new CSSNumericArray($values);
		}
	}

	protected function requireOperandCount(int $minimum, ?int $maximum = null): void
	{
		$count = count($this->values);
		if ($count < $minimum || ($maximum !== null && $count > $maximum)) {
			throw new InvalidArgumentException('Invalid number of operands for ' . static::class . '.');
		}
	}

	/**
	 * Convert to string representation.
	 *
	 * @return string The CSS string representation
	 */
    public function __toString(): string
    {
        return self::serializeMath($this, 0, true);
    }

    private static function serializeMath(CSSNumericValue $value, int $parentPrecedence, bool $root = false): string
    {
        if ($value instanceof CSSMathMin || $value instanceof CSSMathMax || $value instanceof CSSMathClamp) {
            $name = $value instanceof CSSMathMin ? 'min' : ($value instanceof CSSMathMax ? 'max' : 'clamp');
            $arguments = array_map(
                static fn (CSSNumericValue $argument): string => self::serializeMath($argument, 0),
                $value->values->values,
            );
            return $name . '(' . implode(', ', $arguments) . ')';
        }

        if ($value instanceof CSSMathNegate) {
            $inner = $value->values->values[0] ?? null;
            if ($inner === null) {
                return 'calc(0)';
            }
            $expression = '-' . self::serializeMath($inner, 3);
            return $root ? 'calc(' . $expression . ')' : $expression;
        }

        if ($value instanceof CSSMathInvert) {
            $inner = $value->values->values[0] ?? null;
            if ($inner === null) {
                return 'calc(1)';
            }
            $expression = '1 / ' . self::serializeMath($inner, 3);
            return $root ? 'calc(' . $expression . ')' : $expression;
        }

        if ($value instanceof CSSMathProduct) {
            $values = $value->values->values;
            if ($values !== [] && $values[0] instanceof CSSMathInvert) {
                $first = $values[0]->values->values[0] ?? null;
                $expression = $first === null ? '1' : '1 / ' . self::serializeMath($first, 3);
                $values = array_slice($values, 1);
            } else {
                $expression = '';
            }

            foreach ($values as $index => $child) {
                if ($child instanceof CSSMathInvert) {
                    $operand = $child->values->values[0] ?? null;
                    $expression .= ($expression === '' ? '' : ' / ') . ($operand === null ? '1' : self::serializeMath($operand, 3));
                    continue;
                }
                $expression .= ($expression === '' ? '' : ' * ') . self::serializeMath($child, 2);
            }
            $expression = $expression === '' ? '0' : $expression;
            if ($parentPrecedence > 2) {
                $expression = '(' . $expression . ')';
            }
            return $root ? 'calc(' . $expression . ')' : $expression;
        }

        if ($value instanceof CSSMathSum) {
            $expression = '';
            foreach ($value->values->values as $index => $child) {
                if ($index === 0) {
                    $expression = self::serializeMath($child, 1);
                    continue;
                }
                if ($child instanceof CSSMathNegate) {
                    $operand = $child->values->values[0] ?? null;
                    $expression .= ' - ' . ($operand === null ? '0' : self::serializeMath($operand, 1));
                } else {
                    $expression .= ' + ' . self::serializeMath($child, 1);
                }
            }
            $expression = $expression === '' ? '0' : $expression;
			if ($parentPrecedence >= 1) {
				$expression = '(' . $expression . ')';
			}
            return $root ? 'calc(' . $expression . ')' : $expression;
        }

        return (string) $value;
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
