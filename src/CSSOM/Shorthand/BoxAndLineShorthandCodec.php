<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueComponentParser;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueValidator;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;

/**
 * Expands and canonically serializes box, pair, border/line, border-radius,
 * border-image/mask-border, flex, and simple layout/typography shorthands.
 *
 * @phpstan-import-type ParsedComponent from CSSValueComponentParser
 * @internal
 */
final class BoxAndLineShorthandCodec
{
	private const array WHITE_SPACE_LEGACY_TO_LONGHANDS = [
		'normal' => ['collapse', 'wrap'],
		'pre' => ['preserve', 'nowrap'],
		'nowrap' => ['collapse', 'nowrap'],
		'pre-wrap' => ['preserve', 'wrap'],
		'break-spaces' => ['break-spaces', 'wrap'],
		'pre-line' => ['preserve-breaks', 'wrap'],
	];

	private const array WHITE_SPACE_LONGHANDS_TO_LEGACY = [
		"collapse\0wrap" => 'normal',
		"preserve\0nowrap" => 'pre',
		"collapse\0nowrap" => 'nowrap',
		"preserve\0wrap" => 'pre-wrap',
		"break-spaces\0wrap" => 'break-spaces',
		"preserve-breaks\0wrap" => 'pre-line',
	];

	private const array BOX4_SHORTHANDS = [
		'margin' => true,
		'padding' => true,
		'inset' => true,
		'border-width' => true,
		'border-style' => true,
		'border-color' => true,
		'scroll-margin' => true,
		'scroll-padding' => true,
	];

	private const array PAIR2_SHORTHANDS = [
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
		'contain-intrinsic-size' => true,
		'place-content' => true,
		'place-items' => true,
		'place-self' => true,
	];

