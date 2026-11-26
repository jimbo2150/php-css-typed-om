<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Internal implementation of CSS Color 4 §13.2 OKLCH binary-search gamut mapping.
 *
 * @internal
 */
final class CSSColorGamutMapper
{
	public const float JND = 0.02;
	public const float EPSILON = 0.0001;
	public const int MAX_ITERATIONS = 64;
	private const float IN_GAMUT_EPSILON = 1e-7;
	private const float MAX_SEARCH_CHROMA = 10.0;

	/** @var array<string, string> Map of bounded CSS color space name to canonical RGB gamut space */
	private const array BOUNDED_TO_GAMUT_SPACE = [
		'rgb' => 'srgb',
		'rgba' => 'srgb',
		'hsl' => 'srgb',
		'hsla' => 'srgb',
		'hwb' => 'srgb',
		'srgb' => 'srgb',
		'srgb-linear' => 'srgb-linear',
		'display-p3' => 'display-p3',
		'a98-rgb' => 'a98-rgb',
		'prophoto-rgb' => 'prophoto-rgb',
		'rec2020' => 'rec2020',
	];

	/** @var array<string, true> */
	private const array UNBOUNDED_SPACES = [
		'lab' => true,
		'lch' => true,
		'oklab' => true,
		'oklch' => true,
		'xyz' => true,
		'xyz-d50' => true,
		'xyz-d65' => true,
	];

	public static function isSupportedTargetSpace(string $space): bool
	{
		return isset(self::BOUNDED_TO_GAMUT_SPACE[$space]) || isset(self::UNBOUNDED_SPACES[$space]);
	}

	public static function inGamut(CSSColorValue $color, ?string $colorSpace = null): bool
	{
		$space = $colorSpace !== null
			? strtolower(CSSSyntaxScanner::trimCssWhitespace($colorSpace))
			: $color->getSpaceNameInternal();

		if (!self::isSupportedTargetSpace($space)) {
			throw new InvalidArgumentException('Unsupported or custom color space for gamut check: ' . $space);
		}

		if (isset(self::UNBOUNDED_SPACES[$space])) {
			$color->assertConvertibleInternal();
			return true;
		}

		$gamutSpace = self::BOUNDED_TO_GAMUT_SPACE[$space];
		$rgb = $color->toRgbSpaceFloatsInternal($gamutSpace);

		return self::rgbInGamut($rgb);
	}

	public static function map(CSSColorValue $origin, ?string $colorSpace = null): CSSColorValue
	{
		$space = $colorSpace !== null
			? strtolower(CSSSyntaxScanner::trimCssWhitespace($colorSpace))
			: $origin->getSpaceNameInternal();

		if (!self::isSupportedTargetSpace($space)) {
			throw new InvalidArgumentException('Unsupported or custom color space for gamut mapping: ' . $space);
		}

		if (isset(self::UNBOUNDED_SPACES[$space])) {
			return $origin->to($space);
		}

		$gamutSpace = self::BOUNDED_TO_GAMUT_SPACE[$space];
		$originSpace = $origin->getSpaceNameInternal();

		if (self::inGamut($origin, $gamutSpace)) {
			if ($space === $originSpace
				|| ($space === 'rgba' && $originSpace === 'rgb')
				|| ($space === 'hsla' && $originSpace === 'hsl')
			) {
				return $origin->clone();
			}

			return $origin->to($space);
		}

		$missingCategories = CSSColorConversionMeta::extractMissingCategories($origin);
		[$l, $c, $h, $alpha] = $origin->toOklchFloatsInternal();
		if (!is_finite($l) || !is_finite($c) || !is_finite($h)) {
			throw new InvalidArgumentException('Cannot gamut-map non-finite color channels.');
		}

		if ($l >= 1.0) {
			return self::encodeMappedRgb($space, [1.0, 1.0, 1.0], $alpha, $missingCategories);
		}
		if ($l <= 0.0) {
			return self::encodeMappedRgb($space, [0.0, 0.0, 0.0], $alpha, $missingCategories);
		}

		$low = 0.0;
		$high = max(0.0, min($c, self::MAX_SEARCH_CHROMA));
		$minInGamut = true;
		/** @var ?array{0: float, 1: float, 2: float} $bestClipped */
		$bestClipped = null;

		for ($iter = 0; ($high - $low) > self::EPSILON && $iter < self::MAX_ITERATIONS; $iter++) {
			$chroma = $low + ($high - $low) / 2.0;
			$currentRgb = CSSColorValue::oklchToRgbSpaceFloatsInternal($gamutSpace, $l, $chroma, $h);
			if (!is_finite($currentRgb[0]) || !is_finite($currentRgb[1]) || !is_finite($currentRgb[2])) {
				$high = $chroma;
				continue;
			}
			if ($minInGamut && self::rgbInGamut($currentRgb)) {
				$low = $chroma;
				continue;
			}

			$clipped = self::clamp01($currentRgb);
			[$refL, $refA, $refB] = CSSColorValue::oklchToOklabFloatsInternal($l, $chroma, $h);
			[$clipL, $clipA, $clipB] = CSSColorValue::rgbSpaceToOklabFloatsInternal($gamutSpace, $clipped[0], $clipped[1], $clipped[2]);
			$deltaE = sqrt(($refL - $clipL) ** 2 + ($refA - $clipA) ** 2 + ($refB - $clipB) ** 2);

			if (is_finite($deltaE) && $deltaE < self::JND) {
				$bestClipped = $clipped;
				if ((self::JND - $deltaE) < self::EPSILON) {
					return self::encodeMappedRgb($space, $clipped, $alpha, $missingCategories);
				}
				$minInGamut = false;
				$low = $chroma;
			} else {
				$high = $chroma;
			}
		}

		$finalRgb = $bestClipped ?? self::clamp01(CSSColorValue::oklchToRgbSpaceFloatsInternal($gamutSpace, $l, $low, $h));
		$finalRgb = self::normalizeSignedZeros($finalRgb);

		return self::encodeMappedRgb($space, $finalRgb, $alpha, $missingCategories);
	}

