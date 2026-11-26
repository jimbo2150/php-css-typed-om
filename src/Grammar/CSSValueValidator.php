<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Grammar;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;

/**
 * Unified `@internal` facade for CSS property value grammar validation across
 * `CSS::supports()`, `CSSStyleDeclaration`, `CSSStyleValue`, `StylePropertyMap`,
 * and `CSS::registerProperty()`.
 *
 * @phpstan-import-type ParsedResult from CSSValueComponentParser
 * @internal
 */
final class CSSValueValidator
{
	private const int MAX_CACHE_ENTRIES = 2_048;
	private const int MAX_CACHED_VALUE_BYTES = 160;
	private const int MAX_CACHED_SYNTAX_BYTES = 512;
	private const string ASCII_KEYWORD_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-';

	/** @var array<string, bool> `false` = color plus additional keywords (`outline-color`, `fill`, ...) */
	private const array EXTRA_COLOR_PROPERTIES = [
		'outline-color' => false,
		'caret-color' => false,
		'accent-color' => false,
		'fill' => false,
		'stroke' => false,
	];

	/** @var array<string, bool> `true` = allow negative values, `false` = non-negative `[0, ∞]` */
	private const array FAST_LENGTH_PERCENTAGE_PROPERTIES = [
		'width' => false,
		'height' => false,
		'min-width' => false,
		'min-height' => false,
		'max-width' => false,
		'max-height' => false,
		'inline-size' => false,
		'block-size' => false,
		'min-inline-size' => false,
		'min-block-size' => false,
		'max-inline-size' => false,
		'max-block-size' => false,
		'padding-top' => false,
		'padding-right' => false,
		'padding-bottom' => false,
		'padding-left' => false,
		'padding-inline-start' => false,
		'padding-inline-end' => false,
		'padding-block-start' => false,
		'padding-block-end' => false,
		'margin-top' => true,
		'margin-right' => true,
		'margin-bottom' => true,
		'margin-left' => true,
		'margin-inline-start' => true,
		'margin-inline-end' => true,
		'margin-block-start' => true,
		'margin-block-end' => true,
		'top' => true,
		'right' => true,
		'bottom' => true,
		'left' => true,
		'font-size' => false,
		'gap' => false,
		'row-gap' => false,
		'column-gap' => false,
		'flex-basis' => false,
	];

	/** @var array<string, array<string, bool>> 2-level cache `[property][value] = bool` */
	private static array $validationCache = [];

	private static int $validationCacheCount = 0;

