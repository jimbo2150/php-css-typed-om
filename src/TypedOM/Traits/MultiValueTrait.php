<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

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

	/** @var array<CSSNumericValue> */
	   public private(set) array $inner_values {
		get {
			return $this->values->values;
		}
		set {

		}
	}

	/**
	 * Summary of __construct
	 * @param CSSNumericValue|CSSNumericArray<CSSNumericValue>|array<CSSNumericArray> $value
	 */
	public function __construct(CSSNumericValue|CSSNumericArray|array $value) {
		if($value instanceof CSSNumericArray) {
			$this->values = $value;
		} else if($value instanceof CSSNumericValue) {
			$this->values = new CSSNumericArray([$value]);
		} else {
			$this->values = new CSSNumericArray($value);
		}
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