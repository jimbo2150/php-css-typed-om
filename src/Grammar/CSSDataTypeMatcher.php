<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Grammar;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSDeferredColorException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Matches primitive and functional CSS Values & Units / CSS Color / CSS Images data types
 * against slices of parsed CSS component values.
 *
 * @phpstan-import-type ParsedComponent from CSSValueComponentParser
 * @internal
 */
final class CSSDataTypeMatcher
{
	private static ?CSSGrammarNode $cachedPositiveNumberNode = null;

	/** @var array<string, true> */
	private const array PRIMITIVE_TYPES = [
		'number' => true,
		'integer' => true,
		'non-zero-integer' => true,
		'zero' => true,
		'length' => true,
		'percentage' => true,
		'length-percentage' => true,
		'angle' => true,
		'angle-percentage' => true,
		'time' => true,
		'time-percentage' => true,
		'resolution' => true,
		'flex' => true,
		'alpha-value' => true,
		'hue' => true,
		'frequency' => true,
		'dimension' => true,
		'color' => true,
		'hex-color' => true,
		'named-color' => true,
		'system-color' => true,
		'deprecated-color' => true,
		'url' => true,
		'image-misc-fn' => true,
		'string' => true,
		'ident' => true,
		'custom-ident' => true,
		'dashed-ident' => true,
		'urange' => true,
		'ratio' => true,
		'declaration-value' => true,
		'any-value' => true,
	];

	/** @var array<string, true> */
	private const array SYSTEM_COLORS = [
		'accentcolor' => true,
		'accentcolortext' => true,
		'activetext' => true,
		'buttonborder' => true,
		'buttonface' => true,
		'buttontext' => true,
		'canvas' => true,
		'canvastext' => true,
		'field' => true,
		'fieldtext' => true,
		'graytext' => true,
		'highlight' => true,
		'highlighttext' => true,
		'linktext' => true,
		'mark' => true,
		'marktext' => true,
		'selecteditem' => true,
		'selecteditemtext' => true,
		'visitedtext' => true,
	];

	/** @var array<string, true> */
	private const array DEPRECATED_SYSTEM_COLORS = [
		'activeborder' => true,
		'activecaption' => true,
		'appworkspace' => true,
		'background' => true,
		'buttonhighlight' => true,
		'buttonshadow' => true,
		'captiontext' => true,
		'inactiveborder' => true,
		'inactivecaption' => true,
		'inactivecaptiontext' => true,
		'infobackground' => true,
		'infotext' => true,
		'menu' => true,
		'menutext' => true,
		'scrollbar' => true,
		'threeddarkshadow' => true,
		'threedface' => true,
		'threedhighlight' => true,
		'threedlightshadow' => true,
		'threedshadow' => true,
		'window' => true,
		'windowframe' => true,
		'windowtext' => true,
	];

	/** @var array<string, true> */
	private const array LENGTH_UNITS = [
		'px' => true, 'cm' => true, 'mm' => true, 'q' => true, 'in' => true, 'pc' => true, 'pt' => true,
		'em' => true, 'rem' => true, 'ex' => true, 'rex' => true, 'cap' => true, 'rcap' => true,
		'ch' => true, 'rch' => true, 'ic' => true, 'ric' => true, 'lh' => true, 'rlh' => true,
		'vw' => true, 'vh' => true, 'vi' => true, 'vb' => true, 'vmin' => true, 'vmax' => true,
		'svw' => true, 'svh' => true, 'svi' => true, 'svb' => true, 'svmin' => true, 'svmax' => true,
		'lvw' => true, 'lvh' => true, 'lvi' => true, 'lvb' => true, 'lvmin' => true, 'lvmax' => true,
		'dvw' => true, 'dvh' => true, 'dvi' => true, 'dvb' => true, 'dvmin' => true, 'dvmax' => true,
		'cqw' => true, 'cqh' => true, 'cqi' => true, 'cqb' => true, 'cqmin' => true, 'cqmax' => true,
	];

