<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSDataTypeMatcher;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueComponentParser;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;

/**
 * Expands any CSS shorthand declaration value into its constituent longhands
 * (or pending-substitution / system-font placeholder entries) per CSSOM §6.7.
 *
 * @phpstan-type ShorthandExpandResult array{kind: 'expanded', longhands: array<string, string>}|array{kind: 'pending', pendingKind: 'substitution'|'system-font', shorthand: string, value: string, longhands: list<string>}
 * @internal
 */
final class CSSShorthandExpander
{
	private const array FAST_BOX4_SHORTHANDS = [
		'margin' => true,
		'padding' => true,
		'inset' => true,
		'border-width' => true,
		'border-style' => true,
		'border-color' => true,
		'scroll-margin' => true,
		'scroll-padding' => true,
	];

	private const array FAST_PAIR2_SHORTHANDS = [
		'margin-block' => true,
		'margin-inline' => true,
		'padding-block' => true,
		'padding-inline' => true,
		'inset-block' => true,
		'inset-inline' => true,
		'border-block-width' => true,
		'border-block-style' => true,
		'border-block-color' => true,
		'border-inline-width' => true,
		'border-inline-style' => true,
		'border-inline-color' => true,
		'scroll-margin-block' => true,
		'scroll-margin-inline' => true,
		'scroll-padding-block' => true,
		'scroll-padding-inline' => true,
		'gap' => true,
		'overflow' => true,
		'overscroll-behavior' => true,
	];

	/**
	 * Expands `$trimmedValue` for `$shorthand`.
	 *
	 * @return ?ShorthandExpandResult
	 */
	public static function expand(
		string $shorthand,
		string $trimmedValue,
		?CSSParserOptions $options = null,
	): ?array {
		$longhands = CSSShorthandRegistry::getLonghands($shorthand);
		if ($longhands === null || $trimmedValue === '') {
			return null;
		}

		// 1. O(1) CSS-wide keyword fast path
		$lower = strtolower($trimmedValue);
		if (isset(CSSValueComponentParser::CSS_WIDE_KEYWORDS[$lower])) {
			$expanded = [];
			foreach ($longhands as $lh) {
				$expanded[$lh] = $lower;
			}

			return ['kind' => 'expanded', 'longhands' => $expanded];
		}

		// 2. Flat ASCII fast paths for hot-path shorthands (margin, padding, border*, gap, overflow, etc.)
		if (strpbrk($trimmedValue, "()[]{}\"'\\/,*\t\r\n\f") === false && !str_contains($trimmedValue, '/*')) {
			$fast = self::tryFastAsciiExpand($shorthand, $longhands, $trimmedValue);
			if ($fast !== null) {
				return ['kind' => 'expanded', 'longhands' => $fast];
			}
		}

		$options ??= CSSParserOptions::default();
		$parsed = CSSValueComponentParser::parse($trimmedValue, $options);
		if ($parsed === null) {
			return null;
		}

		// 3. Pending-substitution values (`var()`, `env()`, `attr()`, `if()`, `first-valid()`)
		if ($parsed['hasSubstitution']) {
			return [
				'kind' => 'pending',
				'pendingKind' => 'substitution',
				'shorthand' => $shorthand,
				'value' => $trimmedValue,
				'longhands' => $longhands,
			];
		}

		if ($shorthand === 'all') {
			return null;
		}

		$source = $parsed['source'];
		$hasComments = $parsed['hasComments'];
		$components = $parsed['components'];

		if (FontShorthandCodec::supports($shorthand)) {
			return FontShorthandCodec::expand($shorthand, $source, $hasComments, $components, $options);
		}
		if (BoxAndLineShorthandCodec::supports($shorthand)) {
			$expanded = BoxAndLineShorthandCodec::expand($shorthand, $source, $hasComments, $components, $options);

			return $expanded !== null ? ['kind' => 'expanded', 'longhands' => $expanded] : null;
		}
		if (BackgroundAndMaskShorthandCodec::supports($shorthand)) {
			$expanded = BackgroundAndMaskShorthandCodec::expand($shorthand, $source, $hasComments, $components, $options);

			return $expanded !== null ? ['kind' => 'expanded', 'longhands' => $expanded] : null;
		}
		if (GridShorthandCodec::supports($shorthand)) {
			$expanded = GridShorthandCodec::expand($shorthand, $source, $hasComments, $components, $options);

			return $expanded !== null ? ['kind' => 'expanded', 'longhands' => $expanded] : null;
		}
		if (LayeredTimingShorthandCodec::supports($shorthand)) {
			$expanded = LayeredTimingShorthandCodec::expand($shorthand, $source, $hasComments, $components, $options);

			return $expanded !== null ? ['kind' => 'expanded', 'longhands' => $expanded] : null;
		}

		return null;
	}

