<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Trait for simple value classes (color, keyword, image).
 * 
 * Provides common functionality for simple CSS values that don't need complex operations.
 */
trait MultiValueArrayTrait
{
	/** @var array<CSSNumericValue> */
    public protected(set) array $values = [] {
		get {
			return $this->values;
		}
	}

	public int $length {
		get => count($this->values);
	}

	/**
	 * Summary of __construct
	 * @param CSSNumericValue|CSSNumericArray<CSSNumericValue>|array<CSSNumericArray> $value
	 */
	public function __construct(CSSNumericValue|CSSNumericArray|array $value) {
		if($value instanceof CSSNumericArray) {
			$this->values[] = $value;
		} else if ($value instanceof CSSNumericArray) {
			$this->values = $value->values;
		} else {
			$values = $this->values;
			foreach($value as $entry) {
				if(!($entry instanceof CSSNumericValue)) {
					throw new InvalidArgumentException('All values must be of type ' . CSSNumericValue::class);
				}
				$values[] = $entry;
			}
			$this->values = $values;
		}
	}

    /**
     * Convert to string representation.
     */
    public function __toString(): string
    {
        return (string) $this->value;
    }
}