	/** @var array<string, true> */
	private const array RELATIVE_LENGTH_UNITS = [
		'em' => true, 'rem' => true, 'ex' => true, 'rex' => true, 'cap' => true, 'rcap' => true,
		'ch' => true, 'rch' => true, 'ic' => true, 'ric' => true, 'lh' => true, 'rlh' => true,
		'vw' => true, 'vh' => true, 'vi' => true, 'vb' => true, 'vmin' => true, 'vmax' => true,
		'svw' => true, 'svh' => true, 'svi' => true, 'svb' => true, 'svmin' => true, 'svmax' => true,
		'lvw' => true, 'lvh' => true, 'lvi' => true, 'lvb' => true, 'lvmin' => true, 'lvmax' => true,
		'dvw' => true, 'dvh' => true, 'dvi' => true, 'dvb' => true, 'dvmin' => true, 'dvmax' => true,
		'cqw' => true, 'cqh' => true, 'cqi' => true, 'cqb' => true, 'cqmin' => true, 'cqmax' => true,
	];

	/** @var array<string, true> */
	private const array ANGLE_UNITS = ['deg' => true, 'grad' => true, 'rad' => true, 'turn' => true];

	/** @var array<string, true> */
	private const array TIME_UNITS = ['s' => true, 'ms' => true];

	/** @var array<string, true> */
	private const array RESOLUTION_UNITS = ['dpi' => true, 'dpcm' => true, 'dppx' => true, 'x' => true];

	/** @var array<string, true> */
	private const array FREQUENCY_UNITS = ['hz' => true, 'khz' => true];

	/** @var array<string, true> */
	private const array COLOR_FUNCTIONS = [
		'rgb' => true, 'rgba' => true, 'hsl' => true, 'hsla' => true, 'hwb' => true,
		'lab' => true, 'lch' => true, 'oklab' => true, 'oklch' => true,
		'color' => true, 'color-mix' => true, 'light-dark' => true,
	];

	/** @var array<string, true> */
	private const array CUSTOM_IDENT_EXCLUDED = [
		'initial' => true,
		'inherit' => true,
		'unset' => true,
		'revert' => true,
		'revert-layer' => true,
		'default' => true,
	];

	public static function isPrimitiveDataType(string $typeName): bool
	{
		return isset(self::PRIMITIVE_TYPES[strtolower($typeName)]);
	}

	public static function isSystemOrDeprecatedColor(string $lowerIdent): bool
	{
		return isset(self::SYSTEM_COLORS[$lowerIdent]) || isset(self::DEPRECATED_SYSTEM_COLORS[$lowerIdent]);
	}

	public static function isRelativeLengthUnit(string $lowerUnit): bool
	{
		return isset(self::RELATIVE_LENGTH_UNITS[$lowerUnit]);
	}

	public static function isKnownLengthUnitInternal(string $lowerUnit): bool
	{
		return isset(self::LENGTH_UNITS[$lowerUnit]);
	}

	/**
	 * Matches `$node` (`CSSGrammarNodeType::DataType`) starting at `$pos` in `$components`.
	 * Returns all reachable end indices (usually `[]` or `[$pos + 1]`).
	 *
	 * @param list<ParsedComponent> $components
	 * @param array<string, mixed> $evalCache
	 * @return list<int>
	 * @throws CSSParserLimitException When resource limits (e.g. `maxMathDepth`) are exceeded
	 */
	public static function matchEnds(
		CSSGrammarNode $node,
		string $source,
		bool $hasComments,
		array $components,
		int $pos,
		CSSParserOptions $options,
		array &$evalCache = [],
		int &$mathBudget = 0,
	): array {
		$count = count($components);
		if ($pos >= $count) {
			return [];
		}

		$type = (string) $node->value;

		return match ($type) {
			'number', 'integer', 'non-zero-integer', 'zero', 'length', 'percentage', 'length-percentage',
			'angle', 'angle-percentage', 'time', 'time-percentage', 'resolution',
			'flex', 'alpha-value', 'hue', 'frequency', 'dimension' => self::matchNumericType($node, $source, $hasComments, $components[$pos], $pos, $options, $evalCache, $mathBudget),

			'color' => self::matchColor($source, $hasComments, $components[$pos], $pos, $options, $evalCache, $mathBudget),
			'hex-color' => self::matchHexColor($components[$pos], $pos),
			'named-color' => self::matchNamedColor($components[$pos], $pos),
			'system-color' => self::matchSystemColor($components[$pos], $pos, false),
			'deprecated-color' => self::matchSystemColor($components[$pos], $pos, true),

			'url' => self::matchUrl($components[$pos], $pos),
			'image-misc-fn' => self::matchImageMiscFn($components[$pos], $pos),

			'string' => ($components[$pos]['kind'] === 'token' && ($components[$pos]['tokenType'] ?? null) === CSSTokenType::String) ? [$pos + 1] : [],
			'ident' => ($components[$pos]['kind'] === 'token' && ($components[$pos]['tokenType'] ?? null) === CSSTokenType::Ident) ? [$pos + 1] : [],
			'custom-ident' => self::matchCustomIdent($components[$pos], $pos, $node->excludedKeywords),
			'dashed-ident' => self::matchDashedIdent($components[$pos], $pos),
			'urange' => self::matchUnicodeRange($source, $components, $pos),
			'ratio' => self::matchRatio($source, $hasComments, $components, $pos, $options, $evalCache, $mathBudget),
			'declaration-value' => self::matchAnySequence($components, $pos, false),
			'any-value' => self::matchAnySequence($components, $pos, true),

			default => [],
		};
	}

