<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM\Shorthand;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSGrammarMatcher;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSGrammarNode;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSGrammarParser;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSPropertyGrammarRegistry;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueComponentParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;

/**
 * Shared iterative component-slice, grammar-matching, and canonical collapse helpers
 * used across CSSOM shorthand codecs.
 *
 * @phpstan-import-type ParsedComponent from CSSValueComponentParser
 * @phpstan-import-type ParsedResult from CSSValueComponentParser
 * @internal
 */
final class ShorthandCodecHelper
{
	public const array LINE_STYLES = [
		'none' => true,
		'hidden' => true,
		'dotted' => true,
		'dashed' => true,
		'solid' => true,
		'double' => true,
		'groove' => true,
		'ridge' => true,
		'inset' => true,
		'outset' => true,
	];

	public const array LINE_WIDTH_KEYWORDS = [
		'thin' => true,
		'medium' => true,
		'thick' => true,
	];

	private const int MAX_GRAMMAR_CACHE = 128;

	/** @var array<string, CSSGrammarNode> */
	private static array $compiledGrammars = [];

	/**
	 * @param list<ParsedComponent> $components
	 */
	public static function sliceComponents(
		string $source,
		array $components,
		int $startIdx,
		int $endIdx,
		bool $hasComments,
	): string {
		if ($startIdx >= $endIdx || !isset($components[$startIdx], $components[$endIdx - 1])) {
			return '';
		}
		$start = $components[$startIdx]['start'];
		$end = $components[$endIdx - 1]['end'];
		$slice = $hasComments
			? CSSValueComponentParser::sliceClean($source, $start, $end, true)
			: substr($source, $start, $end - $start);

		return str_contains($slice, '\\')
			? CSSSyntaxScanner::trimCssWhitespace($slice)
			: trim($slice, " \t\n\r\f");
	}

	/**
	 * @param ParsedComponent $component
	 */
	public static function componentText(string $source, array $component, bool $hasComments): string
	{
		$start = $component['start'];
		$end = $component['end'];
		$slice = $hasComments
			? CSSValueComponentParser::sliceClean($source, $start, $end, true)
			: substr($source, $start, $end - $start);

		return str_contains($slice, '\\')
			? CSSSyntaxScanner::trimCssWhitespace($slice)
			: trim($slice, " \t\n\r\f");
	}

	/**
	 * Joins a slice of components with single ASCII spaces (no retained comments).
	 *
	 * @param list<ParsedComponent> $components
	 */
	public static function joinComponents(string $source, array $components, bool $hasComments): string
	{
		$parts = [];
		foreach ($components as $comp) {
			$parts[] = self::componentText($source, $comp, $hasComments);
		}

		return implode(' ', $parts);
	}

