<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS CIE `lab(...)` color value.
 */
final class CSSLab extends CSSColorValue
{
	use ThreeChannelColorTrait;

	private const string SPACE_NAME = 'lab';

	public CSSNumericValue|CSSKeywordValue $l {
		get => self::exposeComponent($this->c0Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c0Value = self::normalizePercentChannel($value, 1.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $a {
		get => self::exposeComponent($this->c1Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c1Value = self::normalizeNumberOrPercentChannel($value);
		}
	}

	public CSSNumericValue|CSSKeywordValue $b {
		get => self::exposeComponent($this->c2Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c2Value = self::normalizeNumberOrPercentChannel($value);
		}
	}

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $l,
		CSSNumericValue|CSSKeywordValue|int|float $a,
		CSSNumericValue|CSSKeywordValue|int|float $b,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->c0Value = self::normalizePercentChannel($l, 1.0);
		$this->c1Value = self::normalizeNumberOrPercentChannel($a);
		$this->c2Value = self::normalizeNumberOrPercentChannel($b);
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	public function getL(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c0Value); }
	public function getA(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c1Value); }
	public function getB(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c2Value); }

	public function setL(CSSNumericValue|CSSKeywordValue|int|float $l): void
	{
		$this->c0Value = self::normalizePercentChannel($l, 1.0);
	}

	public function setA(CSSNumericValue|CSSKeywordValue|int|float $a): void
	{
		$this->c1Value = self::normalizeNumberOrPercentChannel($a);
	}

	public function setB(CSSNumericValue|CSSKeywordValue|int|float $b): void
	{
		$this->c2Value = self::normalizeNumberOrPercentChannel($b);
	}

	public function toLab(): CSSLab
	{
		return $this->clone();
	}

	public function toLCH(): CSSLCH
	{
		$l = self::resolveLinearChannel($this->c0Value, 100.0);
		$a = self::resolveLinearChannel($this->c1Value, 125.0);
		$b = self::resolveLinearChannel($this->c2Value, 125.0);
		$c = hypot($a, $b);
		$h = $c < 1e-12 ? 0.0 : fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);

		/** @var CSSLCH */
		return self::buildFromFloatsInternal('lch', $l, $c, $h, $this->alphaValue->clone(), CSSColorConversionMeta::extractMissingMask($this));
	}

	protected function toXyzD65(): array
	{
		$l = self::resolveLinearChannel($this->c0Value, 100.0);
		$a = self::resolveLinearChannel($this->c1Value, 125.0);
		$b = self::resolveLinearChannel($this->c2Value, 125.0);
		[$x50, $y50, $z50] = self::labToXyzD50($l, $a, $b);

		return self::xyzD50ToD65($x50, $y50, $z50);
	}
}
