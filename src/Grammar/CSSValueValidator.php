<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Grammar;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStandardProperties;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Unified `@internal` facade for CSS property value grammar validation across
 * `CSS::supports()`, `CSSStyleDeclaration`, `CSSStyleValue`, `StylePropertyMap`,
 * and `CSS::registerProperty()`.
 *
 * @internal
 */
final class CSSValueValidator
{
	private const int MAX_CACHE_ENTRIES = 2_048;
	private const int MAX_CACHED_VALUE_BYTES = 160;
	private const int MAX_CUSTOM_SYNTAX_CACHE = 256;
	private const int MAX_CACHED_SYNTAX_BYTES = 512;
	private const string ASCII_KEYWORD_CHARS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-';

	/** @var array<string, true> */
	private const array FAST_COLOR_PROPERTIES = [
		'color' => true,
		'background-color' => true,
		'border-top-color' => true,
		'border-right-color' => true,
		'border-bottom-color' => true,
		'border-left-color' => true,
		'outline-color' => true,
		'text-decoration-color' => true,
		'column-rule-color' => true,
		'caret-color' => true,
		'accent-color' => true,
		'fill' => true,
		'stroke' => true,
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

	/** @var array<string, bool> */
	private static array $validationCache = [];

	/** @var array<string, CSSGrammarNode> */
	private static array $compiledCustomSyntaxes = [];

	public static function validateStandardProperty(
		string $normalizedProperty,
		string $trimmedValue,
		?CSSParserOptions $options = null,
	): bool {
		if ($trimmedValue === '' || !CSSPropertyGrammarRegistry::isKnown($normalizedProperty)) {
			return false;
		}

		$options ??= CSSParserOptions::default();
		$valLen = strlen($trimmedValue);
		if ($options->maxBytes !== null && $valLen > $options->maxBytes) {
			return false;
		}

		// O(1) fast path for simple single ASCII keyword values (including CSS-wide keywords)
		if (strspn($trimmedValue, self::ASCII_KEYWORD_CHARS) === $valLen) {
			$lower = strtolower($trimmedValue);
			if (isset(CSSValueComponentParser::CSS_WIDE_KEYWORDS[$lower])) {
				return true;
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

		// Allow caching flat values (no nested blocks/functions) even under non-default depth options
		$isFlat = !str_contains($trimmedValue, '(') && !str_contains($trimmedValue, '[') && !str_contains($trimmedValue, '{');
		$canCache = ($isFlat
				|| ($options->maxDepth === CSSParserOptions::DEFAULT_MAX_DEPTH && $options->maxMathDepth === CSSParserOptions::DEFAULT_MAX_MATH_DEPTH))
			&& $valLen <= self::MAX_CACHED_VALUE_BYTES
			&& !str_contains($trimmedValue, '/*')
			&& !str_contains($trimmedValue, '\\');

		$cacheKey = null;
		if ($canCache) {
			$cacheKey = $normalizedProperty . "\0" . $trimmedValue;
			if (isset(self::$validationCache[$cacheKey])) {
				return self::$validationCache[$cacheKey];
			}
		}

		$parsed = CSSValueComponentParser::parse($trimmedValue, $options);
		if ($parsed === null) {
			self::storeCache($cacheKey, false);
			return false;
		}

		if ($parsed['isCssWideKeyword'] || $parsed['hasSubstitution']) {
			self::storeCache($cacheKey, true);
			return true;
		}

		$grammar = CSSPropertyGrammarRegistry::getPropertyGrammar($normalizedProperty);
		$valid = $grammar !== null && CSSGrammarMatcher::matches(
			$grammar,
			$parsed['source'],
			$parsed['hasComments'],
			$parsed['components'],
			$options,
		);

		if ($valid && ($normalizedProperty === 'transition' || $normalizedProperty === 'animation')) {
			$valid = self::validateTimeOrdering($parsed['source'], $parsed['components'], $options);
		}

		self::storeCache($cacheKey, $valid);

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
				return false;
			}
			if (!$validateRegisteredCustom) {
				if ($trimmedValue === '') {
					return true;
				}
				return CSSValueComponentParser::parse($trimmedValue, $options, true) !== null;
			}

			$rule = CSS::getRegisteredProperty($normalizedProperty);
			if ($rule === null) {
				if ($trimmedValue === '') {
					return true;
				}
				return CSSValueComponentParser::parse($trimmedValue, $options, true) !== null;
			}

			return self::validateRegisteredCustomProperty($rule->getSyntax(), $trimmedValue, false, $options);
		}

		if (CSSStandardProperties::isKnown($normalizedProperty)) {
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
			return false;
		}

		$parsed = CSSValueComponentParser::parse($trimmedValue, $options);
		if ($parsed === null) {
			return false;
		}

		if ($isInitialValue) {
			if ($parsed['isCssWideKeyword'] || $parsed['hasSubstitution']) {
				return false;
			}
			if (self::hasComputationallyDependentConstruct($parsed['source'], $parsed['components'])) {
				return false;
			}
		} else {
			if ($parsed['isCssWideKeyword'] || $parsed['hasSubstitution']) {
				return true;
			}
		}

		$dedupSyntax = self::deduplicateCustomSyntaxAlternates($syntax);

		try {
			if (!$isInitialValue && strlen($dedupSyntax) <= self::MAX_CACHED_SYNTAX_BYTES) {
				if (isset(self::$compiledCustomSyntaxes[$dedupSyntax])) {
					$grammar = self::$compiledCustomSyntaxes[$dedupSyntax];
				} else {
					$grammar = CSSGrammarParser::parse($dedupSyntax, true);
					if (count(self::$compiledCustomSyntaxes) >= self::MAX_CUSTOM_SYNTAX_CACHE) {
						self::$compiledCustomSyntaxes = array_slice(self::$compiledCustomSyntaxes, (int) (self::MAX_CUSTOM_SYNTAX_CACHE / 2), null, true);
					}
					self::$compiledCustomSyntaxes[$dedupSyntax] = $grammar;
				}
			} else {
				$grammar = CSSGrammarParser::parse($dedupSyntax, true);
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
		self::$compiledCustomSyntaxes = [];
	}

	/** @internal */
	public static function clearCustomSyntaxCacheInternal(): void
	{
		self::$compiledCustomSyntaxes = [];
	}

	private static function tryFastSingleTokenMatch(string $normalizedProperty, string $value, int $valLen): ?bool
	{
		// Only consider simple flat single-token candidates (no whitespace, comments, parens, quotes, commas, escapes)
		if (strcspn($value, " \t\n\r\f/*\\()[]{}\"',;") !== $valLen) {
			return null;
		}

		if (isset(self::FAST_COLOR_PROPERTIES[$normalizedProperty])) {
			if ($value[0] === '#') {
				return CSSColorValue::isValidHexInternal(substr($value, 1));
			}
			if (strspn($value, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ') === $valLen) {
				$lower = strtolower($value);
				return CSSColorValue::isNamedColorIdentInternal($lower)
					|| $lower === 'currentcolor'
					|| CSSDataTypeMatcher::isSystemOrDeprecatedColor($lower);
			}
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
		if (!is_numeric($numStr)) {
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
		if (!is_numeric($numStr)) {
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
	 * in each comma-separated item, the first `<time>` component IS the duration and MUST be `>= 0`.
	 *
	 * @param list<mixed> $components
	 */
	private static function validateTimeOrdering(string $source, array $components, CSSParserOptions $options): bool
	{
		$checkingFirstTime = true;
		foreach ($components as $comp) {
			if (!is_array($comp)) {
				continue;
			}
			$kind = $comp['kind'] ?? '';
			if ($kind === 'token' && ($comp['tokenType'] ?? null) === CSSTokenType::Comma) {
				$checkingFirstTime = true;
				continue;
			}
			if (!$checkingFirstTime) {
				continue;
			}
			if ($kind === 'token' && ($comp['tokenType'] ?? null) === CSSTokenType::Dimension) {
				$info = CSSValueComponentParser::decodeNumericToken($source, $comp);
				$u = strtolower($info['unit']);
				if ($u === 's' || $u === 'ms') {
					if ($info['value'] < 0.0) {
						return false;
					}
					$checkingFirstTime = false;
				}
				continue;
			}
			if ($kind === 'function' && CSSCalcParser::isMathFunctionName((string) ($comp['name'] ?? ''))) {
				$slice = substr($source, (int) ($comp['start'] ?? 0), ((int) ($comp['end'] ?? 0)) - ((int) ($comp['start'] ?? 0)));
				try {
					$mathVal = CSSCalcParser::parseUnitOrMathSlice($slice, 0, strlen($slice), $options, 0, true);
					if ($mathVal !== null) {
						$dims = $mathVal->getNumericTypeInternal()->toArray();
						if ((int) ($dims['time'] ?? 0) === 1 && ($mathVal->getNumericTypeInternal()->percentHint === null)) {
							if ($mathVal instanceof CSSUnitValue && $mathVal->value < 0.0) {
								return false;
							}
							$checkingFirstTime = false;
						}
					}
				} catch (CSSParserLimitException $e) {
					throw $e;
				} catch (InvalidArgumentException) {
					// Already validated by grammar matcher
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
			if ($trimmed !== '' && !isset($seen[$trimmed])) {
				$seen[$trimmed] = true;
				$unique[] = $trimmed;
			}
		}

		return $unique === [] ? $syntax : implode(' | ', $unique);
	}

	private static function storeCache(?string $key, bool $valid): void
	{
		if ($key === null) {
			return;
		}
		if (count(self::$validationCache) >= self::MAX_CACHE_ENTRIES) {
			self::$validationCache = array_slice(self::$validationCache, (int) (self::MAX_CACHE_ENTRIES / 2), null, true);
		}
		self::$validationCache[$key] = $valid;
	}

	/**
	 * Iteratively walks component values to detect any font-, viewport-, or container-relative length unit
	 * or computationally dependent function such as `light-dark()`.
	 *
	 * @param list<mixed> $components
	 */
	private static function hasComputationallyDependentConstruct(string $source, array $components): bool
	{
		$stack = $components;
		while ($stack !== []) {
			$comp = array_pop($stack);
			if (!is_array($comp)) {
				continue;
			}
			$kind = $comp['kind'] ?? '';
			if ($kind === 'token') {
				if (($comp['tokenType'] ?? null) === CSSTokenType::Dimension) {
					$info = CSSValueComponentParser::decodeNumericToken($source, $comp);
					if (CSSDataTypeMatcher::isRelativeLengthUnit(strtolower($info['unit']))) {
						return true;
					}
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
