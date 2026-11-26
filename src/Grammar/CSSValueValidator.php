<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Grammar;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStandardProperties;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;

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

		// O(1) fast path for simple single ASCII keyword values when limits are non-negative
		if (preg_match('/^[a-zA-Z-]+$/D', $trimmedValue) === 1) {
			$lower = strtolower($trimmedValue);
			if (isset(CSSValueComponentParser::CSS_WIDE_KEYWORDS[$lower])) {
				return true;
			}
			if (CSSPropertyGrammarRegistry::matchesFastKeyword($normalizedProperty, $lower)) {
				return true;
			}
		}

		// Strictly gate static validation cache to default depth limits and small clean values
		$canCache = ($options->maxDepth === CSSParserOptions::DEFAULT_MAX_DEPTH)
			&& ($options->maxMathDepth === CSSParserOptions::DEFAULT_MAX_MATH_DEPTH)
			&& strlen($trimmedValue) <= self::MAX_CACHED_VALUE_BYTES
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

		$parsed = CSSValueComponentParser::parse($trimmedValue, $options);
		if ($parsed === null) {
			return false;
		}

		if ($isInitialValue) {
			if ($parsed['isCssWideKeyword'] || $parsed['hasSubstitution']) {
				return false;
			}
			if (self::containsRelativeLengthUnit($parsed['source'], $parsed['components'])) {
				return false;
			}
		} else {
			if ($parsed['isCssWideKeyword'] || $parsed['hasSubstitution']) {
				return true;
			}
		}

		try {
			$grammar = self::$compiledCustomSyntaxes[$syntax] ??= CSSGrammarParser::parse($syntax);
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
	 * Iteratively walks component values to detect any font-, viewport-, or container-relative length unit.
	 *
	 * @param list<mixed> $components
	 */
	private static function containsRelativeLengthUnit(string $source, array $components): bool
	{
		$stack = $components;
		while ($stack !== []) {
			$comp = array_pop($stack);
			if (!is_array($comp)) {
				continue;
			}
			if (($comp['kind'] ?? '') === 'token') {
				if (($comp['tokenType'] ?? null) === CSSTokenType::Dimension) {
					$rawUnit = substr($source, (int) ($comp['start'] ?? 0), ((int) ($comp['end'] ?? 0)) - ((int) ($comp['start'] ?? 0)));
					$info = CSSValueComponentParser::decodeNumericToken($source, $comp);
					if (CSSDataTypeMatcher::isRelativeLengthUnit(strtolower($info['unit']))) {
						return true;
					}
					unset($rawUnit);
				}
				continue;
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