	/**
	 * @param ParsedComponent $comp
	 * @param array<string, mixed> $evalCache
	 * @return list<int>
	 */
	private static function matchNumericType(
		CSSGrammarNode $node,
		string $source,
		bool $hasComments,
		array $comp,
		int $pos,
		CSSParserOptions $options,
		array &$evalCache = [],
		int &$mathBudget = 0,
	): array {
		$type = (string) $node->value;

		if ($comp['kind'] === 'token') {
			$tokType = $comp['tokenType'] ?? null;
			if ($tokType !== CSSTokenType::Number && $tokType !== CSSTokenType::Percentage && $tokType !== CSSTokenType::Dimension) {
				return [];
			}

			$info = CSSValueComponentParser::decodeNumericToken($source, $comp);
			$val = $info['value'];
			$isInt = $info['isInteger'];
			$unit = strtolower($info['unit']);

			if (!self::matchesTokenNumericCategory($type, $tokType, $val, $isInt, $unit)) {
				return [];
			}

			if (!self::checkNumericRange($type, $val, $unit, $tokType, $node->rangeMin, $node->rangeMax)) {
				return [];
			}

			return [$pos + 1];
		}

		// CSS Values Level 4 math functions (`calc()`, `min()`, `max()`, `clamp()`, `round()`, `sin()`, ...)
		if ($comp['kind'] === 'function') {
			$fn = (string) ($comp['name'] ?? '');
			if (!CSSCalcParser::isMathFunctionName($fn)) {
				return [];
			}

			$cacheKey = 'm:' . $comp['start'] . ':' . $comp['end'];
			if (array_key_exists($cacheKey, $evalCache)) {
				$cached = $evalCache[$cacheKey];
				if ($cached === false) {
					return [];
				}
				/** @var CSSNumericType $numericType */
				$numericType = $cached['type'];
				/** @var bool $isZero */
				$isZero = $cached['isZero'];
			} else {
				if (++$mathBudget > CSSNumericValue::MAX_MATH_NODES) {
					throw new CSSParserLimitException('CSS math evaluation budget exceeded.');
				}

				$slice = CSSValueComponentParser::sliceClean($source, $comp['start'], $comp['end'], $hasComments);
				try {
					$mathVal = CSSCalcParser::parseUnitOrMathSlice($slice, 0, strlen($slice), $options, 0, true);
				} catch (CSSParserLimitException $e) {
					throw $e;
				} catch (InvalidArgumentException) {
					$evalCache[$cacheKey] = false;
					return [];
				}

				if ($mathVal === null) {
					$evalCache[$cacheKey] = false;
					return [];
				}

				$numericType = $mathVal->getNumericTypeInternal();
				$isZero = $mathVal instanceof CSSUnitValue && $mathVal->value === 0.0;
				$evalCache[$cacheKey] = ['type' => $numericType, 'isZero' => $isZero];
			}

			if ($type === 'non-zero-integer' && $isZero) {
				return [];
			}

			if (!self::matchesMathNumericType($type, $numericType)) {
				return [];
			}

			return [$pos + 1];
		}

		return [];
	}

