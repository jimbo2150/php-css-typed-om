<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use ReflectionClass;

/**
 * Represents a CSS `oklch(...)` color value.
 */
final class CSSOKLCH extends CSSColorValue
{
	private CSSNumericValue|CSSKeywordValue $lValue;
	private CSSNumericValue|CSSKeywordValue $cValue;
	private CSSNumericValue|CSSKeywordValue $hValue;

	public CSSNumericValue|CSSKeywordValue $l {
		get => self::exposeComponent($this->lValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->lValue = self::normalizePercentChannel($value, 100.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $c {
		get => self::exposeComponent($this->cValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->cValue = self::normalizeNumberOrPercentChannel($value);
		}
	}

	public CSSNumericValue|CSSKeywordValue $h {
		get => self::exposeComponent($this->hValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->hValue = self::normalizeAngleChannel($value);
		}
	}

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $l,
		CSSNumericValue|CSSKeywordValue|int|float $c,
		CSSNumericValue|CSSKeywordValue|int|float $h,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->lValue = self::normalizePercentChannel($l, 100.0);
		$this->cValue = self::normalizeNumberOrPercentChannel($c);
		$this->hValue = self::normalizeAngleChannel($h);
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	/**
	 * @internal Fast factory for already-validated, caller-owned channel objects.
	 */
	public static function fromValidated(
		CSSNumericValue|CSSKeywordValue $l,
		CSSNumericValue|CSSKeywordValue $c,
		CSSNumericValue|CSSKeywordValue $h,
		CSSNumericValue|CSSKeywordValue $alpha,
	): self {
		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $instance */
		$instance = $reflection->newInstanceWithoutConstructor();
		$instance->lValue = $l;
		$instance->cValue = $c;
		$instance->hValue = $h;
		$instance->alphaValue = $alpha;

		return $instance;
	}

	public function getL(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->lValue); }
	public function getC(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->cValue); }
	public function getH(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->hValue); }

	public function setL(CSSNumericValue|CSSKeywordValue|int|float $l): void
	{
		$this->lValue = self::normalizePercentChannel($l, 100.0);
	}

	public function setC(CSSNumericValue|CSSKeywordValue|int|float $c): void
	{
		$this->cValue = self::normalizeNumberOrPercentChannel($c);
	}

	public function setH(CSSNumericValue|CSSKeywordValue|int|float $h): void
	{
		$this->hValue = self::normalizeAngleChannel($h);
	}

	public function toOKLCH(): CSSOKLCH
	{
		return $this->clone();
	}

	public function toOKLab(): CSSOKLab
	{
		if ($this->lValue instanceof CSSNumericValue && !$this->lValue instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Color conversion requires concrete numeric values.');
		}
		$c = self::resolveLinearChannel($this->cValue, 0.4);
		$hRad = deg2rad(self::resolveAngleDegrees($this->hValue));

		return CSSOKLab::fromValidated(
			$this->lValue->clone(),
			new CSSUnitValue($c * cos($hRad), 'number'),
			new CSSUnitValue($c * sin($hRad), 'number'),
			$this->alphaValue->clone(),
		);
	}

	public function __clone(): void
	{
		$this->lValue = $this->lValue->clone();
		$this->cValue = $this->cValue->clone();
		$this->hValue = $this->hValue->clone();
		$this->alphaValue = $this->alphaValue->clone();
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		return 'oklch(' . $this->lValue . ' ' . $this->cValue . ' ' . $this->hValue . $this->formatAlphaSuffix() . ')';
	}

	protected function toXyzD65(): array
	{
		$l = self::resolvePercentUnit($this->lValue);
		$c = self::resolveLinearChannel($this->cValue, 0.4);
		$hRad = deg2rad(self::resolveAngleDegrees($this->hValue));
		[$x, $y, $z] = self::oklabToXyzD65($l, $c * cos($hRad), $c * sin($hRad));

		return [$x, $y, $z, $this->alphaValue->clone()];
	}
}