	/**
	 * Splits `$components` on top-level comma or delimiter tokens, rejecting empty segments.
	 *
	 * @param list<ParsedComponent> $components
	 * @return ?list<list<ParsedComponent>>
	 */
	public static function splitByDelimiter(
		array $components,
		string $source,
		CSSTokenType $tokenType,
		?string $delimChar = null,
	): ?array {
		$segments = [];
		$current = [];
		foreach ($components as $comp) {
			$isMatch = ($comp['kind'] ?? '') === 'token'
				&& ($comp['tokenType'] ?? null) === $tokenType
				&& ($delimChar === null || substr($source, $comp['start'], $comp['end'] - $comp['start']) === $delimChar);
			if ($isMatch) {
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
	 */
	public static function identLower(array $comp): ?string
	{
		if (($comp['kind'] ?? '') !== 'token' || ($comp['tokenType'] ?? null) !== CSSTokenType::Ident) {
			return null;
		}

		return strtolower((string) ($comp['name'] ?? ''));
	}

	/**
	 * @param ParsedComponent $comp
	 */
	public static function isIdent(array $comp, string $expectedLower): bool
	{
		return self::identLower($comp) === $expectedLower;
	}

	/**
	 * @param ParsedComponent $comp
	 */
	public static function isDelim(array $comp, string $source, string $char): bool
	{
		return ($comp['kind'] ?? '') === 'token'
			&& ($comp['tokenType'] ?? null) === CSSTokenType::Delim
			&& ($comp['end'] - $comp['start']) === strlen($char)
			&& substr($source, $comp['start'], $comp['end'] - $comp['start']) === $char;
	}

	/**
	 * @param list<ParsedComponent> $sliceComponents
	 */
	public static function matchesProperty(
		string $property,
		string $source,
		bool $hasComments,
		array $sliceComponents,
		CSSParserOptions $options,
	): bool {
		if ($sliceComponents === []) {
			return false;
		}
		$node = CSSPropertyGrammarRegistry::getPropertyGrammar($property);

		return $node !== null && CSSGrammarMatcher::matches($node, $source, $hasComments, $sliceComponents, $options);
	}

	/**
	 * @param list<ParsedComponent> $sliceComponents
	 */
	public static function matchesNonTerminal(
		string $nonTerminal,
		string $source,
		bool $hasComments,
		array $sliceComponents,
		CSSParserOptions $options,
	): bool {
		if ($sliceComponents === []) {
			return false;
		}
		$node = CSSPropertyGrammarRegistry::resolveNonTerminal($nonTerminal);

		return $node !== null && CSSGrammarMatcher::matches($node, $source, $hasComments, $sliceComponents, $options);
	}

	/**
	 * @param list<ParsedComponent> $sliceComponents
	 */
	public static function matchesGrammar(
		string $grammarExpr,
		string $source,
		bool $hasComments,
		array $sliceComponents,
		CSSParserOptions $options,
	): bool {
		if ($sliceComponents === []) {
			return false;
		}
		if (!isset(self::$compiledGrammars[$grammarExpr])) {
			if (count(self::$compiledGrammars) >= self::MAX_GRAMMAR_CACHE) {
				self::$compiledGrammars = array_slice(self::$compiledGrammars, (int) (self::MAX_GRAMMAR_CACHE / 2), null, true);
			}
			self::$compiledGrammars[$grammarExpr] = CSSGrammarParser::parse($grammarExpr);
		}

		return CSSGrammarMatcher::matches(self::$compiledGrammars[$grammarExpr], $source, $hasComments, $sliceComponents, $options);
	}

	/**
	 * @param ParsedComponent $comp
	 */
	public static function matchesLineWidth(
		string $source,
		bool $hasComments,
		array $comp,
		CSSParserOptions $options,
	): bool {
		$ident = self::identLower($comp);
		if ($ident !== null) {
			return isset(self::LINE_WIDTH_KEYWORDS[$ident]);
		}

		return self::matchesNonTerminal('line-width', $source, $hasComments, [$comp], $options);
	}

	/**
	 * @param ParsedComponent $comp
	 */
	public static function matchesLineStyle(array $comp): bool
	{
		$ident = self::identLower($comp);

		return $ident !== null && isset(self::LINE_STYLES[$ident]);
	}

	/**
	 * @param ParsedComponent $comp
	 */
	public static function matchesColor(
		string $source,
		bool $hasComments,
		array $comp,
		CSSParserOptions $options,
	): bool {
		return self::matchesGrammar('<color>', $source, $hasComments, [$comp], $options);
	}

	public static function collapseBox4(string $top, string $right, string $bottom, string $left): string
	{
		if ($left === $right) {
			if ($bottom === $top) {
				return $right === $top ? $top : $top . ' ' . $right;
			}

			return $top . ' ' . $right . ' ' . $bottom;
		}

		return $top . ' ' . $right . ' ' . $bottom . ' ' . $left;
	}

	public static function collapsePair2(string $first, string $second): string
	{
		return $second === $first ? $first : $first . ' ' . $second;
	}

	public static function isInitial(string $longhand, string $value): bool
	{
		return $value === CSSShorthandRegistry::getInitialValue($longhand);
	}

	/**
	 * @return ?ParsedResult
	 */
	public static function parseValue(string $value, CSSParserOptions $options): ?array
	{
		$parsed = CSSValueComponentParser::parse($value, $options);
		if ($parsed === null || $parsed['isCssWideKeyword'] || $parsed['hasSubstitution']) {
			return null;
		}

		return $parsed;
	}
}
