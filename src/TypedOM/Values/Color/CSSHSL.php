<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS `hsl(...)` color value.
 */
final class CSSHSL extends CSSColorValue
{
	use ThreeChannelColorTrait;

	private const string SPACE_NAME = 'hsl';

	public CSSNumericValue|CSSKeywordValue $h {
		get => self::exposeComponent($this->c0Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c0Value = self::normalizeAngleChannel($value);
		}
	}

	public CSSNumericValue|CSSKeywordValue $s {
		get => self::exposeComponent($this->c1Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c1Value = self::normalizePercentChannel($value, 1.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $l {
		get => self::exposeComponent($this->c2Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c2Value = self::normalizePercentChannel($value, 1.0);
		}
	}

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $h,
		CSSNumericValue|CSSKeywordValue|int|float $s,
		CSSNumericValue|CSSKeywordValue|int|float $l,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->c0Value = self::normalizeAngleChannel($h);
		$this->c1Value = self::normalizePercentChannel($s, 1.0);
		$this->c2Value = self::normalizePercentChannel($l, 1.0);
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	public function getH(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c0Value); }
	public function getS(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c1Value); }
	public function getL(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c2Value); }

	public function setH(CSSNumericValue|CSSKeywordValue|int|float $h): void
	{
		$this->c0Value = self::normalizeAngleChannel($h);
	}

	public function setS(CSSNumericValue|CSSKeywordValue|int|float $s): void
	{
		$this->c1Value = self::normalizePercentChannel($s, 1.0);
	}

	public function setL(CSSNumericValue|CSSKeywordValue|int|float $l): void
	{
		$this->c2Value = self::normalizePercentChannel($l, 1.0);
	}

	public function toHSL(bool $gamutMap = false): CSSHSL
	{
		if ($gamutMap) {
			/** @var CSSHSL */
			return CSSColorGamutMapper::map($this, 'hsl');
		}

		return $this->clone();
	}

	protected function toSrgbChannels(): array
	{
		return self::hslToSrgbFloats(
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
