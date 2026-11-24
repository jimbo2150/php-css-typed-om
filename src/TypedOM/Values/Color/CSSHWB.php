<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use ReflectionClass;

/**
 * Represents a CSS `hwb(...)` color value.
 */
final class CSSHWB extends CSSColorValue
{
	private CSSNumericValue|CSSKeywordValue $hValue;
	private CSSNumericValue|CSSKeywordValue $wValue;
	private CSSNumericValue|CSSKeywordValue $bValue;

	public CSSNumericValue|CSSKeywordValue $h {
		get => self::exposeComponent($this->hValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->hValue = self::normalizeAngleChannel($value);
		}
	}

	public CSSNumericValue|CSSKeywordValue $w {
		get => self::exposeComponent($this->wValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->wValue = self::normalizePercentChannel($value, 1.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $b {
		get => self::exposeComponent($this->bValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->bValue = self::normalizePercentChannel($value, 1.0);
		}
	}

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $h,
		CSSNumericValue|CSSKeywordValue|int|float $w,
		CSSNumericValue|CSSKeywordValue|int|float $b,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->hValue = self::normalizeAngleChannel($h);
		$this->wValue = self::normalizePercentChannel($w, 1.0);
		$this->bValue = self::normalizePercentChannel($b, 1.0);
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	/**
	 * @internal Fast factory for already-validated, caller-owned channel objects.
	 */
	public static function fromValidated(
		CSSNumericValue|CSSKeywordValue $h,
		CSSNumericValue|CSSKeywordValue $w,
		CSSNumericValue|CSSKeywordValue $b,
		CSSNumericValue|CSSKeywordValue $alpha,
	): self {
		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $instance */
		$instance = $reflection->newInstanceWithoutConstructor();
		$instance->hValue = $h;
		$instance->wValue = $w;
		$instance->bValue = $b;
		$instance->alphaValue = $alpha;

		return $instance;
	}

	public function getH(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->hValue); }
	public function getW(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->wValue); }
	public function getB(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->bValue); }

	public function setH(CSSNumericValue|CSSKeywordValue|int|float $h): void
	{
		$this->hValue = self::normalizeAngleChannel($h);
	}

	public function setW(CSSNumericValue|CSSKeywordValue|int|float $w): void
	{
		$this->wValue = self::normalizePercentChannel($w, 1.0);
	}

	public function setB(CSSNumericValue|CSSKeywordValue|int|float $b): void
	{
		$this->bValue = self::normalizePercentChannel($b, 1.0);
	}

	public function toHWB(): CSSHWB
	{
		return $this->clone();
	}

	public function __clone(): void
	{
		$this->hValue = $this->hValue->clone();
		$this->wValue = $this->wValue->clone();
		$this->bValue = $this->bValue->clone();
		$this->alphaValue = $this->alphaValue->clone();
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		return 'hwb(' . $this->hValue . ' ' . $this->wValue . ' ' . $this->bValue . $this->formatAlphaSuffix() . ')';
	}

	protected function toSrgbChannels(): array
	{
		[$r, $g, $b] = $this->toSrgbFloats();

		return [$r, $g, $b, $this->alphaValue->clone()];
	}

	protected function toXyzD65(): array
	{
		[$r, $g, $b] = $this->toSrgbFloats();
		[$x, $y, $z] = self::linearSrgbToXyzD65(self::srgbToLinear($r), self::srgbToLinear($g), self::srgbToLinear($b));

		return [$x, $y, $z, $this->alphaValue->clone()];
	}

	/** @return array{float, float, float} */
	private function toSrgbFloats(): array
	{
		$h = self::resolveAngleDegrees($this->hValue);
		$w = self::resolvePercentUnit($this->wValue);
		$b = self::resolvePercentUnit($this->bValue);
		if ($w + $b >= 1.0) {
			$gray = $w / ($w + $b);
			return [$gray, $gray, $gray];
		}

		[$r, $g, $bl] = self::hslToSrgbFloats($h, 1.0, 0.5);
		$factor = 1.0 - $w - $b;

		return [
			$r * $factor + $w,
			$g * $factor + $w,
			$bl * $factor + $w,
		];
	}
}
