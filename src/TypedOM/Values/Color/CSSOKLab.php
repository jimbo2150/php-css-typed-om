<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use ReflectionClass;

/**
 * Represents a CSS `oklab(...)` color value.
 */
final class CSSOKLab extends CSSColorValue
{
	private CSSNumericValue|CSSKeywordValue $lValue;
	private CSSNumericValue|CSSKeywordValue $aValue;
	private CSSNumericValue|CSSKeywordValue $bValue;

	public CSSNumericValue|CSSKeywordValue $l {
		get => self::exposeComponent($this->lValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->lValue = self::normalizePercentChannel($value, 100.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $a {
		get => self::exposeComponent($this->aValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->aValue = self::normalizeNumberOrPercentChannel($value);
		}
	}

	public CSSNumericValue|CSSKeywordValue $b {
		get => self::exposeComponent($this->bValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->bValue = self::normalizeNumberOrPercentChannel($value);
		}
	}

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $l,
		CSSNumericValue|CSSKeywordValue|int|float $a,
		CSSNumericValue|CSSKeywordValue|int|float $b,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->lValue = self::normalizePercentChannel($l, 100.0);
		$this->aValue = self::normalizeNumberOrPercentChannel($a);
		$this->bValue = self::normalizeNumberOrPercentChannel($b);
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	/**
	 * @internal Fast factory for already-validated, caller-owned channel objects.
	 */
	public static function fromValidated(
		CSSNumericValue|CSSKeywordValue $l,
		CSSNumericValue|CSSKeywordValue $a,
		CSSNumericValue|CSSKeywordValue $b,
		CSSNumericValue|CSSKeywordValue $alpha,
	): self {
		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $instance */
		$instance = $reflection->newInstanceWithoutConstructor();
		$instance->lValue = $l;
		$instance->aValue = $a;
		$instance->bValue = $b;
		$instance->alphaValue = $alpha;

		return $instance;
	}

	public function getL(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->lValue); }
	public function getA(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->aValue); }
	public function getB(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->bValue); }

	public function setL(CSSNumericValue|CSSKeywordValue|int|float $l): void
	{
		$this->lValue = self::normalizePercentChannel($l, 100.0);
	}

	public function setA(CSSNumericValue|CSSKeywordValue|int|float $a): void
	{
		$this->aValue = self::normalizeNumberOrPercentChannel($a);
	}

	public function setB(CSSNumericValue|CSSKeywordValue|int|float $b): void
	{
		$this->bValue = self::normalizeNumberOrPercentChannel($b);
	}

	public function toOKLab(): CSSOKLab
	{
		return $this->clone();
	}

	public function __clone(): void
	{
		$this->lValue = $this->lValue->clone();
		$this->aValue = $this->aValue->clone();
		$this->bValue = $this->bValue->clone();
		$this->alphaValue = $this->alphaValue->clone();
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		return 'oklab(' . $this->lValue . ' ' . $this->aValue . ' ' . $this->bValue . $this->formatAlphaSuffix() . ')';
	}

	protected function toXyzD65(): array
	{
		$l = self::resolvePercentUnit($this->lValue);
		$a = self::resolveLinearChannel($this->aValue, 0.4);
		$b = self::resolveLinearChannel($this->bValue, 0.4);
		[$x, $y, $z] = self::oklabToXyzD65($l, $a, $b);

		return [$x, $y, $z, $this->alphaValue->clone()];
	}
}
