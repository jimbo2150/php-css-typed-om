<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS `oklab(...)` color value.
 */
final class CSSOKLab extends CSSColorValue
{
	use ThreeChannelColorTrait;

	private const string SPACE_NAME = 'oklab';

	public CSSNumericValue|CSSKeywordValue $l {
		get => self::exposeComponent($this->c0Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c0Value = self::normalizePercentChannel($value, 100.0);
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
		$this->c0Value = self::normalizePercentChannel($l, 100.0);
		$this->c1Value = self::normalizeNumberOrPercentChannel($a);
		$this->c2Value = self::normalizeNumberOrPercentChannel($b);
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	public function getL(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c0Value); }
	public function getA(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c1Value); }
	public function getB(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c2Value); }

	public function setL(CSSNumericValue|CSSKeywordValue|int|float $l): void
	{
		$this->c0Value = self::normalizePercentChannel($l, 100.0);
	}

	public function setA(CSSNumericValue|CSSKeywordValue|int|float $a): void
	{
		$this->c1Value = self::normalizeNumberOrPercentChannel($a);
	}

	public function setB(CSSNumericValue|CSSKeywordValue|int|float $b): void
	{
		$this->c2Value = self::normalizeNumberOrPercentChannel($b);
	}

	public function toOKLab(): CSSOKLab
	{
		return $this->clone();
	}

	/**
	 * @internal
	 * @return array{0: float, 1: float, 2: float}
	 */
	public function toOklchFloatsInternal(): array
	{
		$l = self::resolvePercentUnit($this->c0Value);
		$a = self::resolveLinearChannel($this->c1Value, 0.4);
		$b = self::resolveLinearChannel($this->c2Value, 0.4);
		$c = hypot($a, $b);
		$h = $c < 1e-12 ? 0.0 : fmod(rad2deg(atan2($b, $a)) + 360.0, 360.0);

		return [$l, $c, $h === 0.0 ? 0.0 : $h];
	}

	public function toOKLCH(): CSSOKLCH
	{
		[$l, $c, $h] = $this->toOklchFloatsInternal();

		/** @var CSSOKLCH */
		return self::buildFromFloatsInternal('oklch', $l, $c, $h, $this->alphaValue->clone(), CSSColorConversionMeta::extractMissingMask($this));
	}

	protected function toXyzD65(): array
	{
		$l = self::resolvePercentUnit($this->c0Value);
		$a = self::resolveLinearChannel($this->c1Value, 0.4);
		$b = self::resolveLinearChannel($this->c2Value, 0.4);

		return self::oklabToXyzD65($l, $a, $b);
	}
}