	private static function matchesTokenNumericCategory(
		string $type,
		CSSTokenType $tokType,
		float $val,
		bool $isInt,
		string $unitLower,
	): bool {
		return match ($type) {
			'zero' => $tokType === CSSTokenType::Number && $val === 0.0,
			'number' => $tokType === CSSTokenType::Number,
			'integer' => $tokType === CSSTokenType::Number && $isInt,
			'non-zero-integer' => $tokType === CSSTokenType::Number && $isInt && $val !== 0.0,
			'percentage' => $tokType === CSSTokenType::Percentage,
			'length' => ($tokType === CSSTokenType::Dimension && isset(self::LENGTH_UNITS[$unitLower]))
				|| ($tokType === CSSTokenType::Number && $val === 0.0),
			'length-percentage' => $tokType === CSSTokenType::Percentage
				|| ($tokType === CSSTokenType::Dimension && isset(self::LENGTH_UNITS[$unitLower]))
				|| ($tokType === CSSTokenType::Number && $val === 0.0),
			'angle' => $tokType === CSSTokenType::Dimension && isset(self::ANGLE_UNITS[$unitLower]),
			'hue' => ($tokType === CSSTokenType::Dimension && isset(self::ANGLE_UNITS[$unitLower]))
				|| $tokType === CSSTokenType::Number,
			'angle-percentage' => $tokType === CSSTokenType::Percentage
				|| ($tokType === CSSTokenType::Dimension && isset(self::ANGLE_UNITS[$unitLower])),
			'time' => $tokType === CSSTokenType::Dimension && isset(self::TIME_UNITS[$unitLower]),
			'time-percentage' => $tokType === CSSTokenType::Percentage
				|| ($tokType === CSSTokenType::Dimension && isset(self::TIME_UNITS[$unitLower])),
			'resolution' => $tokType === CSSTokenType::Dimension && isset(self::RESOLUTION_UNITS[$unitLower]),
			'flex' => $tokType === CSSTokenType::Dimension && $unitLower === 'fr',
			'frequency' => $tokType === CSSTokenType::Dimension && isset(self::FREQUENCY_UNITS[$unitLower]),
			'alpha-value' => $tokType === CSSTokenType::Number || $tokType === CSSTokenType::Percentage,
			'dimension' => $tokType === CSSTokenType::Dimension,
			default => false,
		};
	}

	private static function checkNumericRange(
		string $type,
		float $val,
		string $unitLower,
		CSSTokenType $tokType,
		?float $rangeMin,
		?float $rangeMax,
	): bool {
		if ($rangeMin === null && $rangeMax === null) {
			return true;
		}

		// Unitless 0 has value 0 regardless of unit
		if ($val === 0.0) {
			$cmpVal = 0.0;
		} elseif ($tokType === CSSTokenType::Number || $tokType === CSSTokenType::Percentage) {
			$cmpVal = $val;
		} else {
			$canonical = CSSGrammarParser::canonicalizeDimension($val, $unitLower, $type);
			if ($canonical === null) {
				// Relative length units (e.g. em, vw) cannot be converted to px at parse time;
				// [0, ∞] check depends strictly on the sign of $val.
				if ($rangeMin === 0.0 && $rangeMax === null) {
					return $val >= 0.0;
				}
				return false;
			}
			$cmpVal = $canonical;
		}

		if ($rangeMin !== null && $cmpVal < $rangeMin) {
			return false;
		}
		if ($rangeMax !== null && $cmpVal > $rangeMax) {
			return false;
		}

		return true;
	}

	/**
	 * Enforces CSS Values 4 §10.9 dimensional type checking on `calc()`/`min()`/... results:
	 * - Pure dimensional types (`<length>`, `<angle>`, `<time>`, `<resolution>`, `<flex>`) MUST have `percentHint === null`
	 * - `calc(0)` resolves to numeric category `'number'` and is NOT a `<length>`
	 * - `<integer>` accepts dimensionless numeric math expressions
	 */
	private static function matchesMathNumericType(string $type, CSSNumericType $numericType): bool
	{
		$dims = $numericType->toArray();
		$hint = $numericType->percentHint;
		$nonZero = [];
		foreach ($dims as $dim => $exp) {
			if ($dim !== 'percentHint' && (int) $exp !== 0) {
				$nonZero[$dim] = (int) $exp;
			}
		}

		$countNonZero = count($nonZero);
		$singleDim = $countNonZero === 1 && reset($nonZero) === 1 ? key($nonZero) : null;
		$isNumber = $countNonZero === 0 && $hint === null;
		$isPercent = $singleDim === 'percent' && $hint === null;
		$isPureDim = $singleDim !== null && $singleDim !== 'percent' && $hint === null;
		$isHinted = $singleDim !== null && $singleDim !== 'percent' && ($hint === null || $hint === $singleDim);

		return match ($type) {
			'number', 'integer', 'non-zero-integer' => $isNumber,
			'percentage' => $isPercent,
			'alpha-value' => $isNumber || $isPercent,
			'zero' => false,
			'length' => $isPureDim && $singleDim === 'length',
			'length-percentage' => ($isHinted && $singleDim === 'length') || $isPercent,
			'angle' => $isPureDim && $singleDim === 'angle',
			'hue' => ($isPureDim && $singleDim === 'angle') || $isNumber,
			'angle-percentage' => ($isHinted && $singleDim === 'angle') || $isPercent,
			'time' => $isPureDim && $singleDim === 'time',
			'time-percentage' => ($isHinted && $singleDim === 'time') || $isPercent,
			'resolution' => $isPureDim && $singleDim === 'resolution',
			'flex' => $isPureDim && $singleDim === 'flex',
			'frequency' => $isPureDim && $singleDim === 'frequency',
			'dimension' => $isPureDim,
			default => false,
		};
	}

