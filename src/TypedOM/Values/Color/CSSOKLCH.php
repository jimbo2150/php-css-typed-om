<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents a CSS `oklch(...)` color value.
 */
final class CSSOKLCH extends CSSColorValue
{
	private CSSNumericValue|CSSKeywordValue $l;
	private CSSNumericValue|CSSKeywordValue $c;
	private CSSNumericValue|CSSKeywordValue $h;

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $l,
		CSSNumericValue|CSSKeywordValue|int|float $c,
		CSSNumericValue|CSSKeywordValue|int|float $h,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->setL($l);
		$this->setC($c);
		$this->setH($h);
		$this->setAlpha($alpha);
	}

	public function getL(): CSSNumericValue|CSSKeywordValue { return $this->l; }
	public function getC(): CSSNumericValue|CSSKeywordValue { return $this->c; }
	public function getH(): CSSNumericValue|CSSKeywordValue { return $this->h; }

	public function setL(CSSNumericValue|CSSKeywordValue|int|float $l): void
	{
		$this->l = self::normalizePercentChannel($l, 100.0);
	}

	public function setC(CSSNumericValue|CSSKeywordValue|int|float $c): void
	{
		$this->c = self::normalizeNumberOrPercentChannel($c);
	}

	public function setH(CSSNumericValue|CSSKeywordValue|int|float $h): void
	{
		$this->h = self::normalizeAngleChannel($h);
	}

	public function toOKLCH(): CSSOKLCH
	{
		return $this->clone();
	}

	public function toOKLab(): CSSOKLab
	{
		$c = self::resolveLinearChannel($this->c, 0.4);
		$hRad = deg2rad(self::resolveAngleDegrees($this->h));

		return new CSSOKLab(
			$this->l->clone(),
			new CSSUnitValue($c * cos($hRad), 'number'),
			new CSSUnitValue($c * sin($hRad), 'number'),
			$this->alpha->clone(),
		);
	}

	public function clone(): static
	{
		return new static($this->l->clone(), $this->c->clone(), $this->h->clone(), $this->alpha->clone());
	}

	public function __toString(): string
	{
		return 'oklch(' . $this->l . ' ' . $this->c . ' ' . $this->h . $this->formatAlphaSuffix() . ')';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'l' => $this->l,
			'c' => $this->c,
			'h' => $this->h,
			default => parent::__get($name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($value instanceof CSSNumericValue || $value instanceof CSSKeywordValue || is_int($value) || is_float($value)) {
			if ($name === 'l') { $this->setL($value); return; }
			if ($name === 'c') { $this->setC($value); return; }
			if ($name === 'h') { $this->setH($value); return; }
		}
		parent::__set($name, $value);
	}

	protected function toXyzD65(): array
	{
		return $this->toOKLab()->toXyzD65();
	}
}