	private const array LINE_SHORTHANDS = [
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

	private const array TEXT_BOX_TRIM_KEYWORDS = [
		'none' => true,
		'trim-start' => true,
		'trim-end' => true,
		'trim-both' => true,
	];

	private const array TEXT_DECORATION_LINE_KEYWORDS = [
		'none' => true,
		'underline' => true,
		'overline' => true,
		'line-through' => true,
		'blink' => true,
		'spelling-error' => true,
		'grammar-error' => true,
	];

	private const array TEXT_DECORATION_STYLE_KEYWORDS = [
		'solid' => true,
		'double' => true,
		'dotted' => true,
		'dashed' => true,
		'wavy' => true,
	];

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	public static function expand(
		string $shorthand,
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$longhands = CSSShorthandRegistry::getLonghands($shorthand);
		if ($longhands === null) {
			return null;
		}

		if (isset(self::BOX4_SHORTHANDS[$shorthand])) {
			return self::expandBox4($longhands, $source, $hasComments, $components);
		}
		if (isset(self::PAIR2_SHORTHANDS[$shorthand])) {
			return self::expandPair2($shorthand, $longhands, $source, $hasComments, $components, $options);
		}
		if (isset(self::LINE_SHORTHANDS[$shorthand])) {
			return self::expandLine($shorthand, $longhands, $source, $hasComments, $components, $options);
		}

		return match ($shorthand) {
			'border-radius' => self::expandBorderRadius($longhands, $source, $hasComments, $components),
			'border-image', 'mask-border' => self::expandImageBorder($shorthand, $longhands, $source, $hasComments, $components, $options),
			'flex' => self::expandFlex($source, $hasComments, $components, $options),
			'flex-flow' => self::expandFlexFlow($source, $hasComments, $components),
			'columns' => self::expandColumns($source, $hasComments, $components, $options),
			'container' => self::expandContainer($source, $hasComments, $components),
			'list-style' => self::expandListStyle($source, $hasComments, $components, $options),
			'marker' => self::expandMarker($longhands, $source, $hasComments, $components),
			'offset' => self::expandOffset($source, $hasComments, $components, $options),
			'position-try' => self::expandPositionTry($source, $hasComments, $components, $options),
			'text-box' => self::expandTextBox($source, $hasComments, $components),
			'text-decoration' => self::expandTextDecoration($source, $hasComments, $components, $options),
			'text-emphasis' => self::expandTextEmphasis($source, $hasComments, $components, $options),
			'text-wrap' => self::expandTextWrap($source, $hasComments, $components),
			'white-space' => self::expandWhiteSpace($source, $hasComments, $components),
			default => null,
		};
	}

	/**
	 * @param array<string, string> $values Longhand name => value
	 */
	public static function serialize(string $shorthand, array $values, CSSParserOptions $options): string
	{
		$longhands = CSSShorthandRegistry::getLonghands($shorthand);
		if ($longhands === null) {
			return '';
		}

		if (isset(self::BOX4_SHORTHANDS[$shorthand])) {
			return ShorthandCodecHelper::collapseBox4(
				$values[$longhands[0]],
				$values[$longhands[1]],
				$values[$longhands[2]],
				$values[$longhands[3]],
			);
		}

		if (isset(self::PAIR2_SHORTHANDS[$shorthand])) {
			return self::serializePair2($shorthand, $longhands, $values, $options);
		}

		if (isset(self::LINE_SHORTHANDS[$shorthand])) {
			return self::serializeLine($shorthand, $longhands, $values);
		}

		return match ($shorthand) {
			'border-radius' => self::serializeBorderRadius($longhands, $values, $options),
			'border-image', 'mask-border' => self::serializeImageBorder($shorthand, $longhands, $values),
			'flex' => self::serializeFlex($values, $options),
			'flex-flow' => self::serializeUnorderedPair('flex-direction', 'flex-wrap', $values),
			'columns' => self::serializeUnorderedPair('column-width', 'column-count', $values),
			'container' => self::serializeContainer($values),
			'list-style' => self::serializeListStyle($values),
			'marker' => self::serializeMarker($longhands, $values),
			'offset' => self::serializeOffset($values),
			'position-try' => self::serializePositionTry($values),
			'text-box' => self::serializeTextBox($values),
			'text-decoration' => self::serializeTextDecoration($values),
			'text-emphasis' => self::serializeTextEmphasis($values),
			'text-wrap' => self::serializeUnorderedPair('text-wrap-mode', 'text-wrap-style', $values),
			'white-space' => self::serializeWhiteSpace($values),
			default => '',
		};
	}

	/**
	 * @internal Shared 4-value box longhand assignment used by `expandBox4()` and `CSSShorthandExpander`.
	 * @param list<string> $longhands
	 * @param list<string> $parts
	 * @return ?array<string, string>
	 */
	public static function expandBox4Values(array $longhands, array $parts): ?array
	{
		return match (count($parts)) {
			1 => [
				$longhands[0] => $parts[0],
				$longhands[1] => $parts[0],
				$longhands[2] => $parts[0],
				$longhands[3] => $parts[0],
			],
			2 => [
				$longhands[0] => $parts[0],
				$longhands[1] => $parts[1],
				$longhands[2] => $parts[0],
				$longhands[3] => $parts[1],
			],
			3 => [
				$longhands[0] => $parts[0],
				$longhands[1] => $parts[1],
				$longhands[2] => $parts[2],
				$longhands[3] => $parts[1],
			],
			4 => [
				$longhands[0] => $parts[0],
				$longhands[1] => $parts[1],
				$longhands[2] => $parts[2],
				$longhands[3] => $parts[3],
			],
			default => null,
		};
	}

	/**
	 * @internal Builds longhand assignments for any `<line>` shorthand (`border*`, `outline`, `column-rule`).
	 * @param list<string> $longhands
	 * @return array<string, string>
	 */
	public static function buildLineLonghands(
		string $shorthand,
		array $longhands,
		string $wVal,
		string $sVal,
		string $cVal,
	): array {
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

		if ($shorthand === 'border-block' || $shorthand === 'border-inline') {
			return [
				$longhands[0] => $wVal,
				$longhands[1] => $sVal,
				$longhands[2] => $cVal,
				$longhands[3] => $wVal,
				$longhands[4] => $sVal,
				$longhands[5] => $cVal,
			];
		}

		if ($shorthand === 'outline') {
			return [
				'outline-color' => $cVal,
				'outline-style' => $sVal,
				'outline-width' => $wVal,
			];
		}

		return [
			$longhands[0] => $wVal,
			$longhands[1] => $sVal,
			$longhands[2] => $cVal,
		];
	}

	/**
	 * @param list<string> $longhands
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandBox4(array $longhands, string $source, bool $hasComments, array $components): ?array
	{
		$parts = [];
		foreach ($components as $comp) {
			$parts[] = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
		}

		return self::expandBox4Values($longhands, $parts);
	}

	/**
	 * @param list<string> $longhands
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandPair2(
		string $shorthand,
		array $longhands,
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$count = count($components);
		if ($count === 0) {
			return null;
		}

		if ($shorthand === 'contain-intrinsic-size') {
			$items = [];
			$i = 0;
			while ($i < $count) {
				if (ShorthandCodecHelper::isIdent($components[$i], 'auto') && ($i + 1) < $count) {
					$items[] = 'auto ' . ShorthandCodecHelper::componentText($source, $components[$i + 1], $hasComments);
					$i += 2;
				} else {
					$items[] = ShorthandCodecHelper::componentText($source, $components[$i], $hasComments);
					$i++;
				}
			}
			if (count($items) === 1) {
				return [$longhands[0] => $items[0], $longhands[1] => $items[0]];
			}
			if (count($items) === 2) {
				return [$longhands[0] => $items[0], $longhands[1] => $items[1]];
			}

			return null;
		}

		if ($shorthand === 'place-content' || $shorthand === 'place-items' || $shorthand === 'place-self') {
			[$firstProp, $secondProp] = $longhands;
			if (ShorthandCodecHelper::matchesProperty($firstProp, $source, $hasComments, $components, $options)) {
				$full = ShorthandCodecHelper::joinComponents($source, $components, $hasComments);
				if ($shorthand === 'place-content' && self::isBaselinePosition($components)) {
					return [$firstProp => $full, $secondProp => 'start'];
				}
				if (ShorthandCodecHelper::matchesProperty($secondProp, $source, $hasComments, $components, $options)) {
					return [$firstProp => $full, $secondProp => $full];
				}
			}
			for ($split = 1; $split < $count; $split++) {
				$left = array_slice($components, 0, $split);
				$right = array_slice($components, $split);
				if (ShorthandCodecHelper::matchesProperty($firstProp, $source, $hasComments, $left, $options)
					&& ShorthandCodecHelper::matchesProperty($secondProp, $source, $hasComments, $right, $options)
				) {
					return [
						$firstProp => ShorthandCodecHelper::joinComponents($source, $left, $hasComments),
						$secondProp => ShorthandCodecHelper::joinComponents($source, $right, $hasComments),
					];
				}
			}

			return null;
		}

		if ($count === 1) {
			$val = ShorthandCodecHelper::componentText($source, $components[0], $hasComments);

			return [$longhands[0] => $val, $longhands[1] => $val];
		}
		if ($count === 2) {
			return [
				$longhands[0] => ShorthandCodecHelper::componentText($source, $components[0], $hasComments),
				$longhands[1] => ShorthandCodecHelper::componentText($source, $components[1], $hasComments),
			];
		}

		return null;
	}

	/**
	 * @param list<string> $longhands
	 * @param array<string, string> $values
	 */
	private static function serializePair2(
		string $shorthand,
		array $longhands,
		array $values,
		CSSParserOptions $options,
	): string {
		$first = $values[$longhands[0]];
		$second = $values[$longhands[1]];

		if ($shorthand === 'place-content') {
			$parsed = ShorthandCodecHelper::parseValue($first, $options);
			if ($parsed !== null && self::isBaselinePosition($parsed['components'])) {
				return $second === 'start' ? $first : '';
			}
		}

		return ShorthandCodecHelper::collapsePair2($first, $second);
	}

	/**
	 * @param list<ParsedComponent> $components
	 */
	private static function isBaselinePosition(array $components): bool
	{
		$c = count($components);
		if ($c === 1) {
			return ShorthandCodecHelper::isIdent($components[0], 'baseline');
		}
		if ($c === 2) {
			$first = ShorthandCodecHelper::identLower($components[0]);

			return ($first === 'first' || $first === 'last') && ShorthandCodecHelper::isIdent($components[1], 'baseline');
		}

		return false;
	}

	/**
	 * @param list<string> $longhands
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandBorderRadius(
		array $longhands,
		string $source,
		bool $hasComments,
		array $components,
	): ?array {
		$halves = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Delim, '/');
		if ($halves === null || count($halves) > 2) {
			return null;
		}
		$h = self::expandCorner4($halves[0], $source, $hasComments);
		if ($h === null) {
			return null;
		}
		$v = isset($halves[1]) ? self::expandCorner4($halves[1], $source, $hasComments) : $h;
		if ($v === null) {
			return null;
		}

		$result = [];
		for ($i = 0; $i < 4; $i++) {
			$result[$longhands[$i]] = $h[$i] === $v[$i] ? $h[$i] : $h[$i] . ' ' . $v[$i];
		}

		return $result;
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array{0: string, 1: string, 2: string, 3: string}
	 */
	private static function expandCorner4(array $components, string $source, bool $hasComments): ?array
	{
		$count = count($components);
		if ($count < 1 || $count > 4) {
			return null;
		}
		$p = [];
		foreach ($components as $comp) {
			$p[] = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
		}

		return match ($count) {
			1 => [$p[0], $p[0], $p[0], $p[0]],
			2 => [$p[0], $p[1], $p[0], $p[1]],
			3 => [$p[0], $p[1], $p[2], $p[1]],
			4 => [$p[0], $p[1], $p[2], $p[3]],
		};
	}

	/**
	 * @param list<string> $longhands
	 * @param array<string, string> $values
	 */
	private static function serializeBorderRadius(array $longhands, array $values, CSSParserOptions $options): string
	{
		$h = [];
		$v = [];
		for ($i = 0; $i < 4; $i++) {
			$raw = $values[$longhands[$i]];
			if ($raw !== ''
				&& strcspn($raw, "()[]{}'\"\\/*\t\r\n\f") === strlen($raw)
				&& !isset(CSSValueComponentParser::CSS_WIDE_KEYWORDS[strtolower($raw)])
			) {
				$parts = ShorthandCodecHelper::splitFlatSpaces($raw);
				$cnt = count($parts);
				if ($cnt === 1) {
					$h[] = $parts[0];
					$v[] = $parts[0];
					continue;
				}
				if ($cnt === 2) {
					$h[] = $parts[0];
					$v[] = $parts[1];
					continue;
				}

				return '';
			}

			$parsed = ShorthandCodecHelper::parseValue($raw, $options);
			if ($parsed === null) {
				return '';
			}
			$comps = $parsed['components'];
			$cnt = count($comps);
			if ($cnt === 1) {
				$t = ShorthandCodecHelper::componentText($parsed['source'], $comps[0], $parsed['hasComments']);
				$h[] = $t;
				$v[] = $t;
			} elseif ($cnt === 2) {
				$h[] = ShorthandCodecHelper::componentText($parsed['source'], $comps[0], $parsed['hasComments']);
				$v[] = ShorthandCodecHelper::componentText($parsed['source'], $comps[1], $parsed['hasComments']);
			} else {
				return '';
			}
		}

		$hStr = ShorthandCodecHelper::collapseBox4($h[0], $h[1], $h[2], $h[3]);
		$vStr = ShorthandCodecHelper::collapseBox4($v[0], $v[1], $v[2], $v[3]);

		return $vStr === $hStr ? $hStr : $hStr . ' / ' . $vStr;
	}

	/**
	 * @param list<string> $longhands
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandLine(
		string $shorthand,
		array $longhands,
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$count = count($components);
		if ($count < 1 || $count > 3) {
			return null;
		}

		$width = null;
		$style = null;
		$color = null;
		foreach ($components as $comp) {
			if ($width === null && ShorthandCodecHelper::matchesLineWidth($source, $hasComments, $comp, $options)) {
				$width = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
			} elseif ($style === null && ShorthandCodecHelper::matchesLineStyle($comp)) {
				$style = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
			} elseif ($color === null && ($shorthand === 'outline'
				? ShorthandCodecHelper::matchesProperty('outline-color', $source, $hasComments, [$comp], $options)
				: ShorthandCodecHelper::matchesColor($source, $hasComments, $comp, $options))
			) {
				$color = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
			} else {
				return null;
			}
		}

		$wVal = $width ?? 'medium';
		$sVal = $style ?? 'none';
		$cVal = $color ?? ($shorthand === 'outline' ? 'auto' : 'currentcolor');

		return self::buildLineLonghands($shorthand, $longhands, $wVal, $sVal, $cVal);
	}

	/**
	 * @param list<string> $longhands
	 * @param array<string, string> $values
	 */
	private static function serializeLine(string $shorthand, array $longhands, array $values): string
	{
		if ($shorthand === 'border') {
			foreach (['border-image-source', 'border-image-slice', 'border-image-width', 'border-image-outset', 'border-image-repeat'] as $imgProp) {
				if (!ShorthandCodecHelper::isInitial($imgProp, $values[$imgProp])) {
					return '';
				}
			}
			$w = $values['border-top-width'];
			$s = $values['border-top-style'];
			$c = $values['border-top-color'];
			foreach (['right', 'bottom', 'left'] as $side) {
				if ($values["border-{$side}-width"] !== $w
					|| $values["border-{$side}-style"] !== $s
					|| $values["border-{$side}-color"] !== $c
				) {
					return '';
				}
			}
			$colorInit = 'currentcolor';
		} elseif ($shorthand === 'border-block' || $shorthand === 'border-inline') {
			$w = $values[$longhands[0]];
			$s = $values[$longhands[1]];
			$c = $values[$longhands[2]];
			if ($values[$longhands[3]] !== $w || $values[$longhands[4]] !== $s || $values[$longhands[5]] !== $c) {
				return '';
			}
			$colorInit = 'currentcolor';
		} elseif ($shorthand === 'outline') {
			$c = $values['outline-color'];
			$s = $values['outline-style'];
			$w = $values['outline-width'];
			$colorInit = 'auto';
		} else {
			$w = $values[$longhands[0]];
			$s = $values[$longhands[1]];
			$c = $values[$longhands[2]];
			$colorInit = 'currentcolor';
		}

		$parts = [];
		if ($w !== 'medium') {
			$parts[] = $w;
		}
		if ($s !== 'none') {
			$parts[] = $s;
		}
		if ($c !== $colorInit) {
			$parts[] = $c;
		}

		return $parts === [] ? 'none' : implode(' ', $parts);
	}

	/**
	 * @param list<string> $longhands
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandImageBorder(
		string $shorthand,
		array $longhands,
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$isMask = $shorthand === 'mask-border';
		$result = [];
		foreach ($longhands as $lh) {
			$result[$lh] = CSSShorthandRegistry::getInitialValue($lh);
		}

		// Locate top-level '/' indices
		$slashes = [];
		$count = count($components);
		for ($i = 0; $i < $count; $i++) {
			if (ShorthandCodecHelper::isDelim($components[$i], $source, '/')) {
				$slashes[] = $i;
			}
		}

		if ($slashes === []) {
			return self::classifyImageBorder($shorthand, $result, $source, $hasComments, $components, $options, true);
		}

		if (count($slashes) > 2) {
			return null;
		}

		$s0 = $slashes[0];
		$s1 = $slashes[1] ?? null;

		// Before first '/' contains optional leading items (source/repeat/mode) followed by slice
		$pre = array_slice($components, 0, $s0);
		$sliceStart = null;
		for ($k = 0; $k < count($pre); $k++) {
			$cand = array_slice($pre, $k);
			if (ShorthandCodecHelper::matchesProperty('border-image-slice', $source, $hasComments, $cand, $options)) {
				$sliceStart = $k;
				break;
			}
		}
		if ($sliceStart === null) {
			return null;
		}
		$leading = array_slice($pre, 0, $sliceStart);
		$sliceComps = array_slice($pre, $sliceStart);
		$result[$isMask ? 'mask-border-slice' : 'border-image-slice'] = ShorthandCodecHelper::joinComponents($source, $sliceComps, $hasComments);

		// Determine post-slash end where trailing source/repeat/mode start
		$lastSlash = $s1 ?? $s0;
		$afterLast = array_slice($components, $lastSlash + 1);
		$postSplit = 0;
		$afterCount = count($afterLast);
		if ($s1 !== null) {
			// Segment after second '/' is outset followed by optional trailing items
			for ($len = min(4, $afterCount); $len >= 1; $len--) {
				$cand = array_slice($afterLast, 0, $len);
				if (ShorthandCodecHelper::matchesProperty('border-image-outset', $source, $hasComments, $cand, $options)) {
					$result[$isMask ? 'mask-border-outset' : 'border-image-outset'] = ShorthandCodecHelper::joinComponents($source, $cand, $hasComments);
					$postSplit = $len;
					break;
				}
			}
			if ($postSplit === 0) {
				return null;
			}
			$mid = array_slice($components, $s0 + 1, $s1 - $s0 - 1);
			if ($mid !== []) {
				if (!ShorthandCodecHelper::matchesProperty('border-image-width', $source, $hasComments, $mid, $options)) {
					return null;
				}
				$result[$isMask ? 'mask-border-width' : 'border-image-width'] = ShorthandCodecHelper::joinComponents($source, $mid, $hasComments);
			}
		} else {
			for ($len = min(4, $afterCount); $len >= 1; $len--) {
				$cand = array_slice($afterLast, 0, $len);
				if (ShorthandCodecHelper::matchesProperty('border-image-width', $source, $hasComments, $cand, $options)) {
					$result[$isMask ? 'mask-border-width' : 'border-image-width'] = ShorthandCodecHelper::joinComponents($source, $cand, $hasComments);
					$postSplit = $len;
					break;
				}
			}
			if ($postSplit === 0) {
				return null;
			}
		}

		$trailing = array_slice($afterLast, $postSplit);
		$rest = array_merge($leading, $trailing);
		if ($rest !== []) {
			return self::classifyImageBorder($shorthand, $result, $source, $hasComments, $rest, $options, false);
		}

		return $result;
	}

	/**
	 * @param array<string, string> $result
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function classifyImageBorder(
		string $shorthand,
		array $result,
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
		bool $allowSlice,
	): ?array {
		$isMask = $shorthand === 'mask-border';
		$sourceProp = $isMask ? 'mask-border-source' : 'border-image-source';
		$sliceProp = $isMask ? 'mask-border-slice' : 'border-image-slice';
		$repeatProp = $isMask ? 'mask-border-repeat' : 'border-image-repeat';

		$seenSource = false;
		$seenSlice = false;
		$seenRepeat = false;
		$seenMode = false;
		$count = count($components);
		$i = 0;

		while ($i < $count) {
			$comp = $components[$i];
			$lower = ShorthandCodecHelper::identLower($comp);

			if ($isMask && !$seenMode && ($lower === 'luminance' || $lower === 'alpha')) {
				$result['mask-border-mode'] = $lower;
				$seenMode = true;
				$i++;
				continue;
			}

			if (!$seenSource && ShorthandCodecHelper::matchesProperty($sourceProp, $source, $hasComments, [$comp], $options)) {
				$result[$sourceProp] = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
				$seenSource = true;
				$i++;
				continue;
			}

			if (!$seenRepeat && ShorthandCodecHelper::matchesProperty($repeatProp, $source, $hasComments, [$comp], $options)) {
				$end = $i + 1;
				if ($end < $count && ShorthandCodecHelper::matchesProperty($repeatProp, $source, $hasComments, [$comp, $components[$end]], $options)) {
					$end++;
				}
				$result[$repeatProp] = ShorthandCodecHelper::joinComponents($source, array_slice($components, $i, $end - $i), $hasComments);
				$seenRepeat = true;
				$i = $end;
				continue;
			}

			if ($allowSlice && !$seenSlice) {
				$matchedEnd = null;
				for ($len = min(5, $count - $i); $len >= 1; $len--) {
					$cand = array_slice($components, $i, $len);
					if (ShorthandCodecHelper::matchesProperty($sliceProp, $source, $hasComments, $cand, $options)) {
						$matchedEnd = $i + $len;
						break;
					}
				}
				if ($matchedEnd !== null) {
					$result[$sliceProp] = ShorthandCodecHelper::joinComponents($source, array_slice($components, $i, $matchedEnd - $i), $hasComments);
					$seenSlice = true;
					$i = $matchedEnd;
					continue;
				}
			}

			return null;
		}

		return $result;
	}

	/**
	 * @param list<string> $longhands
	 * @param array<string, string> $values
	 */
	private static function serializeImageBorder(string $shorthand, array $longhands, array $values): string
	{
		$isMask = $shorthand === 'mask-border';
		$source = $values[$isMask ? 'mask-border-source' : 'border-image-source'];
		$slice = $values[$isMask ? 'mask-border-slice' : 'border-image-slice'];
		$width = $values[$isMask ? 'mask-border-width' : 'border-image-width'];
		$outset = $values[$isMask ? 'mask-border-outset' : 'border-image-outset'];
		$repeat = $values[$isMask ? 'mask-border-repeat' : 'border-image-repeat'];
		$mode = $isMask ? $values['mask-border-mode'] : null;

		$sourceInit = 'none';
		$sliceInit = $isMask ? '0' : '100%';
		$widthInit = $isMask ? 'auto' : '1';
		$outsetInit = '0';
		$repeatInit = 'stretch';

		$parts = [];
		if ($source !== $sourceInit) {
			$parts[] = $source;
		}
		$hasWidth = $width !== $widthInit;
		$hasOutset = $outset !== $outsetInit;
		if ($slice !== $sliceInit || $hasWidth || $hasOutset) {
			$sliceChunk = $slice;
			if ($hasOutset) {
				$sliceChunk .= $hasWidth ? ' / ' . $width . ' / ' . $outset : ' / / ' . $outset;
			} elseif ($hasWidth) {
				$sliceChunk .= ' / ' . $width;
			}
			$parts[] = $sliceChunk;
		}
		if ($repeat !== $repeatInit) {
			$parts[] = $repeat;
		}
		if ($isMask && $mode !== 'alpha') {
			$parts[] = (string) $mode;
		}

		return $parts === [] ? 'none' : implode(' ', $parts);
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandFlex(string $source, bool $hasComments, array $components, CSSParserOptions $options): ?array
	{
		$count = count($components);
		if ($count === 1) {
			$lower = ShorthandCodecHelper::identLower($components[0]);
			if ($lower === 'none') {
				return ['flex-grow' => '0', 'flex-shrink' => '0', 'flex-basis' => 'auto'];
			}
			if ($lower === 'auto') {
				return ['flex-grow' => '1', 'flex-shrink' => '1', 'flex-basis' => 'auto'];
			}
			$text = ShorthandCodecHelper::componentText($source, $components[0], $hasComments);
			if (self::isFlexGrowComponent($source, $hasComments, $components[0], $options)) {
				return ['flex-grow' => $text, 'flex-shrink' => '1', 'flex-basis' => '0%'];
			}

			return ['flex-grow' => '1', 'flex-shrink' => '1', 'flex-basis' => $text];
		}

		if ($count === 2) {
			$c0 = $components[0];
			$c1 = $components[1];
			$t0 = ShorthandCodecHelper::componentText($source, $c0, $hasComments);
			$t1 = ShorthandCodecHelper::componentText($source, $c1, $hasComments);
			$g0 = self::isFlexGrowComponent($source, $hasComments, $c0, $options);
			$g1 = self::isFlexGrowComponent($source, $hasComments, $c1, $options);

			if ($g0 && $g1) {
				return ['flex-grow' => $t0, 'flex-shrink' => $t1, 'flex-basis' => '0%'];
			}
			if ($g0 && ShorthandCodecHelper::matchesProperty('flex-basis', $source, $hasComments, [$c1], $options)) {
				return ['flex-grow' => $t0, 'flex-shrink' => '1', 'flex-basis' => $t1];
			}
			if ($g1 && ShorthandCodecHelper::matchesProperty('flex-basis', $source, $hasComments, [$c0], $options)) {
				return ['flex-grow' => $t1, 'flex-shrink' => '1', 'flex-basis' => $t0];
			}

			return null;
		}

		if ($count === 3) {
			$c0 = $components[0];
			$c1 = $components[1];
			$c2 = $components[2];
			$t0 = ShorthandCodecHelper::componentText($source, $c0, $hasComments);
			$t1 = ShorthandCodecHelper::componentText($source, $c1, $hasComments);
			$t2 = ShorthandCodecHelper::componentText($source, $c2, $hasComments);

			if (self::isFlexGrowComponent($source, $hasComments, $c0, $options)
				&& self::isFlexGrowComponent($source, $hasComments, $c1, $options)
				&& ShorthandCodecHelper::matchesProperty('flex-basis', $source, $hasComments, [$c2], $options)
			) {
				return ['flex-grow' => $t0, 'flex-shrink' => $t1, 'flex-basis' => $t2];
			}
			if (self::isFlexGrowComponent($source, $hasComments, $c1, $options)
				&& self::isFlexGrowComponent($source, $hasComments, $c2, $options)
				&& ShorthandCodecHelper::matchesProperty('flex-basis', $source, $hasComments, [$c0], $options)
			) {
				return ['flex-grow' => $t1, 'flex-shrink' => $t2, 'flex-basis' => $t0];
			}
		}

		return null;
	}

	/**
	 * @param ParsedComponent $comp
	 */
	private static function isFlexGrowComponent(string $source, bool $hasComments, array $comp, CSSParserOptions $options): bool
	{
		return ShorthandCodecHelper::matchesProperty('flex-grow', $source, $hasComments, [$comp], $options);
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeFlex(array $values, CSSParserOptions $options): string
	{
		$grow = $values['flex-grow'];
		$shrink = $values['flex-shrink'];
		$basis = $values['flex-basis'];

		if ($basis === '0%') {
			return $shrink === '1' ? $grow : $grow . ' ' . $shrink;
		}
		if ($grow === '0' && $shrink === '0' && $basis === 'auto') {
			return 'none';
		}
		if ($grow === '1' && $shrink === '1' && $basis === 'auto') {
			return 'auto';
		}
		if ($grow === '0' && $shrink === '1' && $basis === 'auto') {
			return '0 1 auto';
		}

		$basisLooksLikeNumber = (strspn($basis, '0123456789.+-eE') === strlen($basis) || strpbrk($basis, '(/\\') !== false)
			&& CSSValueValidator::validateStandardProperty('flex-grow', $basis, $options);
		if ($grow === '1' && $shrink === '1' && !$basisLooksLikeNumber) {
			return $basis;
		}
		if ($shrink === '1' && !$basisLooksLikeNumber) {
			return $grow . ' ' . $basis;
		}

		return $grow . ' ' . $shrink . ' ' . $basis;
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandFlexFlow(string $source, bool $hasComments, array $components): ?array
	{
		$dir = 'row';
		$wrap = 'nowrap';
		$seenDir = false;
		$seenWrap = false;
		foreach ($components as $comp) {
			$lower = ShorthandCodecHelper::identLower($comp);
			if ($lower === null) {
				return null;
			}
			if (!$seenDir && ($lower === 'row' || $lower === 'row-reverse' || $lower === 'column' || $lower === 'column-reverse')) {
				$dir = $lower;
				$seenDir = true;
			} elseif (!$seenWrap && ($lower === 'nowrap' || $lower === 'wrap' || $lower === 'wrap-reverse')) {
				$wrap = $lower;
				$seenWrap = true;
			} else {
				return null;
			}
		}

		return ['flex-direction' => $dir, 'flex-wrap' => $wrap];
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandColumns(string $source, bool $hasComments, array $components, CSSParserOptions $options): ?array
	{
		$count = count($components);
		if ($count < 1 || $count > 2) {
			return null;
		}

		$width = null;
		$colCount = null;
		foreach ($components as $comp) {
			$text = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
			if (ShorthandCodecHelper::isIdent($comp, 'auto')) {
				if ($width === null) {
					$width = 'auto';
				} elseif ($colCount === null) {
					$colCount = 'auto';
				} else {
					return null;
				}
			} elseif ($colCount === null && ShorthandCodecHelper::matchesProperty('column-count', $source, $hasComments, [$comp], $options)) {
				$colCount = $text;
			} elseif ($width === null && ShorthandCodecHelper::matchesProperty('column-width', $source, $hasComments, [$comp], $options)) {
				$width = $text;
			} else {
				return null;
			}
		}

		return [
			'column-width' => $width ?? 'auto',
			'column-count' => $colCount ?? 'auto',
		];
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandContainer(string $source, bool $hasComments, array $components): ?array
	{
		$halves = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Delim, '/');
		if ($halves === null || count($halves) > 2) {
			return null;
		}
		$name = ShorthandCodecHelper::joinComponents($source, $halves[0], $hasComments);
		$type = isset($halves[1]) ? ShorthandCodecHelper::joinComponents($source, $halves[1], $hasComments) : 'normal';

		return ['container-name' => $name, 'container-type' => $type];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeContainer(array $values): string
	{
		$name = $values['container-name'];
		$type = $values['container-type'];

		return $type === 'normal' ? $name : $name . ' / ' . $type;
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandListStyle(string $source, bool $hasComments, array $components, CSSParserOptions $options): ?array
	{
		$position = 'outside';
		$image = null;
		$type = null;
		$seenPos = false;
		$noneCount = 0;

		foreach ($components as $comp) {
			$lower = ShorthandCodecHelper::identLower($comp);
			$text = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
			if ($lower === 'none') {
				$noneCount++;
				continue;
			}
			if (!$seenPos && ($lower === 'inside' || $lower === 'outside')) {
				$position = $lower;
				$seenPos = true;
			} elseif ($image === null && ShorthandCodecHelper::matchesProperty('list-style-image', $source, $hasComments, [$comp], $options)) {
				$image = $text;
			} elseif ($type === null && ShorthandCodecHelper::matchesProperty('list-style-type', $source, $hasComments, [$comp], $options)) {
				$type = $text;
			} else {
				return null;
			}
		}

		if ($noneCount === 1) {
			if ($type === null && $image === null) {
				$type = 'none';
				$image = 'none';
			} elseif ($type === null) {
				$type = 'none';
			} elseif ($image === null) {
				$image = 'none';
			} else {
				return null;
			}
		} elseif ($noneCount === 2) {
			if ($type !== null || $image !== null) {
				return null;
			}
			$type = 'none';
			$image = 'none';
		} elseif ($noneCount > 2) {
			return null;
		}

		return [
			'list-style-position' => $position,
			'list-style-image' => $image ?? 'none',
			'list-style-type' => $type ?? 'disc',
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeListStyle(array $values): string
	{
		$pos = $values['list-style-position'];
		$img = $values['list-style-image'];
		$type = $values['list-style-type'];

		if ($pos === 'outside' && $img === 'none' && $type === 'disc') {
			return 'disc';
		}

		$parts = [];
		if ($type !== 'disc') {
			$parts[] = $type;
		}
		if ($pos !== 'outside') {
			$parts[] = $pos;
		}
		if ($img !== 'none') {
			$parts[] = $img;
		}

		return $parts === [] ? 'disc' : implode(' ', $parts);
	}

	/**
	 * @param list<string> $longhands
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandMarker(array $longhands, string $source, bool $hasComments, array $components): ?array
	{
		if (count($components) !== 1) {
			return null;
		}
		$val = ShorthandCodecHelper::componentText($source, $components[0], $hasComments);

		return [
			$longhands[0] => $val,
			$longhands[1] => $val,
			$longhands[2] => $val,
		];
	}

	/**
	 * @param list<string> $longhands
	 * @param array<string, string> $values
	 */
	private static function serializeMarker(array $longhands, array $values): string
	{
		$a = $values[$longhands[0]];

		return ($a === $values[$longhands[1]] && $a === $values[$longhands[2]]) ? $a : '';
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandOffset(string $source, bool $hasComments, array $components, CSSParserOptions $options): ?array
	{
		$halves = ShorthandCodecHelper::splitByDelimiter($components, $source, CSSTokenType::Delim, '/');
		if ($halves === null || count($halves) > 2) {
			return null;
		}

		$left = $halves[0];
		$anchor = isset($halves[1]) ? ShorthandCodecHelper::joinComponents($source, $halves[1], $hasComments) : 'auto';
		$leftCount = count($left);

		// Grammar: <'offset-position'>? [ <'offset-path'> [ <'offset-distance'> || <'offset-rotate'> ]? ]?
		for ($posLen = 0; $posLen <= min(4, $leftCount); $posLen++) {
			$posSlice = array_slice($left, 0, $posLen);
			if ($posLen > 0 && !ShorthandCodecHelper::matchesProperty('offset-position', $source, $hasComments, $posSlice, $options)) {
				continue;
			}
			$rem = array_slice($left, $posLen);
			if ($rem === []) {
				if ($posLen === 0) {
					continue;
				}

				return [
					'offset-position' => ShorthandCodecHelper::joinComponents($source, $posSlice, $hasComments),
					'offset-path' => 'none',
					'offset-distance' => '0px',
					'offset-rotate' => 'auto',
					'offset-anchor' => $anchor,
				];
			}

			// First 1..2 items of $rem form offset-path
			for ($pathLen = 1; $pathLen <= min(2, count($rem)); $pathLen++) {
				$pathSlice = array_slice($rem, 0, $pathLen);
				if (!ShorthandCodecHelper::matchesProperty('offset-path', $source, $hasComments, $pathSlice, $options)) {
					continue;
				}
				$tail = array_slice($rem, $pathLen);
				$distRot = self::matchOffsetDistanceAndRotate($tail, $source, $hasComments, $options);
				if ($distRot === null) {
					continue;
				}

				return [
					'offset-position' => $posLen > 0 ? ShorthandCodecHelper::joinComponents($source, $posSlice, $hasComments) : 'normal',
					'offset-path' => ShorthandCodecHelper::joinComponents($source, $pathSlice, $hasComments),
					'offset-distance' => $distRot[0],
					'offset-rotate' => $distRot[1],
					'offset-anchor' => $anchor,
				];
			}
		}

		return null;
	}

	/**
	 * @param list<ParsedComponent> $tail
	 * @return ?array{string, string}
	 */
	private static function matchOffsetDistanceAndRotate(
		array $tail,
		string $source,
		bool $hasComments,
		CSSParserOptions $options,
	): ?array {
		$cnt = count($tail);
		if ($cnt === 0) {
			return ['0px', 'auto'];
		}
		if (ShorthandCodecHelper::matchesProperty('offset-distance', $source, $hasComments, $tail, $options)) {
			return [ShorthandCodecHelper::joinComponents($source, $tail, $hasComments), 'auto'];
		}
		if (ShorthandCodecHelper::matchesProperty('offset-rotate', $source, $hasComments, $tail, $options)) {
			return ['0px', ShorthandCodecHelper::joinComponents($source, $tail, $hasComments)];
		}
		for ($split = 1; $split < $cnt; $split++) {
			$a = array_slice($tail, 0, $split);
			$b = array_slice($tail, $split);
			if (ShorthandCodecHelper::matchesProperty('offset-distance', $source, $hasComments, $a, $options)
				&& ShorthandCodecHelper::matchesProperty('offset-rotate', $source, $hasComments, $b, $options)
			) {
				return [
					ShorthandCodecHelper::joinComponents($source, $a, $hasComments),
					ShorthandCodecHelper::joinComponents($source, $b, $hasComments),
				];
			}
			if (ShorthandCodecHelper::matchesProperty('offset-rotate', $source, $hasComments, $a, $options)
				&& ShorthandCodecHelper::matchesProperty('offset-distance', $source, $hasComments, $b, $options)
			) {
				return [
					ShorthandCodecHelper::joinComponents($source, $b, $hasComments),
					ShorthandCodecHelper::joinComponents($source, $a, $hasComments),
				];
			}
		}

		return null;
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeOffset(array $values): string
	{
		$pos = $values['offset-position'];
		$path = $values['offset-path'];
		$dist = $values['offset-distance'];
		$rot = $values['offset-rotate'];
		$anchor = $values['offset-anchor'];

		$parts = [];
		if ($pos !== 'normal') {
			$parts[] = $pos;
		}
		$needPath = $path !== 'none' || $dist !== '0px' || $rot !== 'auto';
		if ($needPath) {
			$parts[] = $path;
			if ($dist !== '0px') {
				$parts[] = $dist;
			}
			if ($rot !== 'auto') {
				$parts[] = $rot;
			}
		}
		if ($parts === []) {
			$parts[] = 'normal';
		}

		$result = implode(' ', $parts);
		if ($anchor !== 'auto') {
			$result .= ' / ' . $anchor;
		}

		return $result;
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return array<string, string>
	 */
	private static function expandPositionTry(string $source, bool $hasComments, array $components, CSSParserOptions $options): array
	{
		$firstLower = ShorthandCodecHelper::identLower($components[0]);
		if (count($components) >= 2 && ShorthandCodecHelper::matchesProperty('position-try-order', $source, $hasComments, [$components[0]], $options)) {
			$rest = array_slice($components, 1);
			if (ShorthandCodecHelper::matchesProperty('position-try-fallbacks', $source, $hasComments, $rest, $options)) {
				return [
					'position-try-order' => (string) $firstLower,
					'position-try-fallbacks' => ShorthandCodecHelper::sliceComponents($source, $components, 1, count($components), $hasComments),
				];
			}
		}

		return [
			'position-try-order' => 'normal',
			'position-try-fallbacks' => ShorthandCodecHelper::sliceComponents($source, $components, 0, count($components), $hasComments),
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializePositionTry(array $values): string
	{
		$order = $values['position-try-order'];
		$fallbacks = $values['position-try-fallbacks'];

		return $order === 'normal' ? $fallbacks : $order . ' ' . $fallbacks;
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return array<string, string>
	 */
	private static function expandTextBox(string $source, bool $hasComments, array $components): array
	{
		if (count($components) === 1 && ShorthandCodecHelper::isIdent($components[0], 'normal')) {
			return ['text-box-trim' => 'none', 'text-box-edge' => 'auto'];
		}

		$firstLower = ShorthandCodecHelper::identLower($components[0]);
		$lastLower = ShorthandCodecHelper::identLower($components[count($components) - 1]);
		if ($firstLower !== null && isset(self::TEXT_BOX_TRIM_KEYWORDS[$firstLower])) {
			$edge = count($components) > 1
				? ShorthandCodecHelper::joinComponents($source, array_slice($components, 1), $hasComments)
				: 'auto';

			return ['text-box-trim' => $firstLower, 'text-box-edge' => $edge];
		}
		if ($lastLower !== null && isset(self::TEXT_BOX_TRIM_KEYWORDS[$lastLower])) {
			$edge = count($components) > 1
				? ShorthandCodecHelper::joinComponents($source, array_slice($components, 0, -1), $hasComments)
				: 'auto';

			return ['text-box-trim' => $lastLower, 'text-box-edge' => $edge];
		}

		return [
			'text-box-trim' => 'trim-both',
			'text-box-edge' => ShorthandCodecHelper::joinComponents($source, $components, $hasComments),
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeTextBox(array $values): string
	{
		$trim = $values['text-box-trim'];
		$edge = $values['text-box-edge'];

		if ($trim === 'none' && $edge === 'auto') {
			return 'normal';
		}
		if ($trim === 'none') {
			return '';
		}
		if ($edge === 'auto') {
			return $trim;
		}

		return $trim === 'trim-both' ? $edge : $trim . ' ' . $edge;
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandTextDecoration(string $source, bool $hasComments, array $components, CSSParserOptions $options): ?array
	{
		$lines = [];
		$thickness = null;
		$style = null;
		$color = null;

		foreach ($components as $comp) {
			$lower = ShorthandCodecHelper::identLower($comp);
			$text = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
			if ($lower !== null && isset(self::TEXT_DECORATION_LINE_KEYWORDS[$lower])) {
				$lines[] = $lower;
			} elseif ($style === null && $lower !== null && isset(self::TEXT_DECORATION_STYLE_KEYWORDS[$lower])) {
				$style = $lower;
			} elseif ($thickness === null && ShorthandCodecHelper::matchesProperty('text-decoration-thickness', $source, $hasComments, [$comp], $options)) {
				$thickness = $text;
			} elseif ($color === null && ShorthandCodecHelper::matchesColor($source, $hasComments, $comp, $options)) {
				$color = $text;
			} else {
				return null;
			}
		}

		return [
			'text-decoration-line' => $lines === [] ? 'none' : implode(' ', $lines),
			'text-decoration-thickness' => $thickness ?? 'auto',
			'text-decoration-style' => $style ?? 'solid',
			'text-decoration-color' => $color ?? 'currentcolor',
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeTextDecoration(array $values): string
	{
		$parts = [];
		if ($values['text-decoration-line'] !== 'none') {
			$parts[] = $values['text-decoration-line'];
		}
		if ($values['text-decoration-thickness'] !== 'auto') {
			$parts[] = $values['text-decoration-thickness'];
		}
		if ($values['text-decoration-style'] !== 'solid') {
			$parts[] = $values['text-decoration-style'];
		}
		if ($values['text-decoration-color'] !== 'currentcolor') {
			$parts[] = $values['text-decoration-color'];
		}

		return $parts === [] ? 'none' : implode(' ', $parts);
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return array<string, string>
	 */
	private static function expandTextEmphasis(string $source, bool $hasComments, array $components, CSSParserOptions $options): array
	{
		$styleParts = [];
		$color = null;
		foreach ($components as $comp) {
			if ($color === null && ShorthandCodecHelper::matchesColor($source, $hasComments, $comp, $options)) {
				$color = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
			} else {
				$styleParts[] = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
			}
		}

		return [
			'text-emphasis-style' => $styleParts === [] ? 'none' : implode(' ', $styleParts),
			'text-emphasis-color' => $color ?? 'currentcolor',
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeTextEmphasis(array $values): string
	{
		$parts = [];
		if ($values['text-emphasis-style'] !== 'none') {
			$parts[] = $values['text-emphasis-style'];
		}
		if ($values['text-emphasis-color'] !== 'currentcolor') {
			$parts[] = $values['text-emphasis-color'];
		}

		return $parts === [] ? 'none' : implode(' ', $parts);
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandTextWrap(string $source, bool $hasComments, array $components): ?array
	{
		$mode = 'wrap';
		$style = 'auto';
		$seenMode = false;
		$seenStyle = false;
		foreach ($components as $comp) {
			$lower = ShorthandCodecHelper::identLower($comp);
			if (!$seenMode && ($lower === 'wrap' || $lower === 'nowrap')) {
				$mode = $lower;
				$seenMode = true;
			} elseif (!$seenStyle && ($lower === 'auto' || $lower === 'balance' || $lower === 'stable' || $lower === 'pretty')) {
				$style = $lower;
				$seenStyle = true;
			} else {
				return null;
			}
		}

		return ['text-wrap-mode' => $mode, 'text-wrap-style' => $style];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeUnorderedPair(string $firstProp, string $secondProp, array $values): string
	{
		$a = $values[$firstProp];
		$b = $values[$secondProp];
		$aInit = ShorthandCodecHelper::isInitial($firstProp, $a);
		$bInit = ShorthandCodecHelper::isInitial($secondProp, $b);

		if ($aInit && $bInit) {
			return $a;
		}
		if ($bInit) {
			return $a;
		}
		if ($aInit) {
			return $b;
		}

		return $a . ' ' . $b;
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array<string, string>
	 */
	private static function expandWhiteSpace(string $source, bool $hasComments, array $components): ?array
	{
		if (count($components) === 1) {
			$lower = ShorthandCodecHelper::identLower($components[0]);
			if ($lower !== null && isset(self::WHITE_SPACE_LEGACY_TO_LONGHANDS[$lower])) {
				[$collapse, $wrap] = self::WHITE_SPACE_LEGACY_TO_LONGHANDS[$lower];

				return ['white-space-collapse' => $collapse, 'text-wrap-mode' => $wrap];
			}
		}

		$collapse = 'collapse';
		$wrap = 'wrap';
		$seenCollapse = false;
		$seenWrap = false;
		foreach ($components as $comp) {
			$lower = ShorthandCodecHelper::identLower($comp);
			if (!$seenCollapse && ($lower === 'collapse' || $lower === 'preserve' || $lower === 'preserve-breaks' || $lower === 'preserve-spaces' || $lower === 'break-spaces')) {
				$collapse = $lower;
				$seenCollapse = true;
			} elseif (!$seenWrap && ($lower === 'wrap' || $lower === 'nowrap')) {
				$wrap = $lower;
				$seenWrap = true;
			} else {
				return null;
			}
		}

		return ['white-space-collapse' => $collapse, 'text-wrap-mode' => $wrap];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeWhiteSpace(array $values): string
	{
		$collapse = $values['white-space-collapse'];
		$wrap = $values['text-wrap-mode'];
		$legacy = self::WHITE_SPACE_LONGHANDS_TO_LEGACY[$collapse . "\0" . $wrap] ?? null;
		if ($legacy !== null) {
			return $legacy;
		}
		if ($wrap === 'wrap') {
			return $collapse;
		}

		return $collapse . ' ' . $wrap;
	}
}
