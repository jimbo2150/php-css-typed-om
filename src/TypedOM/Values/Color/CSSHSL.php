<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use ReflectionClass;

/**
 * Represents a CSS `hsl(...)` color value.
 */
final class CSSHSL extends CSSColorValue
{
	private CSSNumericValue|CSSKeywordValue $hValue;
	private CSSNumericValue|CSSKeywordValue $sValue;
	private CSSNumericValue|CSSKeywordValue $lValue;

	public CSSNumericValue|CSSKeywordValue $h {
		get => self::exposeComponent($this->hValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->hValue = self::normalizeAngleChannel($value);
		}
	}

	public CSSNumericValue|CSSKeywordValue $s {
		get => self::exposeComponent($this->sValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->sValue = self::normalizePercentChannel($value, 1.0);
		}
	}

	public CSSNumericValue|CSSKeywordValue $l {
		get => self::exposeComponent($this->lValue);
		set (CSSNumericValue|CSSKeywordValue|int|float $value) {
			$this->lValue = self::normalizePercentChannel($value, 1.0);
		}
	}

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float $h,
		CSSNumericValue|CSSKeywordValue|int|float $s,
		CSSNumericValue|CSSKeywordValue|int|float $l,
		CSSNumericValue|CSSKeywordValue|int|float $alpha = 1.0,
	) {
		$this->hValue = self::normalizeAngleChannel($h);
		$this->sValue = self::normalizePercentChannel($s, 1.0);
		$this->lValue = self::normalizePercentChannel($l, 1.0);
		$this->alphaValue = self::normalizePercentChannel($alpha, 100.0);
	}

	/**
	 * @internal Fast factory for already-validated, caller-owned channel objects.
	 */
	public static function fromValidated(
		CSSNumericValue|CSSKeywordValue $h,
		CSSNumericValue|CSSKeywordValue $s,
		CSSNumericValue|CSSKeywordValue $l,
		CSSNumericValue|CSSKeywordValue $alpha,
	): self {
		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $instance */
		$instance = $reflection->newInstanceWithoutConstructor();
		$instance->hValue = $h;
		$instance->sValue = $s;
		$instance->lValue = $l;
		$instance->alphaValue = $alpha;

		return $instance;
	}

	public function getH(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->hValue); }
	public function getS(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->sValue); }
	public function getL(): CSSNumericValue|CSSKeywordValue { return self::exposeComponent($this->lValue); }

	public function setH(CSSNumericValue|CSSKeywordValue|int|float $h): void
	{
		$this->hValue = self::normalizeAngleChannel($h);
	}

	public function setS(CSSNumericValue|CSSKeywordValue|int|float $s): void
	{
		$this->sValue = self::normalizePercentChannel($s, 1.0);
	}

	public function setL(CSSNumericValue|CSSKeywordValue|int|float $l): void
	{
		$this->lValue = self::normalizePercentChannel($l, 1.0);
	}

	public function toHSL(bool $gamutMap = false): CSSHSL
	{
		if ($gamutMap && !$this->inGamut()) {
			/** @var CSSHSL */
			return CSSColorGamutMapper::map($this, 'hsl');
		}

		return $this->clone();
	}

	public function __clone(): void
	{
		$this->hValue = $this->hValue->clone();
		$this->sValue = $this->sValue->clone();
		$this->lValue = $this->lValue->clone();
		$this->alphaValue = $this->alphaValue->clone();
	}

	public function clone(): static
	{
		return clone $this;
	}

	/** @internal */
	public function getSpaceNameInternal(): string
	{
		return 'hsl';
	}

	/** @internal */
	public function getComponentValuesInternal(): array
	{
		return [$this->hValue, $this->sValue, $this->lValue, $this->alphaValue];
	}

	/** @internal */
	public function toBoundedCssTextInternal(?int $maxBytes): string
	{
		return $this->serializeFunctionChannelsBounded('hsl', [$this->hValue, $this->sValue, $this->lValue], $maxBytes);
	}

	protected function toSrgbChannels(): array
	{
		[$r, $g, $b] = self::hslToSrgbFloats(
			self::resolveAngleDegrees($this->hValue),
			self::resolvePercentUnit($this->sValue),
			self::resolvePercentUnit($this->lValue),
		);

		return [$r, $g, $b, $this->alphaValue->clone()];
	}

	protected function toXyzD65(): array
	{
		[$r, $g, $b] = self::hslToSrgbFloats(
			self::resolveAngleDegrees($this->hValue),
			self::resolvePercentUnit($this->sValue),
			self::resolvePercentUnit($this->lValue),
		);
		[$x, $y, $z] = self::linearSrgbToXyzD65(self::srgbToLinear($r), self::srgbToLinear($g), self::srgbToLinear($b));

		return [$x, $y, $z, $this->alphaValue->clone()];
	}
}