	/**
	 * @param ParsedComponent $comp
	 * @param array<string, mixed> $evalCache
	 * @return list<int>
	 */
	private static function matchColor(
		string $source,
		bool $hasComments,
		array $comp,
		int $pos,
		CSSParserOptions $options,
		array &$evalCache = [],
		int &$mathBudget = 0,
	): array {
		if ($comp['kind'] === 'token') {
			$tokType = $comp['tokenType'] ?? null;
			if ($tokType === CSSTokenType::Hash) {
				return CSSColorValue::isValidHexInternal((string) ($comp['name'] ?? '')) ? [$pos + 1] : [];
			}
			if ($tokType === CSSTokenType::Ident) {
				$lower = strtolower((string) ($comp['name'] ?? ''));
				if ($lower === 'currentcolor'
					|| CSSColorValue::isNamedColorIdentInternal($lower)
					|| isset(self::SYSTEM_COLORS[$lower])
					|| isset(self::DEPRECATED_SYSTEM_COLORS[$lower])
				) {
					return [$pos + 1];
				}
			}

			return [];
		}

		if ($comp['kind'] === 'function') {
			$fn = (string) ($comp['name'] ?? '');
			if (!isset(self::COLOR_FUNCTIONS[$fn])) {
				return [];
			}

			$cacheKey = 'c:' . $comp['start'] . ':' . $comp['end'];
			if (array_key_exists($cacheKey, $evalCache)) {
				return $evalCache[$cacheKey] ? [$pos + 1] : [];
			}
			if (++$mathBudget > CSSNumericValue::MAX_MATH_NODES) {
				throw new CSSParserLimitException('CSS color evaluation budget exceeded.');
			}

			$slice = CSSValueComponentParser::sliceClean($source, $comp['start'], $comp['end'], $hasComments);
			try {
				CSSColorValue::parseInternal($slice, null, $options, null, true);
				$evalCache[$cacheKey] = true;
				return [$pos + 1];
			} catch (CSSParserLimitException $e) {
				throw $e;
			} catch (CSSDeferredColorException) {
				$evalCache[$cacheKey] = true;
				return [$pos + 1];
			} catch (InvalidArgumentException) {
				$evalCache[$cacheKey] = false;
				return [];
			}
		}

		return [];
	}

	/**
	 * @param ParsedComponent $comp
	 * @return list<int>
	 */
	private static function matchHexColor(array $comp, int $pos): array
	{
		if ($comp['kind'] !== 'token' || ($comp['tokenType'] ?? null) !== CSSTokenType::Hash) {
			return [];
		}

		return CSSColorValue::isValidHexInternal((string) ($comp['name'] ?? '')) ? [$pos + 1] : [];
	}

	/**
	 * @param ParsedComponent $comp
	 * @return list<int>
	 */
	private static function matchNamedColor(array $comp, int $pos): array
	{
		if ($comp['kind'] !== 'token' || ($comp['tokenType'] ?? null) !== CSSTokenType::Ident) {
			return [];
		}
		$lower = strtolower((string) ($comp['name'] ?? ''));

		return CSSColorValue::isNamedColorIdentInternal($lower) ? [$pos + 1] : [];
	}

