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
	private CSSNumericValue|CSSKeywordValue $r;
	private CSSNumericValue|CSSKeywordValue $g;
	private CSSNumericValue|CSSKeywordValue $b;

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $r,
		CSSNumericValue|CSSKeywordValue|int|float $g,
		CSSNumericValue|CSSKeywordValue|int|float $b,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->setR($r);
		$this->setG($g);
		$this->setB($b);
		$this->setAlpha($alpha);
	}

	public function getR(): CSSNumericValue|CSSKeywordValue { return $this->r; }
	public function getG(): CSSNumericValue|CSSKeywordValue { return $this->g; }
	public function getB(): CSSNumericValue|CSSKeywordValue { return $this->b; }

	public function setR(CSSNumericValue|CSSKeywordValue|int|float $r): void
	{
		$this->r = self::normalizePercentChannel($r, 100.0);
	}

	public function setG(CSSNumericValue|CSSKeywordValue|int|float $g): void
	{
		$this->g = self::normalizePercentChannel($g, 100.0);
	}

	public function setB(CSSNumericValue|CSSKeywordValue|int|float $b): void
	{
		$this->b = self::normalizePercentChannel($b, 100.0);
	}

	public function toRGB(): CSSRGB
	{
		return $this->clone();
	}

	public function clone(): static
	{
		return new static($this->r->clone(), $this->g->clone(), $this->b->clone(), $this->alpha->clone());
	}

	public function __toString(): string
	{
		return 'rgb(' . $this->r . ' ' . $this->g . ' ' . $this->b . $this->formatAlphaSuffix() . ')';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'r' => $this->r,
			'g' => $this->g,
			'b' => $this->b,
			default => parent::__get($name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($value instanceof CSSNumericValue || $value instanceof CSSKeywordValue || is_int($value) || is_float($value)) {
			if ($name === 'r') { $this->setR($value); return; }
			if ($name === 'g') { $this->setG($value); return; }
			if ($name === 'b') { $this->setB($value); return; }
		}
		parent::__set($name, $value);
	}

	protected function toSrgbChannels(): array
	{
		return [
			self::resolvePercentUnit($this->r),
			self::resolvePercentUnit($this->g),
			self::resolvePercentUnit($this->b),
			$this->alpha->clone(),
		];
	}

	protected function toXyzD65(): array
	{
		$r = self::srgbToLinear(self::resolvePercentUnit($this->r));
		$g = self::srgbToLinear(self::resolvePercentUnit($this->g));
		$b = self::srgbToLinear(self::resolvePercentUnit($this->b));
		[$x, $y, $z] = self::linearSrgbToXyzD65($r, $g, $b);

		return [$x, $y, $z, $this->alpha->clone()];
	}
}
