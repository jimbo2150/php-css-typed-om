<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS sRGB color (`rgb(...)`) using W3C CSS Color 5 Typed OM percentage channels (`0%..100%`).
 *
 * Bare numeric constructor/setter values in `0..1` map to `0%..100%`.
 */
final class CSSRGB extends CSSColorValue
{
	use ThreeChannelColorTrait;

	private const string SPACE_NAME = 'rgb';

	public CSSNumericValue|CSSKeywordValue $r {
		get => self::exposeComponent($this->c0Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c0Value = self::normalizePercentChannel($value, 100.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $g {
		get => self::exposeComponent($this->c1Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c1Value = self::normalizePercentChannel($value, 100.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $b {
		get => self::exposeComponent($this->c2Value);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->c2Value = self::normalizePercentChannel($value, 100.0);
		}
	}

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $r,
		CSSNumericValue|CSSKeywordValue|int|float $g,
		CSSNumericValue|CSSKeywordValue|int|float $b,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->c0Value = self::normalizePercentChannel($r, 100.0);
		$this->c1Value = self::normalizePercentChannel($g, 100.0);
		$this->c2Value = self::normalizePercentChannel($b, 100.0);
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	public function getR(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c0Value); }
	public function getG(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c1Value); }
	public function getB(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->c2Value); }

	public function setR(CSSNumericValue|CSSKeywordValue|int|float $r): void
	{
		$this->c0Value = self::normalizePercentChannel($r, 100.0);
	}

	public function setG(CSSNumericValue|CSSKeywordValue|int|float $g): void
	{
		$this->c1Value = self::normalizePercentChannel($g, 100.0);
	}

	public function setB(CSSNumericValue|CSSKeywordValue|int|float $b): void
	{
		$this->c2Value = self::normalizePercentChannel($b, 100.0);
	}

	public function toRGB(bool $gamutMap = false): CSSRGB
	{
		if ($gamutMap) {
			/** @var CSSRGB */
			return CSSColorGamutMapper::map($this, 'rgb');
		}

		return $this->clone();
	}

	protected function toSrgbChannels(): array
	{
		return [
			self::resolvePercentUnit($this->c0Value),
			self::resolvePercentUnit($this->c1Value),
			self::resolvePercentUnit($this->c2Value),
		];
	}

	protected function toXyzD65(): array
	{
		$r = self::srgbToLinear(self::resolvePercentUnit($this->c0Value));
		$g = self::srgbToLinear(self::resolvePercentUnit($this->c1Value));
		$b = self::srgbToLinear(self::resolvePercentUnit($this->c2Value));

		return self::linearSrgbToXyzD65($r, $g, $b);
	}
}
