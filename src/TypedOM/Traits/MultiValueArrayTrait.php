<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Trait for array-backed numeric collection classes (`CSSNumericArray`).
 */
trait MultiValueArrayTrait
{
	/** @var list<CSSNumericValue> */
	public protected(set) array $values = [];

	/**
	 * @param CSSNumericValue|CSSNumericArray|list<CSSNumericValue> $value The value to initialize with
	 */
	public function __construct(CSSNumericValue|CSSNumericArray|array $value)
	{
		if ($value instanceof CSSNumericArray) {
			$this->values = $value->values;
		} elseif ($value instanceof CSSNumericValue) {
			$this->values = [$value];
		} else {
			$values = [];
			foreach ($value as $entry) {
				if (!$entry instanceof CSSNumericValue) {
					throw new InvalidArgumentException('All values must be of type ' . CSSNumericValue::class);
				}
				$values[] = $entry;
			}
			$this->values = $values;
		}
	}

	public function __toString(): string
	{
		return implode(', ', $this->values);
	}
}
