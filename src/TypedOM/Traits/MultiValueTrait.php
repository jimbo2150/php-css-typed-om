<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;

/**
 * Trait for simple value classes (color, keyword, image).
 * 
 * Provides common functionality for simple CSS values that don't need complex operations.
 */
trait MultiValueTrait
{
	/** @var CSSNumericArray */
    public protected(set) CSSNumericArray $values {
		get {
			return $this->values;
		}
	}

	public int $length {
		get => count($this->values);
	}

	public function add(CSSNumericValue $value): CSSMathSum {
		$this->values[] = $value;
	}

    /**
     * Convert to string representation.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }

    /**
     * Clone the value.
     */
    public function clone(): static
    {
        $clone = clone $this;
        return $clone;
    }
}