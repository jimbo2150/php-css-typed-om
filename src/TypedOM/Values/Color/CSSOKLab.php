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
	private CSSNumericValue|CSSKeywordValue $l;
	private CSSNumericValue|CSSKeywordValue $a;
	private CSSNumericValue|CSSKeywordValue $b;

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $l,
		CSSNumericValue|CSSKeywordValue|int|float $a,
		CSSNumericValue|CSSKeywordValue|int|float $b,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->setL($l);
		$this->setA($a);
		$this->setB($b);
		$this->setAlpha($alpha);
	}

	public function getL(): CSSNumericValue|CSSKeywordValue { return $this->l; }
	public function getA(): CSSNumericValue|CSSKeywordValue { return $this->a; }
	public function getB(): CSSNumericValue|CSSKeywordValue { return $this->b; }

	public function setL(CSSNumericValue|CSSKeywordValue|int|float $l): void
	{
		$this->l = self::normalizePercentChannel($l, 100.0);
	}

	public function setA(CSSNumericValue|CSSKeywordValue|int|float $a): void
	{
		$this->a = self::normalizeNumberOrPercentChannel($a);
	}

	public function setB(CSSNumericValue|CSSKeywordValue|int|float $b): void
	{
		$this->b = self::normalizeNumberOrPercentChannel($b);
	}

	public function toOKLab(): CSSOKLab
	{
		return $this->clone();
	}

	public function clone(): static
	{
		return new static($this->l->clone(), $this->a->clone(), $this->b->clone(), $this->alpha->clone());
	}

	public function __toString(): string
	{
		return 'oklab(' . $this->l . ' ' . $this->a . ' ' . $this->b . $this->formatAlphaSuffix() . ')';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'l' => $this->l,
			'a' => $this->a,
			'b' => $this->b,
			default => parent::__get($name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($value instanceof CSSNumericValue || $value instanceof CSSKeywordValue || is_int($value) || is_float($value)) {
			if ($name === 'l') { $this->setL($value); return; }
			if ($name === 'a') { $this->setA($value); return; }
			if ($name === 'b') { $this->setB($value); return; }
		}
		parent::__set($name, $value);
	}

	protected function toXyzD65(): array
	{
		$l = self::resolvePercentUnit($this->l);
		$a = self::resolveLinearChannel($this->a, 0.4);
		$b = self::resolveLinearChannel($this->b, 0.4);
		[$x, $y, $z] = self::oklabToXyzD65($l, $a, $b);

		return [$x, $y, $z, $this->alpha->clone()];
	}
}
