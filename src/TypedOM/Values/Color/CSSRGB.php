<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use ReflectionClass;

/**
 * Represents a CSS sRGB color (`rgb(...)`) using W3C CSS Color 5 Typed OM percentage channels (`0%..100%`).
 *
 * Bare numeric constructor/setter values in `0..1` map to `0%..100%`.
 */
final class CSSRGB extends CSSColorValue
{
	private CSSNumericValue|CSSKeywordValue $rValue;
	private CSSNumericValue|CSSKeywordValue $gValue;
	private CSSNumericValue|CSSKeywordValue $bValue;

	public CSSNumericValue|CSSKeywordValue $r {
		get => self::exposeComponent($this->rValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->rValue = self::normalizePercentChannel($value, 100.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $g {
		get => self::exposeComponent($this->gValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->gValue = self::normalizePercentChannel($value, 100.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $b {
		get => self::exposeComponent($this->bValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->bValue = self::normalizePercentChannel($value, 100.0);
		}
	}

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $r,
		CSSNumericValue|CSSKeywordValue|int|float $g,
		CSSNumericValue|CSSKeywordValue|int|float $b,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->rValue = self::normalizePercentChannel($r, 100.0);
		$this->gValue = self::normalizePercentChannel($g, 100.0);
		$this->bValue = self::normalizePercentChannel($b, 100.0);
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	/**
	 * @internal Fast factory for already-validated, caller-owned channel objects.
	 */
	public static function fromValidated(
		CSSNumericValue|CSSKeywordValue $r,
		CSSNumericValue|CSSKeywordValue $g,
		CSSNumericValue|CSSKeywordValue $b,
		CSSNumericValue|CSSKeywordValue $alpha,
	): self {
		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $instance */
		$instance = $reflection->newInstanceWithoutConstructor();
		$instance->rValue = $r;
		$instance->gValue = $g;
		$instance->bValue = $b;
		$instance->alphaValue = $alpha;

		return $instance;
	}

	public function getR(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->rValue); }
	public function getG(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->gValue); }
	public function getB(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->bValue); }

	public function setR(CSSNumericValue|CSSKeywordValue|int|float $r): void
	{
		$this->rValue = self::normalizePercentChannel($r, 100.0);
	}

	public function setG(CSSNumericValue|CSSKeywordValue|int|float $g): void
	{
		$this->gValue = self::normalizePercentChannel($g, 100.0);
	}

	public function setB(CSSNumericValue|CSSKeywordValue|int|float $b): void
	{
		$this->bValue = self::normalizePercentChannel($b, 100.0);
	}

	public function toRGB(): CSSRGB
	{
		return $this->clone();
	}

	public function __clone(): void
	{
		$this->rValue = $this->rValue->clone();
		$this->gValue = $this->gValue->clone();
		$this->bValue = $this->bValue->clone();
		$this->alphaValue = $this->alphaValue->clone();
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		return 'rgb(' . $this->rValue . ' ' . $this->gValue . ' ' . $this->bValue . $this->formatAlphaSuffix() . ')';
	}

	protected function toSrgbChannels(): array
	{
		return [
			self::resolvePercentUnit($this->rValue),
			self::resolvePercentUnit($this->gValue),
			self::resolvePercentUnit($this->bValue),
			$this->alphaValue->clone(),
		];
	}

	protected function toXyzD65(): array
	{
		$r = self::srgbToLinear(self::resolvePercentUnit($this->rValue));
		$g = self::srgbToLinear(self::resolvePercentUnit($this->gValue));
		$b = self::srgbToLinear(self::resolvePercentUnit($this->bValue));
		[$x, $y, $z] = self::linearSrgbToXyzD65($r, $g, $b);

		return [$x, $y, $z, $this->alphaValue->clone()];
	}
}
