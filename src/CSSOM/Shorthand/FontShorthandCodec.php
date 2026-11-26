<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueComponentParser;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueValidator;

/**
 * Expands and canonically serializes `font`, `font-variant`, and `font-synthesis` shorthands.
 *
 * @phpstan-import-type ParsedComponent from CSSValueComponentParser
 * @phpstan-import-type ShorthandExpandResult from CSSShorthandExpander
 * @internal
 */
final class FontShorthandCodec
{
	public const array SYSTEM_FONT_KEYWORDS = [
		'caption' => true,
		'icon' => true,
		'menu' => true,
		'message-box' => true,
		'small-caption' => true,
		'status-bar' => true,
	];

	private const array FONT_STRETCH_CSS3 = [
		'ultra-condensed' => true,
		'extra-condensed' => true,
		'condensed' => true,
		'semi-condensed' => true,
		'semi-expanded' => true,
		'expanded' => true,
		'extra-expanded' => true,
		'ultra-expanded' => true,
	];

	private const array FONT_SYNTHESIS_FLAGS = [
		'weight' => 'font-synthesis-weight',
		'style' => 'font-synthesis-style',
		'small-caps' => 'font-synthesis-small-caps',
		'position' => 'font-synthesis-position',
	];

	private const array FONT_VARIANT_RESET_ONLY = [
		'font-variant-ligatures',
		'font-variant-alternates',
		'font-variant-numeric',
		'font-variant-east-asian',
		'font-variant-position',
		'font-variant-emoji',
	];

	private const array FONT_RESET_ONLY = [
		'font-optical-sizing' => 'auto',
		'font-size-adjust' => 'none',
		'font-kerning' => 'auto',
		'font-feature-settings' => 'normal',
		'font-variation-settings' => 'normal',
		'font-language-override' => 'normal',
	];

	private const array FONT_VARIANT_LIGATURES_KW = [
		'common-ligatures' => true,
		'no-common-ligatures' => true,
		'discretionary-ligatures' => true,
		'no-discretionary-ligatures' => true,
		'historical-ligatures' => true,
		'no-historical-ligatures' => true,
		'contextual' => true,
		'no-contextual' => true,
	];

	private const array FONT_VARIANT_CAPS_KW = [
		'small-caps' => true,
		'all-small-caps' => true,
		'petite-caps' => true,
		'all-petite-caps' => true,
		'unicase' => true,
		'titling-caps' => true,
	];

	private const array FONT_VARIANT_NUMERIC_KW = [
		'lining-nums' => true,
		'oldstyle-nums' => true,
		'proportional-nums' => true,
		'tabular-nums' => true,
		'diagonal-fractions' => true,
		'stacked-fractions' => true,
		'ordinal' => true,
		'slashed-zero' => true,
	];

	private const array FONT_VARIANT_EAST_ASIAN_KW = [
		'jis78' => true,
		'jis83' => true,
		'jis90' => true,
		'jis04' => true,
		'simplified' => true,
		'traditional' => true,
		'full-width' => true,
		'proportional-width' => true,
		'ruby' => true,
	];

	private const array FONT_VARIANT_POSITION_KW = [
		'sub' => true,
		'super' => true,
	];

	private const array FONT_VARIANT_EMOJI_KW = [
		'text' => true,
		'emoji' => true,
		'unicode' => true,
	];

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?ShorthandExpandResult
	 */
	public static function expand(
		string $shorthand,
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		return match ($shorthand) {
			'font' => self::expandFont($source, $hasComments, $components, $options),
			'font-variant' => self::expandFontVariant($source, $hasComments, $components),
			'font-synthesis' => self::expandFontSynthesis($components),
			default => null,
		};
	}

