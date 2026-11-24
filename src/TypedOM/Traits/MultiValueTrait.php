<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Trait for multi-operand CSS math expression classes (`CSSMathValue` hierarchy).
 */
trait MultiValueTrait
{
	public protected(set) CSSNumericArray $values;

	/** @var list<CSSNumericValue> */
	public array $inner_values {
		get => $this->values->values;
	}

	/**
	 * @param CSSNumericValue|CSSNumericArray|list<CSSNumericValue> $value The value to initialize with
	 */
	public function __construct(CSSNumericValue|CSSNumericArray|array $value)
	{
		if ($value instanceof CSSNumericArray) {
			$this->values = $value;
		} elseif ($value instanceof CSSNumericValue) {
			$this->values = new CSSNumericArray([$value]);
		} else {
			$this->values = new CSSNumericArray($value);
		}
	}

	public function __toString(): string
	{
		return (string) $this->values;
	}

	public function __clone(): void
	{
		$this->values = $this->values->clone();
	}

	public function clone(): static
	{
		return clone $this;
	}
}
