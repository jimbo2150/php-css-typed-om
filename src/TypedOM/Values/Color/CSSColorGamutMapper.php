<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

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

	public static function isSupportedTargetSpace(string $space): bool
	{
		return isset(CSSColorSpaceRegistry::BOUNDED_TO_GAMUT_SPACE[$space])
			|| isset(CSSColorSpaceRegistry::UNBOUNDED_SPACES[$space]);
	}

	public static function inGamut(CSSColorValue $color, ?string $colorSpace = null): bool
	{
		$space = $colorSpace !== null
			? strtolower(CSSSyntaxScanner::trimCssWhitespace($colorSpace))
			: $color->getSpaceNameInternal();

		if (!self::isSupportedTargetSpace($space)) {
			throw new InvalidArgumentException('Unsupported or custom color space for gamut check: ' . $space);
		}

		if (isset(CSSColorSpaceRegistry::UNBOUNDED_SPACES[$space])) {
			$color->assertConvertibleInternal();
			return true;
		}

		$gamutSpace = CSSColorSpaceRegistry::BOUNDED_TO_GAMUT_SPACE[$space];
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

		if (isset(CSSColorSpaceRegistry::UNBOUNDED_SPACES[$space])) {
			return $origin->to($space);
		}

		$gamutSpace = CSSColorSpaceRegistry::BOUNDED_TO_GAMUT_SPACE[$space];
		$originSpace = $origin->getSpaceNameInternal();
		$isIdentitySpace = $space === $originSpace
			|| ($space === 'rgba' && $originSpace === 'rgb')
			|| ($space === 'hsla' && $originSpace === 'hsl');

		$oklchFloats = null;
		if ($origin instanceof CSSOKLCH) {
			$oklchFloats = $origin->toOklchFloatsInternal();
			$rgb = CSSColorValue::oklchToRgbSpaceFloatsInternal($gamutSpace, $oklchFloats[0], $oklchFloats[1], $oklchFloats[2]);
			if (self::rgbInGamut($rgb)) {
				return $isIdentitySpace ? $origin->clone() : $origin->to($space);
			}
		} elseif ($gamutSpace === 'srgb' && ($origin instanceof CSSRGB || $origin instanceof CSSHSL || $origin instanceof CSSHWB)) {
			if (self::rgbInGamut($origin->toRgbSpaceFloatsInternal('srgb'))) {
				return $isIdentitySpace ? $origin->clone() : $origin->to($space);
			}
		} else {
			[$x, $y, $z] = $origin->toXyzD65Internal();
			$rgb = CSSColorValue::xyzD65ToRgbSpaceFloatsInternal($gamutSpace, $x, $y, $z);
			if (self::rgbInGamut($rgb)) {
				return $isIdentitySpace ? $origin->clone() : $origin->to($space);
			}
			[$okL, $okA, $okB] = CSSColorValue::xyzD65ToOklabInternal($x, $y, $z);
			$okC = hypot($okA, $okB);
			$okH = $okC < 1e-12 ? 0.0 : fmod(rad2deg(atan2($okB, $okA)) + 360.0, 360.0);
			$oklchFloats = [$okL, $okC, $okH];
		}

		$missingMask = CSSColorConversionMeta::extractMissingMask($origin);
		[$l, $c, $h] = $oklchFloats ?? $origin->toOklchFloatsInternal();
		if (!is_finite($l) || !is_finite($c) || !is_finite($h)) {
			throw new InvalidArgumentException('Cannot gamut-map non-finite color channels.');
		}
		$alpha = $origin->cloneAlphaInternal();

		if ($l >= 1.0) {
			return self::encodeMappedRgb($space, [1.0, 1.0, 1.0], $alpha, $missingMask);
		}
		if ($l <= 0.0) {
			return self::encodeMappedRgb($space, [0.0, 0.0, 0.0], $alpha, $missingMask);
		}

		$low = 0.0;
		$high = max(0.0, min($c, self::MAX_SEARCH_CHROMA));
		$minInGamut = true;
		/** @var ?array{0: float, 1: float, 2: float} $bestClipped */
		$bestClipped = null;
		$hRad = deg2rad($h);
		$cosH = cos($hRad);
		$sinH = sin($hRad);
		$jndSq = self::JND * self::JND;

		for ($iter = 0; ($high - $low) > self::EPSILON && $iter < self::MAX_ITERATIONS; $iter++) {
			$chroma = $low + ($high - $low) / 2.0;
			$refA = $chroma * $cosH;
			$refB = $chroma * $sinH;
			$currentRgb = CSSColorValue::oklabToRgbSpaceFloatsInternal($gamutSpace, $l, $refA, $refB);
			if (!is_finite($currentRgb[0]) || !is_finite($currentRgb[1]) || !is_finite($currentRgb[2])) {
				$high = $chroma;
				continue;
			}
			if ($minInGamut && self::rgbInGamut($currentRgb)) {
				$low = $chroma;
				continue;
			}

			$clipped = self::clamp01($currentRgb);
			[$clipL, $clipA, $clipB] = CSSColorValue::rgbSpaceToOklabFloatsInternal($gamutSpace, $clipped[0], $clipped[1], $clipped[2]);
			$dl = $l - $clipL;
			$da = $refA - $clipA;
			$db = $refB - $clipB;
			$deltaESq = $dl * $dl + $da * $da + $db * $db;

			if (!is_finite($deltaESq) || $deltaESq >= $jndSq) {
				$high = $chroma;
				continue;
			}

			$deltaE = sqrt($deltaESq);
			$bestClipped = $clipped;
			if ((self::JND - $deltaE) < self::EPSILON) {
				return self::encodeMappedRgb($space, $clipped, $alpha, $missingMask);
			}
			$minInGamut = false;
			$low = $chroma;
		}

		$finalRgb = $bestClipped ?? self::clamp01(CSSColorValue::oklabToRgbSpaceFloatsInternal($gamutSpace, $l, $low * $cosH, $low * $sinH));
		$finalRgb = self::normalizeSignedZeros($finalRgb);

		return self::encodeMappedRgb($space, $finalRgb, $alpha, $missingMask);
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
	 */
	private static function encodeMappedRgb(
		string $targetSpace,
		array $rgb,
		CSSNumericValue|CSSKeywordValue $alpha,
		int $missingMask,
	): CSSColorValue {
		$rgb = self::normalizeSignedZeros($rgb);

		if ($targetSpace === 'hsl' || $targetSpace === 'hsla') {
			[$h, $s, $l] = CSSColorValue::srgbFloatsToHslInternal($rgb[0], $rgb[1], $rgb[2]);

			return CSSColorValue::buildFromFloatsInternal('hsl', $h, $s, $l, $alpha->clone(), $missingMask);
		}
		if ($targetSpace === 'hwb') {
			[$h, $w, $b] = CSSColorValue::srgbFloatsToHwbInternal($rgb[0], $rgb[1], $rgb[2]);

			return CSSColorValue::buildFromFloatsInternal('hwb', $h, $w, $b, $alpha->clone(), $missingMask);
		}

		return CSSColorValue::buildFromFloatsInternal($targetSpace, $rgb[0], $rgb[1], $rgb[2], $alpha->clone(), $missingMask);
	}
}