	/**
	 * @param list<string> $longhands
	 * @return ?array<string, string>
	 */
	private static function tryFastAsciiExpand(string $shorthand, array $longhands, string $value): ?array
	{
		if (isset(self::FAST_BOX4_SHORTHANDS[$shorthand])) {
			$parts = str_contains($value, ' ') ? array_values(array_filter(explode(' ', $value), static fn (string $p): bool => $p !== '')) : [$value];
			$count = count($parts);
			if ($count < 1 || $count > 4) {
				return null;
			}

			return match ($count) {
				1 => [$longhands[0] => $parts[0], $longhands[1] => $parts[0], $longhands[2] => $parts[0], $longhands[3] => $parts[0]],
				2 => [$longhands[0] => $parts[0], $longhands[1] => $parts[1], $longhands[2] => $parts[0], $longhands[3] => $parts[1]],
				3 => [$longhands[0] => $parts[0], $longhands[1] => $parts[1], $longhands[2] => $parts[2], $longhands[3] => $parts[1]],
				4 => [$longhands[0] => $parts[0], $longhands[1] => $parts[1], $longhands[2] => $parts[2], $longhands[3] => $parts[3]],
			};
		}

		if (isset(self::FAST_PAIR2_SHORTHANDS[$shorthand])) {
			$parts = str_contains($value, ' ') ? array_values(array_filter(explode(' ', $value), static fn (string $p): bool => $p !== '')) : [$value];
			$count = count($parts);
			if ($count === 1) {
				return [$longhands[0] => $parts[0], $longhands[1] => $parts[0]];
			}
			if ($count === 2) {
				return [$longhands[0] => $parts[0], $longhands[1] => $parts[1]];
			}

			return null;
		}

		if ($shorthand === 'border-radius') {
			$parts = str_contains($value, ' ') ? array_values(array_filter(explode(' ', $value), static fn (string $p): bool => $p !== '')) : [$value];
			$count = count($parts);
			if ($count < 1 || $count > 4) {
				return null;
			}

			return match ($count) {
				1 => [$longhands[0] => $parts[0], $longhands[1] => $parts[0], $longhands[2] => $parts[0], $longhands[3] => $parts[0]],
				2 => [$longhands[0] => $parts[0], $longhands[1] => $parts[1], $longhands[2] => $parts[0], $longhands[3] => $parts[1]],
				3 => [$longhands[0] => $parts[0], $longhands[1] => $parts[1], $longhands[2] => $parts[2], $longhands[3] => $parts[1]],
				4 => [$longhands[0] => $parts[0], $longhands[1] => $parts[1], $longhands[2] => $parts[2], $longhands[3] => $parts[3]],
			};
		}

		if ($shorthand === 'border' || $shorthand === 'border-top' || $shorthand === 'border-right' || $shorthand === 'border-bottom' || $shorthand === 'border-left') {
			$parts = str_contains($value, ' ') ? array_values(array_filter(explode(' ', $value), static fn (string $p): bool => $p !== '')) : [$value];
			if (count($parts) < 1 || count($parts) > 3) {
				return null;
			}
			$w = null;
			$s = null;
			$c = null;
			foreach ($parts as $p) {
				$lower = strtolower($p);
				if ($s === null && isset(ShorthandCodecHelper::LINE_STYLES[$lower])) {
					$s = $p;
				} elseif ($w === null && self::isFastFlatLineWidth($p, $lower)) {
					$w = $p;
				} elseif ($c === null && self::isFastFlatColor($p, $lower)) {
					$c = $p;
				} else {
					return null;
				}
			}
			$wVal = $w ?? 'medium';
			$sVal = $s ?? 'none';
			$cVal = $c ?? 'currentcolor';
			if ($shorthand === 'border') {
				return [
					'border-top-width' => $wVal,
					'border-right-width' => $wVal,
					'border-bottom-width' => $wVal,
					'border-left-width' => $wVal,
					'border-top-style' => $sVal,
					'border-right-style' => $sVal,
					'border-bottom-style' => $sVal,
					'border-left-style' => $sVal,
					'border-top-color' => $cVal,
					'border-right-color' => $cVal,
					'border-bottom-color' => $cVal,
					'border-left-color' => $cVal,
					'border-image-source' => 'none',
					'border-image-slice' => '100%',
					'border-image-width' => '1',
					'border-image-outset' => '0',
					'border-image-repeat' => 'stretch',
				];
			}

			return [
				$longhands[0] => $wVal,
				$longhands[1] => $sVal,
				$longhands[2] => $cVal,
			];
		}

		return null;
	}

	private static function isFastFlatLineWidth(string $raw, string $lower): bool
	{
		if (isset(ShorthandCodecHelper::LINE_WIDTH_KEYWORDS[$lower])) {
			return true;
		}
		if ($raw === '0') {
			return true;
		}
		$numLen = strspn($raw, '0123456789.');
		if ($numLen === 0 || $numLen === strlen($raw) || $raw[$numLen - 1] === '.' || !is_numeric(substr($raw, 0, $numLen))) {
			return false;
		}
		$unit = strtolower(substr($raw, $numLen));

		return CSSDataTypeMatcher::isKnownLengthUnitInternal($unit);
	}

	private static function isFastFlatColor(string $raw, string $lower): bool
	{
		if ($raw[0] === '#') {
			return CSSColorValue::isValidHexInternal(substr($raw, 1));
		}

		return $lower === 'currentcolor'
			|| CSSColorValue::isNamedColorIdentInternal($lower)
			|| CSSDataTypeMatcher::isSystemOrDeprecatedColor($lower);
	}
}
