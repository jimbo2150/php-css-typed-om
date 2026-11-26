<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use LogicException;
use Stringable;

/**
 * Represents a CSS value with a unit (e.g., `10px`, `50%`, `2em`).
 *
 * @phpstan-consistent-constructor
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSUnitValue
 */
class CSSUnitValue extends CSSNumericValue implements Stringable
{
	protected ?CSSUnitEnum $unitObj = null;

	public string $unit {
		get => $this->unitObj ? $this->unitObj->toUnitName() : '';
	}

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
	 * @phpstan-consistent-constructor
	 * @param string|float|int $value Finite numeric magnitude
	 * @param string|CSSUnitEnum $unit CSS unit name/symbol or enum
	 * @throws InvalidArgumentException When $value is not finite/numeric or $unit is unsupported
	 */
	public function __construct(string|float|int $value, string|CSSUnitEnum $unit)
	{
		$this->value = $value;
		$this->setUnit($unit);
	}

	/** @internal */
	public function getUnitObjInternal(): ?CSSUnitEnum
	{
		return $this->unitObj;
	}

	protected function setUnit(string|CSSUnitEnum $unit): void
	{
		if (is_string($unit)) {
			$resolved = CSSUnitEnum::tryFromUnitName($unit);
			if ($resolved === null) {
				$symbol = CSS::translateUnit(strtolower($unit));
				throw new InvalidArgumentException('Unsupported CSS unit: ' . $symbol);
			}
			$unit = $resolved;
		}
		$this->unitObj = $unit;
	}

	/**
	 * Get the category name of the unit.
	 *
	 * @throws LogicException If no unit is set
	 */
	public function unitType(): ?string
	{
		if (!$this->unitObj) {
			throw new LogicException('No unit set.');
		}

		return $this->unitObj->type()?->value;
	}

	public function add(CSSNumericValue|int|float ...$values): CSSNumericValue
	{
		return $this->addOrSub(1, ...$values);
	}

	public function sub(CSSNumericValue|int|float ...$values): CSSNumericValue
	{
		return $this->addOrSub(-1, ...$values);
	}

	private function addOrSub(int $sign, CSSNumericValue|int|float ...$values): CSSNumericValue
	{
		if ($values === []) {
			return $sign > 0 ? parent::add(...$values) : parent::sub(...$values);
		}
		if (count($values) >= self::MAX_MATH_NODES) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
		}

		$allSameUnit = true;
		$sum = $this->value;
		$thisUnit = $this->unitObj;

		foreach ($values as $v) {
			if ($v instanceof self && $v->unitObj === $thisUnit) {
				$sum += $sign * $v->value;
				continue;
			}
			if (!$v instanceof self && $thisUnit === CSSUnitEnum::NUMBER) {
				$float = (float) $v;
				if (!is_finite($float)) {
					throw new InvalidArgumentException('CSS numeric operand must be finite.');
				}
				$sum += $sign * $float;
				continue;
			}
			$allSameUnit = false;
			break;
		}

		if ($allSameUnit) {
			if (!is_finite($sum)) {
				throw new InvalidArgumentException(
					$sign > 0
						? 'CSS numeric addition overflowed to a non-finite value.'
						: 'CSS numeric subtraction overflowed to a non-finite value.',
				);
			}

			return new self($sum, $thisUnit ?? $this->unit);
		}

		$normalized = [];
		foreach ($values as $v) {
			$normalized[] = self::toNumericValue($v);
		}

		return $sign > 0 ? parent::add(...$normalized) : parent::sub(...$normalized);
	}

	public function mul(CSSNumericValue|int|float ...$values): CSSNumericValue
	{
		if ($values === []) {
			return parent::mul(...$values);
		}
		if (count($values) >= self::MAX_MATH_NODES) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
		}

		$canCollapse = true;
		$product = $this->value;
		$resultUnit = $this->unitObj ?? CSSUnitEnum::NUMBER;
		$hasDimension = $resultUnit !== CSSUnitEnum::NUMBER;
		$normalized = [];

		foreach ($values as $v) {
			$nv = self::toNumericValue($v);
			$normalized[] = $nv;
			if ($canCollapse) {
				if (!$nv instanceof self) {
					$canCollapse = false;
					continue;
				}
				$nvUnit = $nv->unitObj ?? CSSUnitEnum::NUMBER;
				if ($nvUnit !== CSSUnitEnum::NUMBER) {
					if ($hasDimension) {
						$canCollapse = false;
						continue;
					}
					$hasDimension = true;
					$resultUnit = $nvUnit;
				}
				$product *= $nv->value;
			}
		}

		if ($canCollapse) {
			if (!is_finite($product)) {
				throw new InvalidArgumentException('CSS numeric multiplication overflowed to a non-finite value.');
			}
			return new self($product, $resultUnit);
		}

		return parent::mul(...$normalized);
	}

	public function div(CSSNumericValue|int|float ...$values): CSSNumericValue
	{
		if ($values === []) {
			return parent::div(...$values);
		}
		if (1 + (2 * count($values)) > self::MAX_MATH_NODES) {
			throw new CSSParserLimitException(sprintf('Maximum CSS math expression node count of %d exceeded.', self::MAX_MATH_NODES));
		}

		$allNumberDivisors = true;
		$normalized = [];

		foreach ($values as $v) {
			$nv = self::toNumericValue($v);
			$normalized[] = $nv;
			if ($allNumberDivisors && (!$nv instanceof self || ($nv->unitObj ?? CSSUnitEnum::NUMBER) !== CSSUnitEnum::NUMBER)) {
				$allNumberDivisors = false;
			}
		}

		if ($allNumberDivisors) {
			$quotient = $this->value;
			foreach ($normalized as $nv) {
				/** @var self $nv */
				if ($nv->value === 0.0) {
					throw new InvalidArgumentException('Cannot divide CSS numeric value by zero.');
				}
				$quotient = fdiv($quotient, $nv->value);
			}
			if (!is_finite($quotient)) {
				throw new InvalidArgumentException('CSS numeric division overflowed to a non-finite value.');
			}
			return new self($quotient, $this->unitObj ?? $this->unit);
		}

		return parent::div(...$normalized);
	}

	public function getNumericCategory(): string
	{
		return $this->unitObj?->type()->value ?? 'number';
	}

	public function to(string $unit, ?CSSContext $context = null): self
	{
		/** @var self $converted */
		$converted = parent::to($unit, $context);

		return $converted;
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
