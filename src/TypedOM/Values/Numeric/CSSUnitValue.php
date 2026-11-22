<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use InvalidArgumentException;
use Stringable;

/**
 * Represents a CSS value with a unit (e.g., `10px`, `50%`, `2em`).
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSUnitValue
 */
class CSSUnitValue extends CSSNumericValue implements Stringable
{
	public mixed $value = null {
		set (mixed $newValue) {
			if (!is_numeric($newValue) || !is_finite((float) $newValue)) {
				throw new InvalidArgumentException('CSSUnitValue::$value must be a finite number.');
			}
			$this->value = (float) $newValue;
		}
	}

	/**
	 * @param string|float|int $value Finite numeric magnitude
	 * @param string|CSSUnitEnum $unit CSS unit name/symbol or enum
	 * @throws InvalidArgumentException When $value is not finite/numeric or $unit is unsupported
	 */
	public function __construct(string|float|int $value, string|CSSUnitEnum $unit)
	{
		if (!is_numeric($value)) {
			throw new InvalidArgumentException('Invalid numeric value for CSSUnitValue.');
		}
		$numeric = (float) $value;
		if (!is_finite($numeric)) {
			throw new InvalidArgumentException('CSSUnitValue must be finite.');
		}
		$this->setValue($numeric);
		$this->setUnit($unit);
	}

	public function add(CSSNumericValue|int|float ...$values): CSSNumericValue
	{
		$normalized = array_map(static fn (CSSNumericValue|int|float $v): CSSNumericValue => self::toNumericValue($v), $values);
		if ($normalized !== [] && array_all($normalized, fn (CSSNumericValue $value): bool =>
			$value instanceof self && $this->unit === $value->unit)) {
			$total = $this->value + array_sum(array_map(static fn (self $value): float => $value->value, $normalized));
			if (!is_finite($total)) {
				throw new InvalidArgumentException('CSS numeric addition overflowed to a non-finite value.');
			}
			return new self($total, $this->unitObj ?? $this->unit);
		}
		return parent::add(...$normalized);
	}

	public function sub(CSSNumericValue|int|float ...$values): CSSNumericValue
	{
		$normalized = array_map(static fn (CSSNumericValue|int|float $v): CSSNumericValue => self::toNumericValue($v), $values);
		if ($normalized !== [] && array_all($normalized, fn (CSSNumericValue $value): bool =>
			$value instanceof self && $this->unit === $value->unit)) {
			$total = $this->value - array_sum(array_map(static fn (self $value): float => $value->value, $normalized));
			if (!is_finite($total)) {
				throw new InvalidArgumentException('CSS numeric subtraction overflowed to a non-finite value.');
			}
			return new self($total, $this->unitObj ?? $this->unit);
		}
		return parent::sub(...$normalized);
	}

	public function __toString(): string
	{
		$symbol = $this->unitObj?->toString() ?? '';
		return "{$this->value}{$symbol}";
	}

	public function clone(): static
	{
		return new static($this->value, $this->unitObj ?? $this->unit);
	}
}