	/**
	 * @param ParsedComponent $comp
	 * @return list<int>
	 */
	private static function matchSystemColor(array $comp, int $pos, bool $deprecated): array
	{
		if ($comp['kind'] !== 'token' || ($comp['tokenType'] ?? null) !== CSSTokenType::Ident) {
			return [];
		}
		$lower = strtolower((string) ($comp['name'] ?? ''));
		$ok = $deprecated ? isset(self::DEPRECATED_SYSTEM_COLORS[$lower]) : isset(self::SYSTEM_COLORS[$lower]);

		return $ok ? [$pos + 1] : [];
	}

	/**
	 * Matches `<url>`: unquoted `CSSTokenType::Url`, quoted `url("..." <url-modifier>*)`, or `src("..." <url-modifier>*)`.
	 *
	 * @param ParsedComponent $comp
	 * @return list<int>
	 */
	private static function matchUrl(array $comp, int $pos): array
	{
		if ($comp['kind'] === 'token' && ($comp['tokenType'] ?? null) === CSSTokenType::Url) {
			return [$pos + 1];
		}
		if ($comp['kind'] === 'function') {
			$fn = (string) ($comp['name'] ?? '');
			if ($fn !== 'url' && $fn !== 'src') {
				return [];
			}
			/** @var list<ParsedComponent> $args */
			$args = $comp['components'] ?? [];
			if ($args === [] || ($args[0]['kind'] ?? '') !== 'token' || ($args[0]['tokenType'] ?? null) !== CSSTokenType::String) {
				return [];
			}
			// Optional <url-modifier>* (idents or modifier functions)
			$argCount = count($args);
			for ($k = 1; $k < $argCount; $k++) {
				$mod = $args[$k];
				$modKind = $mod['kind'] ?? '';
				if ($modKind === 'function') {
					continue;
				}
				if ($modKind === 'token' && ($mod['tokenType'] ?? null) === CSSTokenType::Ident) {
					continue;
				}
				return [];
			}

			return [$pos + 1];
		}

		return [];
	}

	/**
	 * Matches miscellaneous functional image notations (`element(...)` and `paint(...)`).
	 *
	 * @param ParsedComponent $comp
	 * @return list<int>
	 */
	private static function matchImageMiscFn(array $comp, int $pos): array
	{
		if ($comp['kind'] !== 'function') {
			return [];
		}

		$fn = (string) ($comp['name'] ?? '');
		/** @var list<ParsedComponent> $args */
		$args = $comp['components'] ?? [];

		if ($fn === 'element') {
			// element( <id-selector> [ , [ first | start | last | first-except ] ]? )
			if ($args === [] || ($args[0]['kind'] ?? '') !== 'token' || ($args[0]['tokenType'] ?? null) !== CSSTokenType::Hash) {
				return [];
			}
			if (count($args) === 1) {
				return [$pos + 1];
			}
			if (count($args) === 3
				&& ($args[1]['tokenType'] ?? null) === CSSTokenType::Comma
				&& ($args[2]['tokenType'] ?? null) === CSSTokenType::Ident
			) {
				$kw = strtolower((string) ($args[2]['name'] ?? ''));
				if (in_array($kw, ['first', 'start', 'last', 'first-except'], true)) {
					return [$pos + 1];
				}
			}
			return [];
		}

		if ($fn === 'paint') {
			if ($args === [] || ($args[0]['kind'] ?? '') !== 'token' || ($args[0]['tokenType'] ?? null) !== CSSTokenType::Ident) {
				return [];
			}
			return self::splitComponentArgsByComma($args) !== null ? [$pos + 1] : [];
		}

		return [];
	}

	/**
	 * @param list<ParsedComponent> $args
	 * @return ?list<list<ParsedComponent>>
	 */
	private static function splitComponentArgsByComma(array $args): ?array
	{
		$segments = [];
		$current = [];
		foreach ($args as $comp) {
			if (($comp['kind'] ?? '') === 'token' && ($comp['tokenType'] ?? null) === CSSTokenType::Comma) {
				if ($current === []) {
					return null;
				}
				$segments[] = $current;
				$current = [];
				continue;
			}
			$current[] = $comp;
		}
		if ($current === []) {
			return null;
		}
		$segments[] = $current;

		return $segments;
	}

	/**
	 * @param ParsedComponent $comp
	 * @param list<string> $excludedKeywords
	 * @return list<int>
	 */
	private static function matchCustomIdent(array $comp, int $pos, array $excludedKeywords): array
	{
		if ($comp['kind'] !== 'token' || ($comp['tokenType'] ?? null) !== CSSTokenType::Ident) {
			return [];
		}
		$lower = strtolower((string) ($comp['name'] ?? ''));
		if (isset(self::CUSTOM_IDENT_EXCLUDED[$lower])) {
			return [];
		}
		if ($excludedKeywords !== [] && in_array($lower, $excludedKeywords, true)) {
			return [];
		}

		return [$pos + 1];
	}

