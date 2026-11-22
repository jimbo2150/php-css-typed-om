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
	private CSSNumericValue|CSSKeywordValue $h;
	private CSSNumericValue|CSSKeywordValue $w;
	private CSSNumericValue|CSSKeywordValue $b;

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $h,
		CSSNumericValue|CSSKeywordValue|int|float $w,
		CSSNumericValue|CSSKeywordValue|int|float $b,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->setH($h);
		$this->setW($w);
		$this->setB($b);
		$this->setAlpha($alpha);
	}

	public function getH(): CSSNumericValue|CSSKeywordValue { return $this->h; }
	public function getW(): CSSNumericValue|CSSKeywordValue { return $this->w; }
	public function getB(): CSSNumericValue|CSSKeywordValue { return $this->b; }

	public function setH(CSSNumericValue|CSSKeywordValue|int|float $h): void
	{
		$this->h = self::normalizeAngleChannel($h);
	}

	public function setW(CSSNumericValue|CSSKeywordValue|int|float $w): void
	{
		$this->w = self::normalizePercentChannel($w, 1.0);
	}

	public function setB(CSSNumericValue|CSSKeywordValue|int|float $b): void
	{
		$this->b = self::normalizePercentChannel($b, 1.0);
	}

	public function toHWB(): CSSHWB
	{
		return $this->clone();
	}

	public function clone(): static
	{
		return new static($this->h->clone(), $this->w->clone(), $this->b->clone(), $this->alpha->clone());
	}

	public function __toString(): string
	{
		return 'hwb(' . $this->h . ' ' . $this->w . ' ' . $this->b . $this->formatAlphaSuffix() . ')';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'h' => $this->h,
			'w' => $this->w,
			'b' => $this->b,
			default => parent::__get($name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($value instanceof CSSNumericValue || $value instanceof CSSKeywordValue || is_int($value) || is_float($value)) {
			if ($name === 'h') { $this->setH($value); return; }
			if ($name === 'w') { $this->setW($value); return; }
			if ($name === 'b') { $this->setB($value); return; }
		}
		parent::__set($name, $value);
	}

	protected function toSrgbChannels(): array
	{
		$w = self::resolvePercentUnit($this->w);
		$b = self::resolvePercentUnit($this->b);
		if ($w + $b >= 1.0) {
			$gray = $w / ($w + $b);
			return [$gray, $gray, $gray, $this->alpha->clone()];
		}

		$hsl = new CSSHSL($this->h->clone(), 100.0, 50.0, $this->alpha->clone());
		[$r, $g, $bl] = $hsl->toSrgbChannels();
		$factor = 1.0 - $w - $b;

		return [
			$r * $factor + $w,
			$g * $factor + $w,
			$bl * $factor + $w,
			$this->alpha->clone(),
		];
	}

	protected function toXyzD65(): array
	{
		[$r, $g, $b, $alpha] = $this->toSrgbChannels();
		[$x, $y, $z] = self::linearSrgbToXyzD65(self::srgbToLinear($r), self::srgbToLinear($g), self::srgbToLinear($b));

		return [$x, $y, $z, $alpha];
	}
}
