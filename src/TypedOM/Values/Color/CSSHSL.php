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
	private CSSNumericValue|CSSKeywordValue $h;
	private CSSNumericValue|CSSKeywordValue $s;
	private CSSNumericValue|CSSKeywordValue $l;

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $h,
		CSSNumericValue|CSSKeywordValue|int|float $s,
		CSSNumericValue|CSSKeywordValue|int|float $l,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->setH($h);
		$this->setS($s);
		$this->setL($l);
		$this->setAlpha($alpha);
	}

	public function getH(): CSSNumericValue|CSSKeywordValue { return $this->h; }
	public function getS(): CSSNumericValue|CSSKeywordValue { return $this->s; }
	public function getL(): CSSNumericValue|CSSKeywordValue { return $this->l; }

	public function setH(CSSNumericValue|CSSKeywordValue|int|float $h): void
	{
		$this->h = self::normalizeAngleChannel($h);
	}

	public function setS(CSSNumericValue|CSSKeywordValue|int|float $s): void
	{
		$this->s = self::normalizePercentChannel($s, 1.0);
	}

	public function setL(CSSNumericValue|CSSKeywordValue|int|float $l): void
	{
		$this->l = self::normalizePercentChannel($l, 1.0);
	}

	public function toHSL(): CSSHSL
	{
		return $this->clone();
	}

	public function clone(): static
	{
		return new static($this->h->clone(), $this->s->clone(), $this->l->clone(), $this->alpha->clone());
	}

	public function __toString(): string
	{
		return 'hsl(' . $this->h . ' ' . $this->s . ' ' . $this->l . $this->formatAlphaSuffix() . ')';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'h' => $this->h,
			's' => $this->s,
			'l' => $this->l,
			default => parent::__get($name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($value instanceof CSSNumericValue || $value instanceof CSSKeywordValue || is_int($value) || is_float($value)) {
			if ($name === 'h') { $this->setH($value); return; }
			if ($name === 's') { $this->setS($value); return; }
			if ($name === 'l') { $this->setL($value); return; }
		}
		parent::__set($name, $value);
	}

	protected function toSrgbChannels(): array
	{
		$h = fmod(fmod(self::resolveAngleDegrees($this->h), 360.0) + 360.0, 360.0);
		$s = self::resolvePercentUnit($this->s);
		$l = self::resolvePercentUnit($this->l);

		$f = static function (float $n) use ($h, $s, $l): float {
			$k = fmod($n + $h / 30.0, 12.0);
			$a = $s * min($l, 1.0 - $l);
			return $l - $a * max(-1.0, min($k - 3.0, 9.0 - $k, 1.0));
		};

		return [$f(0.0), $f(8.0), $f(4.0), $this->alpha->clone()];
	}

	protected function toXyzD65(): array
	{
		[$r, $g, $b, $alpha] = $this->toSrgbChannels();
		[$x, $y, $z] = self::linearSrgbToXyzD65(self::srgbToLinear($r), self::srgbToLinear($g), self::srgbToLinear($b));

		return [$x, $y, $z, $alpha];
	}
}
