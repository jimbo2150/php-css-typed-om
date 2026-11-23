<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
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
			if (is_string($newValue) && CSSSyntaxScanner::hasControlCharacters($newValue)) {
				throw new InvalidArgumentException('CSSUnitValue::$value contains disallowed control characters.');
			}
			if (!is_numeric($newValue)) {
				throw new InvalidArgumentException('CSSUnitValue::$value must be a finite number.');
			}
			$float = (float) $newValue;
			if (!is_finite($float)) {
				throw new InvalidArgumentException('CSSUnitValue::$value must be a finite number.');
			}
			$this->value = $float;
		}
	}

	/**
	 * @param string|float|int $value Finite numeric magnitude
	 * @param string|CSSUnitEnum $unit CSS unit name/symbol or enum
	 * @throws InvalidArgumentException When $value is not finite/numeric or $unit is unsupported
	 */
	public function __construct(string|float|int $value, string|CSSUnitEnum $unit)
	{
		$this->value = $value;
		$this->setUnit($unit);
	}

	public function add(CSSNumericValue|int|float ...$values): CSSNumericValue
	{
		if ($values === []) {
			return parent::add(...$values);
		}

		$allSameUnit = true;
		$sum = $this->value;
		$normalized = [];
		$thisUnit = $this->unitObj;

		foreach ($values as $v) {
			$nv = self::toNumericValue($v);
			$normalized[] = $nv;
			if ($allSameUnit) {
				if ($nv instanceof self && $nv->unitObj === $thisUnit) {
					$sum += $nv->value;
				} else {
					$allSameUnit = false;
				}
			}
		}

		if ($allSameUnit) {
			if (!is_finite($sum)) {
				throw new InvalidArgumentException('CSS numeric addition overflowed to a non-finite value.');
			}
			return new self($sum, $thisUnit ?? $this->unit);
		}

		return parent::add(...$normalized);
	}

	public function sub(CSSNumericValue|int|float ...$values): CSSNumericValue
	{
		if ($values === []) {
			return parent::sub(...$values);
		}

		$allSameUnit = true;
		$sum = $this->value;
		$normalized = [];
		$thisUnit = $this->unitObj;

		foreach ($values as $v) {
			$nv = self::toNumericValue($v);
			$normalized[] = $nv;
			if ($allSameUnit) {
				if ($nv instanceof self && $nv->unitObj === $thisUnit) {
					$sum -= $nv->value;
				} else {
					$allSameUnit = false;
				}
			}
		}

		if ($allSameUnit) {
			if (!is_finite($sum)) {
				throw new InvalidArgumentException('CSS numeric subtraction overflowed to a non-finite value.');
			}
			return new self($sum, $thisUnit ?? $this->unit);
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
