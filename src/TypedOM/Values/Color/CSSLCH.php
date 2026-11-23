<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use ReflectionClass;

/**
 * Represents a CSS CIE `lch(...)` color value.
 */
final class CSSLCH extends CSSColorValue
{
	private CSSNumericValue|CSSKeywordValue $lValue;
	private CSSNumericValue|CSSKeywordValue $cValue;
	private CSSNumericValue|CSSKeywordValue $hValue;

	public CSSNumericValue|CSSKeywordValue $l {
		get => $this->lValue;
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->lValue = self::normalizePercentChannel($value, 1.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $c {
		get => $this->cValue;
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->cValue = self::normalizeNumberOrPercentChannel($value);
		}
	}

	public CSSNumericValue|CSSKeywordValue $h {
		get => $this->hValue;
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
		$this->lValue = self::normalizePercentChannel($l, 1.0);
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

	public function getL(): CSSNumericValue|CSSKeywordValue { return $this->lValue; }
	public function getC(): CSSNumericValue|CSSKeywordValue { return $this->cValue; }
	public function getH(): CSSNumericValue|CSSKeywordValue { return $this->hValue; }

	public function setL(CSSNumericValue|CSSKeywordValue|int|float $l): void
	{
		$this->lValue = self::normalizePercentChannel($l, 1.0);
	}

	public function setC(CSSNumericValue|CSSKeywordValue|int|float $c): void
	{
		$this->cValue = self::normalizeNumberOrPercentChannel($c);
	}

	public function setH(CSSNumericValue|CSSKeywordValue|int|float $h): void
	{
		$this->hValue = self::normalizeAngleChannel($h);
	}

	public function toLCH(): CSSLCH
	{
		return $this->clone();
	}

	public function toLab(): CSSLab
	{
		$c = self::resolveLinearChannel($this->cValue, 150.0);
		$hRad = deg2rad(self::resolveAngleDegrees($this->hValue));

		return CSSLab::fromValidated(
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
		return 'lch(' . $this->lValue . ' ' . $this->cValue . ' ' . $this->hValue . $this->formatAlphaSuffix() . ')';
	}

	protected function toXyzD65(): array
	{
		$l = self::resolveLinearChannel($this->lValue, 100.0);
		$c = self::resolveLinearChannel($this->cValue, 150.0);
		$hRad = deg2rad(self::resolveAngleDegrees($this->hValue));
		[$x50, $y50, $z50] = self::labToXyzD50($l, $c * cos($hRad), $c * sin($hRad));
		[$x, $y, $z] = self::xyzD50ToD65($x50, $y50, $z50);

		return [$x, $y, $z, $this->alphaValue->clone()];
	}
}