	/**
	 * @param ParsedComponent $comp
	 * @return list<int>
	 */
	private static function matchDashedIdent(array $comp, int $pos): array
	{
		if ($comp['kind'] !== 'token' || ($comp['tokenType'] ?? null) !== CSSTokenType::Ident) {
			return [];
		}
		$name = (string) ($comp['name'] ?? '');

		return str_starts_with($name, '--') && strlen($name) > 2 ? [$pos + 1] : [];
	}

	/**
	 * Matches `<urange>` tokens (`U+0-10FFFF`, `U+4??`, `U+0020-007F`).
	 *
	 * @param list<ParsedComponent> $components
	 * @return list<int>
	 */
	private static function matchUnicodeRange(string $source, array $components, int $pos): array
	{
		$count = count($components);
		$first = $components[$pos];
		if ($first['kind'] !== 'token' || ($first['tokenType'] ?? null) !== CSSTokenType::Ident) {
			return [];
		}
		if (strcasecmp((string) ($first['name'] ?? ''), 'u') !== 0) {
			return [];
		}

		// Consume contiguous non-whitespace tokens immediately following 'U' starting with '+'
		$endPos = $pos + 1;
		$prevEnd = $first['end'];
		$tail = '';
		while ($endPos < $count) {
			$next = $components[$endPos];
			if ($next['start'] !== $prevEnd) {
				break;
			}
			if (($next['kind'] ?? '') === 'token' && ($next['tokenType'] ?? null) === CSSTokenType::Comma) {
				break;
			}
			$tail .= substr($source, $next['start'], $next['end'] - $next['start']);
			$prevEnd = $next['end'];
			$endPos++;
		}

		if (preg_match('/^\+(?:[0-9a-fA-F]{1,6}(?:-[0-9a-fA-F]{1,6})?|[0-9a-fA-F]{0,5}\?{1,6})$/D', $tail) !== 1) {
			return [];
		}

		return [$endPos];
	}

	/**
	 * Matches `<ratio>` = `<number [0,∞]> [ / <number [0,∞]> ]?`.
	 *
	 * @param list<ParsedComponent> $components
	 * @param array<string, mixed> $evalCache
	 * @return list<int>
	 */
	private static function matchRatio(
		string $source,
		bool $hasComments,
		array $components,
		int $pos,
		CSSParserOptions $options,
		array &$evalCache = [],
		int &$mathBudget = 0,
	): array {
		$numNode = self::$cachedPositiveNumberNode ??= new CSSGrammarNode(0, CSSGrammarNodeType::DataType, 'number', [], 1, 1, false, 0.0, null);
		if (self::matchNumericType($numNode, $source, $hasComments, $components[$pos], $pos, $options, $evalCache, $mathBudget) === []) {
			return [];
		}

		$ends = [$pos + 1];
		$count = count($components);
		if ($pos + 2 < $count
			&& ($components[$pos + 1]['kind'] ?? '') === 'token'
			&& ($components[$pos + 1]['tokenType'] ?? null) === CSSTokenType::Delim
			&& substr($source, $components[$pos + 1]['start'], $components[$pos + 1]['end'] - $components[$pos + 1]['start']) === '/'
			&& self::matchNumericType($numNode, $source, $hasComments, $components[$pos + 2], $pos + 2, $options, $evalCache, $mathBudget) !== []
		) {
			$ends[] = $pos + 3;
		}

		return $ends;
	}

	/**
	 * @param list<ParsedComponent> $components
	 * @return list<int>
	 */
	private static function matchAnySequence(array $components, int $pos, bool $allowEmptyAndBraces): array
	{
		$count = count($components);
		$ends = [];
		for ($i = $pos; $i < $count; $i++) {
			if (!$allowEmptyAndBraces && ($components[$i]['kind'] ?? '') === 'token') {
				$t = $components[$i]['tokenType'] ?? null;
				if ($t === CSSTokenType::Semicolon || $t === CSSTokenType::LeftCurlyBracket || $t === CSSTokenType::RightCurlyBracket) {
					break;
				}
			}
			$ends[] = $i + 1;
		}

		return $ends;
	}
}
