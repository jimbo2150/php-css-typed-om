<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS `hwb(...)` color value.
 */
final class CSSHWB extends CSSColorValue
{
	use ThreeChannelColorTrait;

	private const string SPACE_NAME = 'hwb';

	public CSSNumericValue|CSSKeywordValue $h {
		get => self::exposeComponent($this->c0Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c0Value = self::normalizeAngleChannel($value);
		}
	}

	public CSSNumericValue|CSSKeywordValue $w {
		get => self::exposeComponent($this->c1Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c1Value = self::normalizePercentChannel($value, 1.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $b {
		get => self::exposeComponent($this->c2Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c2Value = self::normalizePercentChannel($value, 1.0);
		}
	}

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $h,
		CSSNumericValue|CSSKeywordValue|int|float $w,
		CSSNumericValue|CSSKeywordValue|int|float $b,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->c0Value = self::normalizeAngleChannel($h);
		$this->c1Value = self::normalizePercentChannel($w, 1.0);
		$this->c2Value = self::normalizePercentChannel($b, 1.0);
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	public function getH(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c0Value); }
	public function getW(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c1Value); }
	public function getB(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c2Value); }

	public function setH(CSSNumericValue|CSSKeywordValue|int|float $h): void
	{
		$this->c0Value = self::normalizeAngleChannel($h);
	}

	public function setW(CSSNumericValue|CSSKeywordValue|int|float $w): void
	{
		$this->c1Value = self::normalizePercentChannel($w, 1.0);
	}

	public function setB(CSSNumericValue|CSSKeywordValue|int|float $b): void
	{
		$this->c2Value = self::normalizePercentChannel($b, 1.0);
	}

	public function toHWB(bool $gamutMap = false): CSSHWB
	{
		if ($gamutMap) {
			/** @var CSSHWB */
			return CSSColorGamutMapper::map($this, 'hwb');
		}

		return $this->clone();
	}

	protected function toSrgbChannels(): array
	{
		return self::hwbToSrgbFloatsInternal(
			self::resolveAngleDegrees($this->c0Value),
			self::resolvePercentUnit($this->c1Value),
			self::resolvePercentUnit($this->c2Value),
		);
	}

	protected function toXyzD65(): array
	{
		[$r, $g, $b] = $this->toSrgbChannels();

		return self::linearSrgbToXyzD65(self::srgbToLinear($r), self::srgbToLinear($g), self::srgbToLinear($b));
	}
}