	/**
	 * @param ?ParsedResult $parsedOut
	 * @param-out ?ParsedResult $parsedOut
	 */
	public static function validateStandardProperty(
		string $normalizedProperty,
		string $trimmedValue,
		?CSSParserOptions $options = null,
		?array &$parsedOut = null,
	): bool {
		$parsedOut = null;
		if ($trimmedValue === '' || !CSSPropertyGrammarRegistry::isKnown($normalizedProperty)) {
			return false;
		}

		$options ??= CSSParserOptions::default();
		$valLen = strlen($trimmedValue);
		if ($options->maxBytes !== null && $valLen > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property value exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		// O(1) fast path for simple single ASCII keyword values (including CSS-wide & exhaustive pure-keyword sets)
		if (strspn($trimmedValue, self::ASCII_KEYWORD_CHARS) === $valLen) {
			$lower = strtolower($trimmedValue);
			if (isset(CSSValueComponentParser::CSS_WIDE_KEYWORDS[$lower])) {
				return true;
			}
			$pureKeywords = CSSPropertyGrammarRegistry::getPureKeywordSet($normalizedProperty);
			if ($pureKeywords !== null) {
				return isset($pureKeywords[$lower]);
			}
			if (CSSPropertyGrammarRegistry::matchesFastKeyword($normalizedProperty, $lower)) {
				return true;
			}
		}

		// O(1) fast path for simple single-token flat values (#hex, named color, 10px, 50%, opacity, z-index, font-weight)
		$fastResult = self::tryFastSingleTokenMatch($normalizedProperty, $trimmedValue, $valLen);
		if ($fastResult !== null) {
			return $fastResult;
		}

		// Check 2-level cache BEFORE strcspn/str_contains on cache hits (Guard B1: only flat values hit cache under non-default depth).
		$isDefaultDepth = $options->maxDepth === CSSParserOptions::DEFAULT_MAX_DEPTH
			&& $options->maxMathDepth === CSSParserOptions::DEFAULT_MAX_MATH_DEPTH;
		if (isset(self::$validationCache[$normalizedProperty][$trimmedValue])
			&& ($isDefaultDepth || strcspn($trimmedValue, '([{') === $valLen)
		) {
			return self::$validationCache[$normalizedProperty][$trimmedValue];
		}

		$canCache = $valLen <= self::MAX_CACHED_VALUE_BYTES
			&& ($isDefaultDepth || strcspn($trimmedValue, '([{') === $valLen)
			&& !str_contains($trimmedValue, '/*')
			&& !str_contains($trimmedValue, '\\');

		$parsed = CSSValueComponentParser::parse($trimmedValue, $options);
		if ($parsed === null) {
			if ($canCache) {
				self::storeCache($normalizedProperty, $trimmedValue, false);
			}
			return false;
		}

		$parsedOut = $parsed;

		if ($parsed['isCssWideKeyword'] || $parsed['hasSubstitution']) {
			if ($canCache) {
				self::storeCache($normalizedProperty, $trimmedValue, true);
			}
			return true;
		}

		$grammar = CSSPropertyGrammarRegistry::getPropertyGrammar($normalizedProperty);
		$evalCache = [];
		$mathBudget = 0;
		$valid = $grammar !== null && CSSGrammarMatcher::matches(
			$grammar,
			$parsed['source'],
			$parsed['hasComments'],
			$parsed['components'],
			$options,
			evalCacheOut: $evalCache,
			mathBudgetOut: $mathBudget,
		);

		if ($valid && ($normalizedProperty === 'transition' || $normalizedProperty === 'animation')) {
			$valid = self::validateTimeOrdering($normalizedProperty, $parsed['source'], $parsed['components'], $options, $evalCache, $mathBudget);
		}

		if ($canCache) {
			self::storeCache($normalizedProperty, $trimmedValue, $valid);
		}

		return $valid;
	}

	/**
	 * Validates any CSS property value:
	 * - Standard properties: full W3C VDS grammar check
	 * - Registered `--*` properties (when `$validateRegisteredCustom` is true): registered syntax check
	 * - Unregistered `--*` / unknown / vendor-prefixed properties: structural component check
	 */
	public static function validatePropertyValue(
		string $normalizedProperty,
		string $trimmedValue,
		?CSSParserOptions $options = null,
		bool $validateRegisteredCustom = false,
	): bool {
		if (str_starts_with($normalizedProperty, '--')) {
			if ($trimmedValue !== '' && $options !== null && $options->maxBytes !== null && strlen($trimmedValue) > $options->maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS property value exceeds maximum size of %d bytes.', $options->maxBytes));
			}
			if (!$validateRegisteredCustom) {
				return $trimmedValue === '' || CSSValueComponentParser::parse($trimmedValue, $options, true) !== null;
			}

			$syntax = CSS::getRegisteredPropertySyntaxInternal($normalizedProperty);
			if ($syntax === null) {
				return $trimmedValue === '' || CSSValueComponentParser::parse($trimmedValue, $options, true) !== null;
			}

			return self::validateRegisteredCustomProperty($syntax, $trimmedValue, false, $options);
		}

		if (CSSPropertyGrammarRegistry::isKnown($normalizedProperty)) {
			return self::validateStandardProperty($normalizedProperty, $trimmedValue, $options);
		}

		// Unknown / vendor-prefixed property: structural component-value check only (Decision A2)
		if ($trimmedValue === '') {
			return false;
		}

		return CSSValueComponentParser::parse($trimmedValue, $options) !== null;
	}

	/**
	 * Validates a value against a CSS Properties & Values Level 1 custom property syntax string.
	 * When `$isInitialValue` is true, enforces computational independence per §2.3.
	 */
	public static function validateRegisteredCustomProperty(
		string $syntax,
		string $trimmedValue,
		bool $isInitialValue = false,
		?CSSParserOptions $options = null,
	): bool {
		$options ??= CSSParserOptions::default();

		if ($syntax === '*') {
			if ($trimmedValue === '') {
				return true;
			}
			$parsed = CSSValueComponentParser::parse($trimmedValue, $options, true);
			if ($parsed === null) {
				return false;
			}
			if ($isInitialValue) {
				return !$parsed['isCssWideKeyword'] && !$parsed['hasSubstitution'];
			}

			return true;
		}

		if ($trimmedValue === '') {
			return false;
		}
		if ($options->maxBytes !== null && strlen($trimmedValue) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property value exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		$parsed = CSSValueComponentParser::parse($trimmedValue, $options);
		if ($parsed === null) {
			return false;
		}

		if ($isInitialValue) {
			if ($parsed['isCssWideKeyword'] || $parsed['hasSubstitution']) {
				return false;
			}
			if (self::hasComputationallyDependentConstruct($parsed['components'])) {
				return false;
			}
		} else {
			if ($parsed['isCssWideKeyword'] || $parsed['hasSubstitution']) {
				return true;
			}
		}

		try {
			if (!$isInitialValue && CSSPropertyGrammarRegistry::hasCachedGrammar($syntax, true)) {
				$grammar = CSSPropertyGrammarRegistry::getGrammar($syntax, true);
			} else {
				$dedupSyntax = self::deduplicateCustomSyntaxAlternates($syntax);
				$grammar = (!$isInitialValue && strlen($dedupSyntax) <= self::MAX_CACHED_SYNTAX_BYTES)
					? CSSPropertyGrammarRegistry::getGrammar($dedupSyntax, true)
					: CSSGrammarParser::parse($dedupSyntax, true);
			}
		} catch (InvalidArgumentException) {
			return false;
		}

		return CSSGrammarMatcher::matches(
			$grammar,
			$parsed['source'],
			$parsed['hasComments'],
			$parsed['components'],
			$options,
		);
	}

	public static function isListValuedProperty(string $normalizedProperty): bool
	{
		return CSSPropertyGrammarRegistry::isListValued($normalizedProperty);
	}

	/** @internal */
	public static function clearCacheInternal(): void
	{
		self::$validationCache = [];
		self::$validationCacheCount = 0;
		CSSPropertyGrammarRegistry::clearCustomGrammarCacheInternal();
	}

	/** @internal */
	public static function clearCustomSyntaxCacheInternal(): void
	{
		CSSPropertyGrammarRegistry::clearCustomGrammarCacheInternal();
	}

	private static function tryFastSingleTokenMatch(string $normalizedProperty, string $value, int $valLen): ?bool
	{
		$isPureColor = CSSPropertyGrammarRegistry::isPureColorProperty($normalizedProperty);
		if ($isPureColor || isset(self::EXTRA_COLOR_PROPERTIES[$normalizedProperty])) {
			if ($value[0] === '#') {
				return CSSColorValue::isValidHexInternal(substr($value, 1));
			}
			if (strspn($value, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') === $valLen) {
				$lower = strtolower($value);
				if (CSSColorValue::isNamedColorIdentInternal($lower)
					|| $lower === 'currentcolor'
					|| CSSDataTypeMatcher::isSystemOrDeprecatedColor($lower)
				) {
					return true;
				}
				return $isPureColor ? false : null;
			}
			return null;
		}

		// Only consider simple flat single-token candidates (no whitespace, comments, parens, quotes, commas, escapes)
		if (strcspn($value, " \t\n\r\f/*\\()[]{}\"',;") !== $valLen) {
			return null;
		}

		if (isset(self::FAST_LENGTH_PERCENTAGE_PROPERTIES[$normalizedProperty])) {
			return self::matchFastSingleLengthPercentage($value, $valLen, self::FAST_LENGTH_PERCENTAGE_PROPERTIES[$normalizedProperty]);
		}

		if ($normalizedProperty === 'opacity') {
			return self::matchFastAlphaOrNumber($value, $valLen, true);
		}

		if ($normalizedProperty === 'z-index' || $normalizedProperty === 'order') {
			$start = ($value[0] === '+' || $value[0] === '-') ? 1 : 0;
			if ($start < $valLen && ctype_digit(substr($value, $start))) {
				return true;
			}
			return null;
		}

		if ($normalizedProperty === 'font-weight') {
			$numResult = self::matchFastAlphaOrNumber($value, $valLen, false);
			if ($numResult !== null) {
				if (!$numResult) {
					return false;
				}
				$f = (float) $value;
				return $f >= 1.0 && $f <= 1000.0;
			}
		}

		return null;
	}

	private static function matchFastSingleLengthPercentage(string $value, int $valLen, bool $allowNegative): ?bool
	{
		$numLen = strspn($value, '+-0123456789.');
		if ($numLen === 0) {
			return null;
		}
		$numStr = substr($value, 0, $numLen);
		if ($numStr[$numLen - 1] === '.' || !is_numeric($numStr)) {
			return null;
		}
		$val = (float) $numStr;
		if ($numLen === $valLen) {
			if ($val === 0.0) {
				return true;
			}
			return false;
		}
		$unit = strtolower(substr($value, $numLen));
		if ($unit === '%') {
			return $allowNegative || $val >= 0.0;
		}
		// Let scientific notation fall through to the full component parser
		if ($unit[0] === 'e' && strlen($unit) > 1 && ($unit[1] === '+' || $unit[1] === '-' || ($unit[1] >= '0' && $unit[1] <= '9'))) {
			return null;
		}
		if (!ctype_alpha($unit)) {
			return null;
		}
		if (!CSSDataTypeMatcher::isKnownLengthUnitInternal($unit)) {
			return false;
		}

		return $allowNegative || $val >= 0.0;
	}

	private static function matchFastAlphaOrNumber(string $value, int $valLen, bool $allowPercentage): ?bool
	{
		$numLen = strspn($value, '+-0123456789.');
		if ($numLen === 0) {
			return null;
		}
		$numStr = substr($value, 0, $numLen);
		if ($numStr[$numLen - 1] === '.' || !is_numeric($numStr)) {
			return null;
		}
		if ($numLen === $valLen) {
			return true;
		}
		if ($allowPercentage && $numLen === $valLen - 1 && $value[$numLen] === '%') {
			return true;
		}

		return null;
	}

	/**
	 * Enforces CSS Transitions/Animations duration-before-delay ordering rule:
	 * in each comma-separated item, the first `<time>` (or `auto` on `animation`) is duration (`>= 0`).
	 * Negative simplified `calc()` results remain parse-valid per CSS Values 4 §10.9.
	 *
	 * @param list<mixed> $components
	 * @param array<string, mixed> $evalCache
	 */
	private static function validateTimeOrdering(
		string $normalizedProperty,
		string $source,
		array $components,
		CSSParserOptions $options,
		array &$evalCache,
		int &$mathBudget,
	): bool {
		$checkingFirstTime = true;
		$allowAutoDuration = $normalizedProperty === 'animation';
		foreach ($components as $comp) {
			if (!is_array($comp)) {
				continue;
			}
			$kind = $comp['kind'] ?? '';
			if ($kind === 'token') {
				$tokType = $comp['tokenType'] ?? null;
				if ($tokType === CSSTokenType::Comma) {
					$checkingFirstTime = true;
					continue;
				}
				if (!$checkingFirstTime) {
					continue;
				}
				if ($allowAutoDuration && $tokType === CSSTokenType::Ident && ($comp['lower'] ?? '') === 'auto') {
					$checkingFirstTime = false;
					continue;
				}
				if ($tokType === CSSTokenType::Dimension) {
					$u = $comp['unitLower'] ?? '';
					if ($u === 's' || $u === 'ms') {
						if (($comp['numValue'] ?? 0.0) < 0.0) {
							return false;
						}
						$checkingFirstTime = false;
					}
				}
				continue;
			}
			if (!$checkingFirstTime) {
				continue;
			}
			if ($kind === 'function' && CSSCalcParser::isMathFunctionName((string) ($comp['name'] ?? ''))) {
				$evaluated = CSSDataTypeMatcher::evaluateMathComponentInternal($source, $comp, $options, $evalCache, $mathBudget);
				if ($evaluated !== null && $evaluated[0] === 'time' && $evaluated[3] === null) {
					$checkingFirstTime = false;
				}
			}
		}

		return true;
	}

	private static function deduplicateCustomSyntaxAlternates(string $syntax): string
	{
		if (!str_contains($syntax, '|')) {
			return $syntax;
		}
		$parts = explode('|', $syntax);
		$seen = [];
		$unique = [];
		foreach ($parts as $part) {
			$trimmed = trim($part);
			if ($trimmed === '') {
				continue;
			}
			if (str_starts_with($trimmed, '<')) {
				$trimmed = strtolower($trimmed);
			}
			if (!isset($seen[$trimmed])) {
				$seen[$trimmed] = true;
				$unique[] = $trimmed;
			}
		}

		return $unique === [] ? $syntax : implode(' | ', $unique);
	}

	private static function storeCache(string $property, string $value, bool $valid): void
	{
		if (self::$validationCacheCount >= self::MAX_CACHE_ENTRIES) {
			self::$validationCache = [];
			self::$validationCacheCount = 0;
		}
		if (!isset(self::$validationCache[$property][$value])) {
			self::$validationCacheCount++;
		}
		self::$validationCache[$property][$value] = $valid;
	}

	/**
	 * Iteratively walks component values to detect any font-, viewport-, or container-relative length unit
	 * or computationally dependent function such as `light-dark()`.
	 *
	 * @param list<mixed> $components
	 */
	private static function hasComputationallyDependentConstruct(array $components): bool
	{
		$stack = $components;
		while ($stack !== []) {
			$comp = array_pop($stack);
			if (!is_array($comp)) {
				continue;
			}
			$kind = $comp['kind'] ?? '';
			if ($kind === 'token') {
				if (($comp['tokenType'] ?? null) === CSSTokenType::Dimension && CSSDataTypeMatcher::isRelativeLengthUnit((string) ($comp['unitLower'] ?? ''))) {
					return true;
				}
				continue;
			}
			if ($kind === 'function' && ((string) ($comp['name'] ?? '')) === 'light-dark') {
				return true;
			}
			$children = $comp['components'] ?? [];
			if (is_array($children)) {
				foreach ($children as $child) {
					$stack[] = $child;
				}
			}
		}

		return false;
	}
}
