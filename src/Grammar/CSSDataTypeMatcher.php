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
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitTypeEnum;
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

	/** @var array<string, bool> `true` = CSS Color 4 system color, `false` = deprecated system color */
	private const array SYSTEM_COLORS_MAP = [
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
		'activeborder' => false,
		'activecaption' => false,
		'appworkspace' => false,
		'background' => false,
		'buttonhighlight' => false,
		'buttonshadow' => false,
		'captiontext' => false,
		'inactiveborder' => false,
		'inactivecaption' => false,
		'inactivecaptiontext' => false,
		'infobackground' => false,
		'infotext' => false,
		'menu' => false,
		'menutext' => false,
		'scrollbar' => false,
		'threeddarkshadow' => false,
		'threedface' => false,
		'threedhighlight' => false,
		'threedlightshadow' => false,
		'threedshadow' => false,
		'window' => false,
		'windowframe' => false,
		'windowtext' => false,
	];

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
		return isset(self::SYSTEM_COLORS_MAP[$lowerIdent]);
	}

	public static function isRelativeLengthUnit(string $lowerUnit): bool
	{
		return CSSUnitEnum::tryFromSymbol($lowerUnit)?->isRelativeLength() ?? false;
	}

	public static function isKnownLengthUnitInternal(string $lowerUnit): bool
	{
		return CSSUnitEnum::tryFromSymbol($lowerUnit)?->type() === CSSUnitTypeEnum::LENGTH;
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

			$val = $comp['numValue'] ?? 0.0;
			$isInt = $comp['isInt'] ?? false;
			$unit = $comp['unitLower'] ?? '';

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

			$evaluated = self::evaluateMathComponentInternal($source, $comp, $options, $evalCache, $mathBudget);
			if ($evaluated === null) {
				return [];
			}
			if ($type === 'non-zero-integer' && $evaluated[4]) {
				return [];
			}

			return match ($type) {
				'number', 'integer', 'non-zero-integer' => $evaluated[1],
				'percentage' => $evaluated[2],
				'alpha-value' => $evaluated[1] || $evaluated[2],
				'zero' => false,
				'length' => $evaluated[0] === 'length' && $evaluated[3] === null,
				'length-percentage' => ($evaluated[0] === 'length' && ($evaluated[3] === null || $evaluated[3] === 'length')) || $evaluated[2],
				'angle' => $evaluated[0] === 'angle' && $evaluated[3] === null,
				'hue' => ($evaluated[0] === 'angle' && $evaluated[3] === null) || $evaluated[1],
				'angle-percentage' => ($evaluated[0] === 'angle' && ($evaluated[3] === null || $evaluated[3] === 'angle')) || $evaluated[2],
				'time' => $evaluated[0] === 'time' && $evaluated[3] === null,
				'time-percentage' => ($evaluated[0] === 'time' && ($evaluated[3] === null || $evaluated[3] === 'time')) || $evaluated[2],
				'resolution' => $evaluated[0] === 'resolution' && $evaluated[3] === null,
				'flex' => $evaluated[0] === 'flex' && $evaluated[3] === null,
				'frequency' => $evaluated[0] === 'frequency' && $evaluated[3] === null,
				'dimension' => $evaluated[0] !== null && $evaluated[3] === null,
				default => false,
			} ? [$pos + 1] : [];
		}

		return [];
	}

	/**
	 * Evaluates and caches pre-classified math function numeric metadata:
	 * `[0 => ?singleDim, 1 => isNumber, 2 => isPercent, 3 => ?percentHint, 4 => isZero]`.
	 *
	 * @param ParsedComponent $comp
	 * @param array<string, mixed> $evalCache
	 * @return ?array{0: ?string, 1: bool, 2: bool, 3: ?string, 4: bool}
	 */
	public static function evaluateMathComponentInternal(
		string $source,
		array $comp,
		CSSParserOptions $options,
		array &$evalCache,
		int &$mathBudget,
	): ?array {
		$cacheKey = 'm:' . $comp['start'] . ':' . $comp['end'];
		if (array_key_exists($cacheKey, $evalCache)) {
			$cached = $evalCache[$cacheKey];
			return $cached === false ? null : $cached;
		}
		if (++$mathBudget > CSSNumericValue::MAX_MATH_NODES) {
			throw new CSSParserLimitException('CSS math evaluation budget exceeded.');
		}

		$mathVal = CSSCalcParser::parseUnitOrMathSlice($source, $comp['start'], $comp['end'], $options, 0, false, null, $mathBudget);

		if ($mathVal === null) {
			$evalCache[$cacheKey] = false;
			return null;
		}

		$numericType = $mathVal->getNumericTypeInternal();
		$dims = $numericType->toArray();
		$hint = $numericType->percentHint;
		$nonZeroDim = null;
		$countNonZero = 0;
		$singleExp = 0;
		foreach ($dims as $dim => $exp) {
			if ($dim !== 'percentHint' && (int) $exp !== 0) {
				$nonZeroDim = $dim;
				$singleExp = (int) $exp;
				$countNonZero++;
			}
		}
		$singleDim = ($countNonZero === 1 && $singleExp === 1) ? $nonZeroDim : null;
		$isNumber = $countNonZero === 0 && $hint === null;
		$isPercent = $singleDim === 'percent' && $hint === null;
		$pureDim = ($singleDim !== null && $singleDim !== 'percent') ? $singleDim : null;
		$isZero = $mathVal instanceof CSSUnitValue && $mathVal->value === 0.0;

		return $evalCache[$cacheKey] = [$pureDim, $isNumber, $isPercent, $hint, $isZero];
	}

	private static function matchesTokenNumericCategory(
		string $type,
		CSSTokenType $tokType,
		float $val,
		bool $isInt,
		string $unitLower,
	): bool {
		$unitEnum = $tokType === CSSTokenType::Dimension ? CSSUnitEnum::tryFromSymbol($unitLower) : null;
		$unitCategory = $unitEnum?->type();

		return match ($type) {
			'zero' => $tokType === CSSTokenType::Number && $val === 0.0,
			'number' => $tokType === CSSTokenType::Number,
			'integer' => $tokType === CSSTokenType::Number && $isInt,
			'non-zero-integer' => $tokType === CSSTokenType::Number && $isInt && $val !== 0.0,
			'percentage' => $tokType === CSSTokenType::Percentage,
			'length' => $unitCategory === CSSUnitTypeEnum::LENGTH
				|| ($tokType === CSSTokenType::Number && $val === 0.0),
			'length-percentage' => $tokType === CSSTokenType::Percentage
				|| $unitCategory === CSSUnitTypeEnum::LENGTH
				|| ($tokType === CSSTokenType::Number && $val === 0.0),
			'angle' => $unitCategory === CSSUnitTypeEnum::ANGLE,
			'hue' => $unitCategory === CSSUnitTypeEnum::ANGLE
				|| $tokType === CSSTokenType::Number,
			'angle-percentage' => $tokType === CSSTokenType::Percentage
				|| $unitCategory === CSSUnitTypeEnum::ANGLE,
			'time' => $unitCategory === CSSUnitTypeEnum::TIME,
			'time-percentage' => $tokType === CSSTokenType::Percentage
				|| $unitCategory === CSSUnitTypeEnum::TIME,
			'resolution' => $unitCategory === CSSUnitTypeEnum::RES,
			'flex' => $unitEnum === CSSUnitEnum::FLEX_fr,
			'frequency' => $unitCategory === CSSUnitTypeEnum::FREQ,
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
				$lower = $comp['lower'] ?? '';
				if ($lower === 'currentcolor'
					|| CSSColorValue::isNamedColorIdentInternal($lower)
					|| isset(self::SYSTEM_COLORS_MAP[$lower])
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
				if ($fn !== 'color-mix'
					&& $fn !== 'light-dark'
					&& ($open = strpos($slice, '(')) !== false
					&& !CSSColorValue::hasLeadingFromKeywordInternal($slice, $open + 1, strlen($slice) - 1)
				) {
					CSSColorValue::parseLeafFunctionInternal($fn, $slice, $options, $mathBudget);
				} else {
					CSSColorValue::parseInternal($slice, null, $options, null, true);
				}
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

		return CSSColorValue::isNamedColorIdentInternal($comp['lower'] ?? '') ? [$pos + 1] : [];
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
		$lower = $comp['lower'] ?? '';

		return (self::SYSTEM_COLORS_MAP[$lower] ?? null) === !$deprecated ? [$pos + 1] : [];
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
			return self::hasValidCommaSeparatedArgs($args) ? [$pos + 1] : [];
		}

		return [];
	}

	/**
	 * @param list<ParsedComponent> $args
	 */
	private static function hasValidCommaSeparatedArgs(array $args): bool
	{
		$hasItem = false;
		foreach ($args as $comp) {
			if (($comp['kind'] ?? '') === 'token' && ($comp['tokenType'] ?? null) === CSSTokenType::Comma) {
				if (!$hasItem) {
					return false;
				}
				$hasItem = false;
				continue;
			}
			$hasItem = true;
		}

		return $hasItem;
	}

	/**
	 * @param ParsedComponent $comp
	 * @param array<string, true> $excludedKeywords
	 * @return list<int>
	 */
	private static function matchCustomIdent(array $comp, int $pos, array $excludedKeywords): array
	{
		if ($comp['kind'] !== 'token' || ($comp['tokenType'] ?? null) !== CSSTokenType::Ident) {
			return [];
		}
		$lower = $comp['lower'] ?? '';
		if (isset(self::CUSTOM_IDENT_EXCLUDED[$lower]) || isset($excludedKeywords[$lower])) {
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
