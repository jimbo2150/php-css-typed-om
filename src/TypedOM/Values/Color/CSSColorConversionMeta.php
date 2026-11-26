<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Internal helper implementing CSS Color 4 §4.4.1 powerless component detection
 * and §12.2 missing (`none`) analogous component carry-forward.
 *
 * @internal
 */
final class CSSColorConversionMeta
{
	public const int BIT_RED = 1;
	public const int BIT_GREEN = 2;
	public const int BIT_BLUE = 4;
	public const int BIT_HUE = 8;
	public const int BIT_COLORFULNESS = 16;
	public const int BIT_LIGHTNESS = 32;
	public const int BIT_OPPONENT_A = 64;
	public const int BIT_OPPONENT_B = 128;

	private const float POWERLESS_HSL_EPSILON = 1e-7;
	private const float POWERLESS_HWB_EPSILON = 1e-7;
	private const float POWERLESS_LCH_EPSILON = 1e-6;

	/**
	 * Collects the bitmask of CSS Color 4 §12.2 analogous missing-component categories present on a color.
	 */
	public static function extractMissingMask(CSSColorValue $color): int
	{
		$space = $color->getSpaceNameInternal();
		$bits = CSSColorSpaceRegistry::ANALOGOUS_BITS[$space] ?? null;
		if ($bits === null) {
			return 0;
		}

		$components = $color->getComponentValuesInternal();
		$mask = 0;
		if ($bits[0] !== 0 && isset($components[0]) && $components[0] instanceof CSSKeywordValue) {
			$mask |= $bits[0];
		}
		if ($bits[1] !== 0 && isset($components[1]) && $components[1] instanceof CSSKeywordValue) {
			$mask |= $bits[1];
		}
		if ($bits[2] !== 0 && isset($components[2]) && $components[2] instanceof CSSKeywordValue) {
			$mask |= $bits[2];
		}

		return $mask;
	}

	public static function detectPowerlessMask(string $targetSpace, float $c0, float $c1, float $c2): int
	{
		return match ($targetSpace) {
			'hsl' => abs($c1) <= self::POWERLESS_HSL_EPSILON ? 1 : 0,
			'hwb' => ($c1 + $c2) >= (1.0 - self::POWERLESS_HWB_EPSILON) ? 1 : 0,
			'lch', 'oklch' => $c1 <= self::POWERLESS_LCH_EPSILON ? 4 : 0,
			default => 0,
		};
	}

	/**
	 * Builds channel objects (CSSUnitValue or `CSSKeywordValue('none')`) without allocating units for powerless/carried-missing slots.
	 *
	 * @return array{0: CSSNumericValue|CSSKeywordValue, 1: CSSNumericValue|CSSKeywordValue, 2: CSSNumericValue|CSSKeywordValue}
	 */
	public static function applyMissingAndPowerlessFloats(
		string $targetSpace,
		float $v0,
		CSSUnitEnum $u0,
		float $v1,
		CSSUnitEnum $u1,
		float $v2,
		CSSUnitEnum $u2,
		float $p0,
		float $p1,
		float $p2,
		int $sourceMissingMask,
	): array {
		$powerlessMask = self::detectPowerlessMask($targetSpace, $p0, $p1, $p2);
		$bits = CSSColorSpaceRegistry::ANALOGOUS_BITS[$targetSpace] ?? [0, 0, 0];

		return [
			(($powerlessMask & 1) !== 0 || ($sourceMissingMask & $bits[0]) !== 0)
				? CSSKeywordValue::fromValidated('none')
				: new CSSUnitValue($v0, $u0),
			(($powerlessMask & 2) !== 0 || ($sourceMissingMask & $bits[1]) !== 0)
				? CSSKeywordValue::fromValidated('none')
				: new CSSUnitValue($v1, $u1),
			(($powerlessMask & 4) !== 0 || ($sourceMissingMask & $bits[2]) !== 0)
				? CSSKeywordValue::fromValidated('none')
				: new CSSUnitValue($v2, $u2),
		];
	}

	/**
	 * Returns `[?float, ?float, ?float]` with `null` in any slot that is powerless or carried missing.
	 *
	 * @return array{0: ?float, 1: ?float, 2: ?float}
	 */
	public static function maskFloats(
		string $targetSpace,
		float $v0,
		float $v1,
		float $v2,
		float $p0,
		float $p1,
		float $p2,
		int $sourceMissingMask,
	): array {
		$powerlessMask = self::detectPowerlessMask($targetSpace, $p0, $p1, $p2);
		$bits = CSSColorSpaceRegistry::ANALOGOUS_BITS[$targetSpace] ?? [0, 0, 0];

		return [
			(($powerlessMask & 1) !== 0 || ($sourceMissingMask & $bits[0]) !== 0) ? null : $v0,
			(($powerlessMask & 2) !== 0 || ($sourceMissingMask & $bits[1]) !== 0) ? null : $v1,
			(($powerlessMask & 4) !== 0 || ($sourceMissingMask & $bits[2]) !== 0) ? null : $v2,
		];
	}
}
