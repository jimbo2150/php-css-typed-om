<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents a CSS CIE `lch(...)` color value.
 */
final class CSSLCH extends CSSColorValue
{
	use ThreeChannelColorTrait;

	private const string SPACE_NAME = 'lch';

	public CSSNumericValue|CSSKeywordValue $l {
		get => self::exposeComponent($this->c0Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c0Value = self::normalizePercentChannel($value, 1.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $c {
		get => self::exposeComponent($this->c1Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c1Value = self::normalizeNumberOrPercentChannel($value);
		}
	}

	public CSSNumericValue|CSSKeywordValue $h {
		get => self::exposeComponent($this->c2Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c2Value = self::normalizeAngleChannel($value);
		}
	}

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $l,
		CSSNumericValue|CSSKeywordValue|int|float $c,
		CSSNumericValue|CSSKeywordValue|int|float $h,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->c0Value = self::normalizePercentChannel($l, 1.0);
		$this->c1Value = self::normalizeNumberOrPercentChannel($c);
		$this->c2Value = self::normalizeAngleChannel($h);
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	public function getL(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c0Value); }
	public function getC(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c1Value); }
	public function getH(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c2Value); }

	public function setL(CSSNumericValue|CSSKeywordValue|int|float $l): void
	{
		$this->c0Value = self::normalizePercentChannel($l, 1.0);
	}

	public function setC(CSSNumericValue|CSSKeywordValue|int|float $c): void
	{
		$this->c1Value = self::normalizeNumberOrPercentChannel($c);
	}

	public function setH(CSSNumericValue|CSSKeywordValue|int|float $h): void
	{
		$this->c2Value = self::normalizeAngleChannel($h);
	}

	public function toLCH(): CSSLCH
	{
		return $this->clone();
	}

	public function toLab(): CSSLab
	{
		if ($this->c0Value instanceof CSSNumericValue && !$this->c0Value instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Color conversion requires concrete numeric values.');
		}
		$c = self::resolveLinearChannel($this->c1Value, 150.0);
		$hRad = deg2rad(self::resolveAngleDegrees($this->c2Value));

		return CSSLab::fromValidated(
			$this->c0Value->clone(),
			new CSSUnitValue($c * cos($hRad), CSSUnitEnum::NUMBER),
			new CSSUnitValue($c * sin($hRad), CSSUnitEnum::NUMBER),
			$this->alphaValue->clone(),
		);
	}

	protected function toXyzD65(): array
	{
		$l = self::resolveLinearChannel($this->c0Value, 100.0);
		$c = self::resolveLinearChannel($this->c1Value, 150.0);
		$hRad = deg2rad(self::resolveAngleDegrees($this->c2Value));
		[$x50, $y50, $z50] = self::labToXyzD50($l, $c * cos($hRad), $c * sin($hRad));

		return self::xyzD50ToD65($x50, $y50, $z50);
	}
}