	/**
	 * @param array{0: float, 1: float, 2: float} $rgb
	 */
	private static function rgbInGamut(array $rgb): bool
	{
		return is_finite($rgb[0]) && is_finite($rgb[1]) && is_finite($rgb[2])
			&& $rgb[0] >= -self::IN_GAMUT_EPSILON && $rgb[0] <= 1.0 + self::IN_GAMUT_EPSILON
			&& $rgb[1] >= -self::IN_GAMUT_EPSILON && $rgb[1] <= 1.0 + self::IN_GAMUT_EPSILON
			&& $rgb[2] >= -self::IN_GAMUT_EPSILON && $rgb[2] <= 1.0 + self::IN_GAMUT_EPSILON;
	}

	/**
	 * @param array{0: float, 1: float, 2: float} $rgb
	 * @return array{0: float, 1: float, 2: float}
	 */
	private static function clamp01(array $rgb): array
	{
		return [
			self::clampChannel($rgb[0]),
			self::clampChannel($rgb[1]),
			self::clampChannel($rgb[2]),
		];
	}

	private static function clampChannel(float $v): float
	{
		if (!is_finite($v)) {
			throw new InvalidArgumentException('Non-finite RGB channel produced during gamut mapping.');
		}
		if ($v <= 0.0) {
			return 0.0;
		}
		if ($v >= 1.0) {
			return 1.0;
		}

		return $v;
	}

	/**
	 * @param array{0: float, 1: float, 2: float} $rgb
	 * @return array{0: float, 1: float, 2: float}
	 */
	private static function normalizeSignedZeros(array $rgb): array
	{
		return [
			$rgb[0] === 0.0 ? 0.0 : $rgb[0],
			$rgb[1] === 0.0 ? 0.0 : $rgb[1],
			$rgb[2] === 0.0 ? 0.0 : $rgb[2],
		];
	}

	/**
	 * @param array{0: float, 1: float, 2: float} $rgb
	 * @param array<string, true> $missingCategories
	 */
	private static function encodeMappedRgb(
		string $targetSpace,
		array $rgb,
		CSSNumericValue|CSSKeywordValue $alpha,
		array $missingCategories,
	): CSSColorValue {
		$rgb = self::normalizeSignedZeros($rgb);

		if ($targetSpace === 'rgb' || $targetSpace === 'rgba') {
			$components = CSSColorConversionMeta::applyMissingAndPowerless(
				'rgb',
				[
					new CSSUnitValue($rgb[0] * 100.0, 'percent'),
					new CSSUnitValue($rgb[1] * 100.0, 'percent'),
					new CSSUnitValue($rgb[2] * 100.0, 'percent'),
				],
				$rgb,
				$missingCategories,
			);

			return CSSRGB::fromValidated($components[0], $components[1], $components[2], $alpha->clone());
		}

		if ($targetSpace === 'hsl' || $targetSpace === 'hsla') {
			[$h, $s, $l] = CSSColorValue::srgbFloatsToHslInternal($rgb[0], $rgb[1], $rgb[2]);
			$components = CSSColorConversionMeta::applyMissingAndPowerless(
				'hsl',
				[
					new CSSUnitValue($h, 'deg'),
					new CSSUnitValue($s * 100.0, 'percent'),
					new CSSUnitValue($l * 100.0, 'percent'),
				],
				[$h, $s, $l],
				$missingCategories,
			);

			return CSSHSL::fromValidated($components[0], $components[1], $components[2], $alpha->clone());
		}

		if ($targetSpace === 'hwb') {
			[$h, $w, $b] = CSSColorValue::srgbFloatsToHwbInternal($rgb[0], $rgb[1], $rgb[2]);
			$components = CSSColorConversionMeta::applyMissingAndPowerless(
				'hwb',
				[
					new CSSUnitValue($h, 'deg'),
					new CSSUnitValue($w * 100.0, 'percent'),
					new CSSUnitValue($b * 100.0, 'percent'),
				],
				[$h, $w, $b],
				$missingCategories,
			);

			return CSSHWB::fromValidated($components[0], $components[1], $components[2], $alpha->clone());
		}

		$components = CSSColorConversionMeta::applyMissingAndPowerless(
			$targetSpace,
			[
				new CSSUnitValue($rgb[0], 'number'),
				new CSSUnitValue($rgb[1], 'number'),
				new CSSUnitValue($rgb[2], 'number'),
			],
			$rgb,
			$missingCategories,
		);

		return CSSColor::fromValidated(
			new CSSKeywordValue($targetSpace),
			[$components[0], $components[1], $components[2]],
			$alpha->clone(),
		);
	}
}