	/**
	 * @param array<string, string> $values
	 */
	public static function serialize(string $shorthand, array $values, CSSParserOptions $options): string
	{
		return match ($shorthand) {
			'font' => self::serializeFont($values, $options),
			'font-variant' => self::serializeFontVariant($values),
			'font-synthesis' => self::serializeFontSynthesis($values),
			default => '',
		};
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?ShorthandExpandResult
	 */
	private static function expandFont(
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
	): ?array {
		$count = count($components);
		if ($count === 1) {
			$lower = ShorthandCodecHelper::identLower($components[0]);
			if ($lower !== null && isset(self::SYSTEM_FONT_KEYWORDS[$lower])) {
				return [
					'kind' => 'pending',
					'pendingKind' => 'system-font',
					'shorthand' => 'font',
					'value' => $lower,
					'longhands' => CSSShorthandRegistry::getLonghands('font') ?? [],
				];
			}

			return null;
		}

		// Locate font-size token (everything before it must be prelude items)
		$sizeIdx = null;
		for ($i = 0; $i < $count; $i++) {
			if (ShorthandCodecHelper::matchesProperty('font-size', $source, $hasComments, [$components[$i]], $options)) {
				// Disambiguate number vs 0: font-weight is [1,1000], font-size zero is 0
				$sizeIdx = $i;
				break;
			}
		}
		if ($sizeIdx === null || $sizeIdx + 1 >= $count) {
			return null;
		}

		$prelude = self::parseFontPrelude(array_slice($components, 0, $sizeIdx), $source, $hasComments, $options);
		if ($prelude === null) {
			return null;
		}

		$fontSize = ShorthandCodecHelper::componentText($source, $components[$sizeIdx], $hasComments);
		$afterSizeIdx = $sizeIdx + 1;
		$lineHeight = 'normal';
		if (ShorthandCodecHelper::isDelim($components[$afterSizeIdx], $source, '/')) {
			if ($afterSizeIdx + 2 >= $count) {
				return null;
			}
			$lhComp = $components[$afterSizeIdx + 1];
			if (!ShorthandCodecHelper::matchesProperty('line-height', $source, $hasComments, [$lhComp], $options)) {
				return null;
			}
			$lineHeight = ShorthandCodecHelper::componentText($source, $lhComp, $hasComments);
			$afterSizeIdx += 2;
		}

		$familyComps = array_slice($components, $afterSizeIdx);
		if ($familyComps === [] || !ShorthandCodecHelper::matchesProperty('font-family', $source, $hasComments, $familyComps, $options)) {
			return null;
		}
		$fontFamily = ShorthandCodecHelper::sliceComponents($source, $components, $afterSizeIdx, $count, $hasComments);

		$longhands = [
			'font-style' => $prelude['style'],
			'font-variant-caps' => $prelude['variantCaps'],
			'font-weight' => $prelude['weight'],
			'font-stretch' => $prelude['stretch'],
			'font-size' => $fontSize,
			'line-height' => $lineHeight,
			'font-family' => $fontFamily,
		];
		foreach (self::FONT_VARIANT_RESET_ONLY as $lh) {
			$longhands[$lh] = 'normal';
		}
		foreach (self::FONT_RESET_ONLY as $lh => $init) {
			$longhands[$lh] = $init;
		}

		return ['kind' => 'expanded', 'longhands' => $longhands];
	}

	/**
	 * @param list<ParsedComponent> $prelude
	 * @return ?array{style: string, variantCaps: string, weight: string, stretch: string}
	 */
	private static function parseFontPrelude(
		array $prelude,
		string $source,
		bool $hasComments,
		CSSParserOptions $options,
	): ?array {
		$style = null;
		$variantCaps = null;
		$weight = null;
		$stretch = null;
		$normalCount = 0;

		$count = count($prelude);
		$i = 0;
		while ($i < $count) {
			$comp = $prelude[$i];
			$lower = ShorthandCodecHelper::identLower($comp);

			if ($lower === 'normal') {
				$normalCount++;
				$i++;
				continue;
			}
			if ($style === null && $lower === 'italic') {
				$style = 'italic';
				$i++;
				continue;
			}
			if ($style === null && $lower === 'oblique') {
				if ($i + 1 < $count && ShorthandCodecHelper::matchesProperty('font-style', $source, $hasComments, [$comp, $prelude[$i + 1]], $options)) {
					$style = 'oblique ' . ShorthandCodecHelper::componentText($source, $prelude[$i + 1], $hasComments);
					$i += 2;
				} else {
					$style = 'oblique';
					$i++;
				}
				continue;
			}
			if ($variantCaps === null && $lower === 'small-caps') {
				$variantCaps = 'small-caps';
				$i++;
				continue;
			}
			if ($stretch === null && $lower !== null && isset(self::FONT_STRETCH_CSS3[$lower])) {
				$stretch = $lower;
				$i++;
				continue;
			}
			if ($weight === null && ShorthandCodecHelper::matchesNonTerminal('font-weight-absolute', $source, $hasComments, [$comp], $options)) {
				$weight = ShorthandCodecHelper::componentText($source, $comp, $hasComments);
				$i++;
				continue;
			}

			return null;
		}

		$remainingSlots = ($style === null ? 1 : 0)
			+ ($variantCaps === null ? 1 : 0)
			+ ($weight === null ? 1 : 0)
			+ ($stretch === null ? 1 : 0);
		if ($normalCount > $remainingSlots) {
			return null;
		}

		return [
			'style' => $style ?? 'normal',
			'variantCaps' => $variantCaps ?? 'normal',
			'weight' => $weight ?? 'normal',
			'stretch' => $stretch ?? 'normal',
		];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeFont(array $values, CSSParserOptions $options): string
	{
		foreach (self::FONT_RESET_ONLY as $lh => $init) {
			if ($values[$lh] !== $init) {
				return '';
			}
		}
		foreach (self::FONT_VARIANT_RESET_ONLY as $lh) {
			if ($values[$lh] !== 'normal') {
				return '';
			}
		}

		$caps = $values['font-variant-caps'];
		if ($caps !== 'normal' && $caps !== 'small-caps') {
			return '';
		}

		$weight = $values['font-weight'];
		$weightLower = strtolower($weight);
		if ($weightLower === 'bolder' || $weightLower === 'lighter') {
			return '';
		}

		$stretch = $values['font-stretch'];
		$stretchLower = strtolower($stretch);
		if ($stretchLower !== 'normal' && !isset(self::FONT_STRETCH_CSS3[$stretchLower])) {
			return '';
		}

		$style = $values['font-style'];
		$size = $values['font-size'];
		$lineHeight = $values['line-height'];
		$family = $values['font-family'];
		if ($size === '' || strtolower($size) === 'normal' || $family === '') {
			return '';
		}

		// Note: CSS Fonts 4 prohibits <relative-size> or oblique angles? oblique angle IS valid in font-style!
		$parts = [];
		if (strtolower($style) !== 'normal') {
			$parts[] = $style;
		}
		if ($caps !== 'normal') {
			$parts[] = $caps;
		}
		if ($weightLower !== 'normal') {
			$parts[] = $weight;
		}
		if ($stretchLower !== 'normal') {
			$parts[] = $stretch;
		}
		$parts[] = $lineHeight === 'normal' ? $size : $size . '/' . $lineHeight;
		$parts[] = $family;

		$candidate = implode(' ', $parts);

		return CSSValueValidator::validateStandardProperty('font', $candidate, $options) ? $candidate : '';
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array{kind: 'expanded', longhands: array<string, string>}
	 */
	private static function expandFontVariant(string $source, bool $hasComments, array $components): ?array
	{
		$longhands = [
			'font-variant-ligatures' => 'normal',
			'font-variant-caps' => 'normal',
			'font-variant-alternates' => 'normal',
			'font-variant-numeric' => 'normal',
			'font-variant-east-asian' => 'normal',
			'font-variant-position' => 'normal',
			'font-variant-emoji' => 'normal',
		];

		if (count($components) === 1) {
			$lower = ShorthandCodecHelper::identLower($components[0]);
			if ($lower === 'normal') {
				return ['kind' => 'expanded', 'longhands' => $longhands];
			}
			if ($lower === 'none') {
				$longhands['font-variant-ligatures'] = 'none';

				return ['kind' => 'expanded', 'longhands' => $longhands];
			}
		}

		$lig = [];
		$caps = null;
		$num = [];
		$ea = [];
		$pos = null;
		$emoji = null;

		foreach ($components as $comp) {
			$lower = ShorthandCodecHelper::identLower($comp);
			if ($lower === null) {
				return null;
			}
			if (isset(self::FONT_VARIANT_LIGATURES_KW[$lower])) {
				$lig[] = $lower;
			} elseif ($caps === null && isset(self::FONT_VARIANT_CAPS_KW[$lower])) {
				$caps = $lower;
			} elseif (isset(self::FONT_VARIANT_NUMERIC_KW[$lower])) {
				$num[] = $lower;
			} elseif (isset(self::FONT_VARIANT_EAST_ASIAN_KW[$lower])) {
				$ea[] = $lower;
			} elseif ($pos === null && isset(self::FONT_VARIANT_POSITION_KW[$lower])) {
				$pos = $lower;
			} elseif ($emoji === null && isset(self::FONT_VARIANT_EMOJI_KW[$lower])) {
				$emoji = $lower;
			} else {
				return null;
			}
		}

		if ($lig !== []) {
			$longhands['font-variant-ligatures'] = implode(' ', $lig);
		}
		if ($caps !== null) {
			$longhands['font-variant-caps'] = $caps;
		}
		if ($num !== []) {
			$longhands['font-variant-numeric'] = implode(' ', $num);
		}
		if ($ea !== []) {
			$longhands['font-variant-east-asian'] = implode(' ', $ea);
		}
		if ($pos !== null) {
			$longhands['font-variant-position'] = $pos;
		}
		if ($emoji !== null) {
			$longhands['font-variant-emoji'] = $emoji;
		}

		return ['kind' => 'expanded', 'longhands' => $longhands];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeFontVariant(array $values): string
	{
		if ($values['font-variant-alternates'] !== 'normal') {
			return '';
		}
		$lig = $values['font-variant-ligatures'];
		$caps = $values['font-variant-caps'];
		$num = $values['font-variant-numeric'];
		$ea = $values['font-variant-east-asian'];
		$pos = $values['font-variant-position'];
		$emoji = $values['font-variant-emoji'];

		if ($lig === 'none') {
			if ($caps === 'normal' && $num === 'normal' && $ea === 'normal' && $pos === 'normal' && $emoji === 'normal') {
				return 'none';
			}

			return '';
		}

		$parts = [];
		foreach ([$lig, $caps, $num, $ea, $pos, $emoji] as $val) {
			if ($val !== 'normal') {
				$parts[] = $val;
			}
		}

		return $parts === [] ? 'normal' : implode(' ', $parts);
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return ?array{kind: 'expanded', longhands: array<string, string>}
	 */
	private static function expandFontSynthesis(array $components): ?array
	{
		$longhands = [
			'font-synthesis-weight' => 'none',
			'font-synthesis-style' => 'none',
			'font-synthesis-small-caps' => 'none',
			'font-synthesis-position' => 'none',
		];

		if (count($components) === 1 && ShorthandCodecHelper::isIdent($components[0], 'none')) {
			return ['kind' => 'expanded', 'longhands' => $longhands];
		}

		foreach ($components as $comp) {
			$lower = ShorthandCodecHelper::identLower($comp);
			if ($lower === null || !isset(self::FONT_SYNTHESIS_FLAGS[$lower])) {
				return null;
			}
			$longhands[self::FONT_SYNTHESIS_FLAGS[$lower]] = 'auto';
		}

		return ['kind' => 'expanded', 'longhands' => $longhands];
	}

	/**
	 * @param array<string, string> $values
	 */
	private static function serializeFontSynthesis(array $values): string
	{
		$parts = [];
		foreach (self::FONT_SYNTHESIS_FLAGS as $flag => $prop) {
			$val = $values[$prop];
			if ($val === 'auto') {
				$parts[] = $flag;
			} elseif ($val !== 'none') {
				return '';
			}
		}

		return $parts === [] ? 'none' : implode(' ', $parts);
	}
}
