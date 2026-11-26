<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Internal helper implementing CSS Color 4 §4.4.1 powerless component detection
 * and §12.2 missing (`none`) analogous component carry-forward.
 *
 * @internal
 */
final class CSSColorConversionMeta
{
	private const float POWERLESS_HSL_EPSILON = 1e-7;
	private const float POWERLESS_HWB_EPSILON = 1e-7;
	private const float POWERLESS_LCH_EPSILON = 1e-6;

	/**
	 * Map of canonical space name -> list of 3 analogous category names (null = no analogous category).
	 *
	 * @var array<string, array{0: ?string, 1: ?string, 2: ?string}>
	 */
	private const array ANALOGOUS_CATEGORIES = [
		'rgb' => ['red', 'green', 'blue'],
		'srgb' => ['red', 'green', 'blue'],
		'srgb-linear' => ['red', 'green', 'blue'],
		'display-p3' => ['red', 'green', 'blue'],
		'a98-rgb' => ['red', 'green', 'blue'],
		'prophoto-rgb' => ['red', 'green', 'blue'],
		'rec2020' => ['red', 'green', 'blue'],
		'hsl' => ['hue', 'colorfulness', 'lightness'],
		'hwb' => ['hue', null, null],
		'lab' => ['lightness', 'opponent-a', 'opponent-b'],
		'lch' => ['lightness', 'colorfulness', 'hue'],
		'oklab' => ['lightness', 'opponent-a', 'opponent-b'],
		'oklch' => ['lightness', 'colorfulness', 'hue'],
		'xyz' => [null, null, null],
		'xyz-d50' => [null, null, null],
		'xyz-d65' => [null, null, null],
	];

	/**
	 * Collects the set of CSS Color 4 §12.2 analogous missing-component categories present on a color.
	 *
	 * @return array<string, true>
	 */
	public static function extractMissingCategories(CSSColorValue $color): array
	{
		$space = $color->getSpaceNameInternal();
		$categories = self::ANALOGOUS_CATEGORIES[$space] ?? null;
		if ($categories === null) {
			return [];
		}

		$components = $color->getComponentValuesInternal();
		$missing = [];
		for ($i = 0; $i < 3; $i++) {
			$cat = $categories[$i];
			if ($cat !== null && isset($components[$i]) && $components[$i] instanceof CSSKeywordValue) {
				$missing[$cat] = true;
			}
		}

		return $missing;
	}

	/**
	 * Returns which channel indices (0..2) are powerless in $targetSpace for the given numeric channels.
	 *
	 * Per CSS Color 4 §4.4.1 & §7.1:
	 * - HSL: only hue (0) is powerless when saturation `s` (normalized 0..1) is ~0 (`abs(s) <= 1e-7`).
	 *        Saturation `s` is NOT powerless at `l = 0%` or `l = 100%`.
	 * - HWB: hue (0) is powerless when `w + b >= 1 - 1e-7` (normalized 0..1).
	 * - LCH / OKLCH: hue (2) is powerless when `c <= 1e-6`.
	 *
	 * @param array{0: float, 1: float, 2: float} $normalizedChannels Channels in standard float scale
	 * @return array<int, true>
	 */
	public static function detectPowerlessIndices(string $targetSpace, array $normalizedChannels): array
	{
		return match ($targetSpace) {
			'hsl' => abs($normalizedChannels[1]) <= self::POWERLESS_HSL_EPSILON ? [0 => true] : [],
			'hwb' => ($normalizedChannels[1] + $normalizedChannels[2]) >= (1.0 - self::POWERLESS_HWB_EPSILON) ? [0 => true] : [],
			'lch', 'oklch' => $normalizedChannels[1] <= self::POWERLESS_LCH_EPSILON ? [2 => true] : [],
			default => [],
		};
	}

	/**
	 * Computes final channel components (CSSNumericValue or `CSSKeywordValue('none')`)
	 * by applying §4.4.1 powerless detection and §12.2 missing-component carry-forward.
	 *
	 * @param array{0: CSSNumericValue|CSSKeywordValue, 1: CSSNumericValue|CSSKeywordValue, 2: CSSNumericValue|CSSKeywordValue} $components
	 * @param array{0: float, 1: float, 2: float} $powerlessCheckValues Normalized numeric values for powerless detection
	 * @param array<string, true> $sourceMissingCategories Missing categories from the source color
	 * @return array{0: CSSNumericValue|CSSKeywordValue, 1: CSSNumericValue|CSSKeywordValue, 2: CSSNumericValue|CSSKeywordValue}
	 */
	public static function applyMissingAndPowerless(
		string $targetSpace,
		array $components,
		array $powerlessCheckValues,
		array $sourceMissingCategories,
	): array {
		$powerless = self::detectPowerlessIndices($targetSpace, $powerlessCheckValues);
		$targetCategories = self::ANALOGOUS_CATEGORIES[$targetSpace] ?? [null, null, null];

		for ($i = 0; $i < 3; $i++) {
			$cat = $targetCategories[$i];
			$isCarriedMissing = $cat !== null && isset($sourceMissingCategories[$cat]);
			if (isset($powerless[$i]) || $isCarriedMissing) {
				$components[$i] = new CSSKeywordValue('none');
			}
		}

		return $components;
	}
}
