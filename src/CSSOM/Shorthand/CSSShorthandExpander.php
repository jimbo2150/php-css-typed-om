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
		'border-radius' => true,
	];

	private const array FAST_LINE_SHORTHANDS = [
		'border' => true,
		'border-top' => true,
		'border-right' => true,
		'border-bottom' => true,
		'border-left' => true,
		'border-block-start' => true,
		'border-block-end' => true,
		'border-inline-start' => true,
		'border-inline-end' => true,
		'border-block' => true,
		'border-inline' => true,
		'outline' => true,
		'column-rule' => true,
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
	 * @param ?array{source: string, components: list<mixed>, hasSubstitution: bool, isCssWideKeyword: bool, hasComments: bool} $preParsed
	 * @return ?ShorthandExpandResult
	 */
	public static function expand(
		string $shorthand,
		string $trimmedValue,
		?CSSParserOptions $options = null,
		?array $preParsed = null,
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
		$parsed = $preParsed ?? CSSValueComponentParser::parse($trimmedValue, $options);
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

		return match (CSSShorthandRegistry::getCodecInternal($shorthand)) {
			'font' => FontShorthandCodec::expand($shorthand, $source, $hasComments, $components, $options),
			'box' => ($expanded = BoxAndLineShorthandCodec::expand($shorthand, $source, $hasComments, $components, $options)) !== null
				? ['kind' => 'expanded', 'longhands' => $expanded]
				: null,
			'bg' => ($expanded = BackgroundAndMaskShorthandCodec::expand($shorthand, $source, $hasComments, $components, $options)) !== null
				? ['kind' => 'expanded', 'longhands' => $expanded]
				: null,
			'grid' => ($expanded = GridShorthandCodec::expand($shorthand, $source, $hasComments, $components, $options)) !== null
				? ['kind' => 'expanded', 'longhands' => $expanded]
				: null,
			'timing' => ($expanded = LayeredTimingShorthandCodec::expand($shorthand, $source, $hasComments, $components, $options)) !== null
				? ['kind' => 'expanded', 'longhands' => $expanded]
				: null,
			default => null,
		};
	}

	/**
	 * @param list<string> $longhands
	 * @return ?array<string, string>
	 */
	private static function tryFastAsciiExpand(string $shorthand, array $longhands, string $value): ?array
	{
		if (isset(self::FAST_BOX4_SHORTHANDS[$shorthand])) {
			return BoxAndLineShorthandCodec::expandBox4Values($longhands, ShorthandCodecHelper::splitFlatSpaces($value));
		}

		if (isset(self::FAST_PAIR2_SHORTHANDS[$shorthand])) {
			$parts = ShorthandCodecHelper::splitFlatSpaces($value);
			$count = count($parts);
			if ($count === 1) {
				return [$longhands[0] => $parts[0], $longhands[1] => $parts[0]];
			}
			if ($count === 2) {
				return [$longhands[0] => $parts[0], $longhands[1] => $parts[1]];
			}

			return null;
		}

		if (isset(self::FAST_LINE_SHORTHANDS[$shorthand])) {
			$parts = ShorthandCodecHelper::splitFlatSpaces($value);
			$count = count($parts);
			if ($count < 1 || $count > 3) {
				return null;
			}
			$w = null;
			$s = null;
			$c = null;
			$isOutline = $shorthand === 'outline';
			foreach ($parts as $p) {
				$lower = strtolower($p);
				if ($s === null && isset(ShorthandCodecHelper::LINE_STYLES[$lower])) {
					$s = $p;
				} elseif ($w === null && self::isFastFlatLineWidth($p, $lower)) {
					$w = $p;
				} elseif ($c === null && (($isOutline && ($lower === 'auto' || $lower === 'invert')) || self::isFastFlatColor($p, $lower))) {
					$c = $p;
				} else {
					return null;
				}
			}

			return BoxAndLineShorthandCodec::buildLineLonghands(
				$shorthand,
				$longhands,
				$w ?? 'medium',
				$s ?? 'none',
				$c ?? ($isOutline ? 'auto' : 'currentcolor'),
			);
		}

		if ($shorthand === 'flex') {
			return self::tryFastFlatFlex(ShorthandCodecHelper::splitFlatSpaces($value));
		}

		return null;
	}

	/**
	 * @param list<string> $parts
	 * @return ?array<string, string>
	 */
	private static function tryFastFlatFlex(array $parts): ?array
	{
		$count = count($parts);
		if ($count === 1) {
			$p0 = $parts[0];
			$lower = strtolower($p0);
			if ($lower === 'none') {
				return ['flex-grow' => '0', 'flex-shrink' => '0', 'flex-basis' => 'auto'];
			}
			if ($lower === 'auto') {
				return ['flex-grow' => '1', 'flex-shrink' => '1', 'flex-basis' => 'auto'];
			}
			if (self::isFastFlatNonNegNumber($p0)) {
				return ['flex-grow' => $p0, 'flex-shrink' => '1', 'flex-basis' => '0%'];
			}
			if (self::isFastFlatFlexBasis($p0, $lower)) {
				return ['flex-grow' => '1', 'flex-shrink' => '1', 'flex-basis' => $p0];
			}

			return null;
		}

		if ($count === 2) {
			[$p0, $p1] = $parts;
			$g0 = self::isFastFlatNonNegNumber($p0);
			$g1 = self::isFastFlatNonNegNumber($p1);
			if ($g0 && $g1) {
				return ['flex-grow' => $p0, 'flex-shrink' => $p1, 'flex-basis' => '0%'];
			}
			if ($g0 && self::isFastFlatFlexBasis($p1, strtolower($p1))) {
				return ['flex-grow' => $p0, 'flex-shrink' => '1', 'flex-basis' => $p1];
			}
			if ($g1 && self::isFastFlatFlexBasis($p0, strtolower($p0))) {
				return ['flex-grow' => $p1, 'flex-shrink' => '1', 'flex-basis' => $p0];
			}

			return null;
		}

		if ($count === 3) {
			[$p0, $p1, $p2] = $parts;
			$g0 = self::isFastFlatNonNegNumber($p0);
			$g1 = self::isFastFlatNonNegNumber($p1);
			$g2 = self::isFastFlatNonNegNumber($p2);
			if ($g0 && $g1 && self::isFastFlatFlexBasis($p2, strtolower($p2))) {
				return ['flex-grow' => $p0, 'flex-shrink' => $p1, 'flex-basis' => $p2];
			}
			if ($g1 && $g2 && self::isFastFlatFlexBasis($p0, strtolower($p0))) {
				return ['flex-grow' => $p1, 'flex-shrink' => $p2, 'flex-basis' => $p0];
			}
		}

		return null;
	}

	private static function isFastFlatNonNegNumber(string $raw): bool
	{
		$len = strlen($raw);
		if ($len === 0 || strspn($raw, '0123456789.') !== $len || $raw[$len - 1] === '.') {
			return false;
		}

		return is_numeric($raw);
	}

	private static function isFastFlatFlexBasis(string $raw, string $lower): bool
	{
		if ($lower === 'auto' || $lower === 'content' || $lower === 'max-content' || $lower === 'min-content' || $lower === 'fit-content') {
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

		return $unit === '%' || CSSDataTypeMatcher::isKnownLengthUnitInternal($unit);
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
