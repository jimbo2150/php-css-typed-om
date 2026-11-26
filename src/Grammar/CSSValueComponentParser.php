<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Grammar;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenizer;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

/**
 * Iterative CSS Syntax Level 3 §5.4 component-value tree parser built directly over
 * `CSSTokenizer::forParserInternal()`.
 *
 * Rejects bad tokens, control bytes, unclosed constructs, and validates arbitrary
 * substitution functions (`var()`, `env()`, `attr()`, `if()`, `first-valid()`).
 *
 * @phpstan-type ParsedComponent array{
 *   kind: 'token'|'function'|'paren'|'bracket'|'brace',
 *   tokenType?: CSSTokenType,
 *   name?: string,
 *   start: int,
 *   end: int,
 *   innerStart?: int,
 *   innerEnd?: int,
 *   components?: list<mixed>,
 *   hasEscapes?: bool
 * }
 * @phpstan-type ParsedResult array{
 *   source: string,
 *   components: list<ParsedComponent>,
 *   hasSubstitution: bool,
 *   isCssWideKeyword: bool,
 *   hasComments: bool,
 *   hasEscapes: bool
 * }
 *
 * @internal
 */
final class CSSValueComponentParser
{
	/** @var array<string, true> */
	public const array CSS_WIDE_KEYWORDS = [
		'initial' => true,
		'inherit' => true,
		'unset' => true,
		'revert' => true,
		'revert-layer' => true,
	];

	/** @var array<string, true> */
	public const array SUBSTITUTION_FUNCTIONS = [
		'var' => true,
		'env' => true,
		'attr' => true,
		'if' => true,
		'first-valid' => true,
	];

	private const int MAX_COMPONENT_TOKENS = 65_536;

	/**
	 * Parses `$value` iteratively into CSS Syntax L3 component values, or returns `null`
	 * when the input contains syntax errors (unclosed blocks/strings/comments, bad tokens, control bytes).
	 *
	 * @return ?ParsedResult
	 * @throws CSSParserLimitException When resource limits (`maxBytes`, `maxDepth`, `MAX_LIST_ITEMS`) are exceeded
	 */
	public static function parse(string $value, ?CSSParserOptions $options = null, bool $allowEmpty = false): ?array
	{
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($value) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property value exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if (CSSSyntaxScanner::hasControlCharacters($value)) {
			return null;
		}

		$len = strlen($value);
		$tokenizer = CSSTokenizer::forParserInternal($value, $options, false, true, 0, $len);

		$maxDepth = $options->maxDepth;
		/** @var list<array{kind: 'brace'|'bracket'|'function'|'paren'|'root', closeType: ?CSSTokenType, name: ?string, start: int, innerStart: int, hasEscapes: bool, components: list<mixed>}> $stack */
		$stack = [
			[
				'kind' => 'root',
				'closeType' => null,
				'name' => null,
				'start' => 0,
				'innerStart' => 0,
				'hasEscapes' => false,
				'components' => [],
			],
		];

		$componentCount = 0;
		$topLevelCommaCount = 0;
		$hasComments = false;
		$hasEscapes = false;
		$potentialSubstitutions = [];

		while (true) {
			$type = $tokenizer->advanceInternal();
			if ($tokenizer->tokenHadLeadingComment) {
				$hasComments = true;
			}
			if ($tokenizer->commentControlErrorOffsets !== [] || $tokenizer->tokenControlByteOffset !== null) {
				return null;
			}
			if ($tokenizer->tokenUnclosedCommentOffset !== null || $tokenizer->tokenClosedAtEof) {
				return null;
			}
			if ($type === CSSTokenType::Eof) {
				break;
			}
			if ($type === CSSTokenType::BadString || $type === CSSTokenType::BadUrl) {
				return null;
			}
			if ($type === CSSTokenType::Delim && $tokenizer->tokenParseErrorMessage !== null) {
				return null;
			}
			if ($type === CSSTokenType::Whitespace) {
				continue;
			}
			if ($tokenizer->tokenHasEscapes) {
				$hasEscapes = true;
			}

			// Structural CSS delimiters disallowed outside custom property raw blocks at top-level
			if (count($stack) === 1) {
				if ($type === CSSTokenType::Semicolon || $type === CSSTokenType::LeftCurlyBracket || $type === CSSTokenType::RightCurlyBracket) {
					return null;
				}
				if ($type === CSSTokenType::Comma && ++$topLevelCommaCount >= CSSStyleValue::MAX_LIST_ITEMS) {
					throw new CSSParserLimitException(sprintf('CSS value exceeds maximum limit of %d list items.', CSSStyleValue::MAX_LIST_ITEMS));
				}
			}

			if (++$componentCount > self::MAX_COMPONENT_TOKENS) {
				throw new CSSParserLimitException(sprintf('CSS value exceeds maximum limit of %d component tokens.', self::MAX_COMPONENT_TOKENS));
			}

			if ($type === CSSTokenType::Function
				|| $type === CSSTokenType::LeftParenthesis
				|| $type === CSSTokenType::LeftSquareBracket
				|| $type === CSSTokenType::LeftCurlyBracket
			) {
				$newDepth = count($stack);
				if ($maxDepth !== null && $newDepth > $maxDepth) {
					throw new CSSParserLimitException(sprintf('CSS nesting depth exceeds maximum limit of %d.', $maxDepth));
				}

				$fnName = null;
				$kind = 'paren';
				$closeType = CSSTokenType::RightParenthesis;
				if ($type === CSSTokenType::Function) {
					$fnName = strtolower($tokenizer->decodeCurrentIdentInternal());
					$kind = 'function';
					$closeType = CSSTokenType::RightParenthesis;
				} elseif ($type === CSSTokenType::LeftSquareBracket) {
					$kind = 'bracket';
					$closeType = CSSTokenType::RightSquareBracket;
				} elseif ($type === CSSTokenType::LeftCurlyBracket) {
					$kind = 'brace';
					$closeType = CSSTokenType::RightCurlyBracket;
				}

				$stack[] = [
					'kind' => $kind,
					'closeType' => $closeType,
					'name' => $fnName,
					'start' => $tokenizer->tokenStart,
					'innerStart' => $tokenizer->tokenEnd,
					'hasEscapes' => $tokenizer->tokenHasEscapes,
					'components' => [],
				];
				continue;
			}

			if ($type === CSSTokenType::RightParenthesis
				|| $type === CSSTokenType::RightSquareBracket
				|| $type === CSSTokenType::RightCurlyBracket
			) {
				if (count($stack) <= 1) {
					return null;
				}
				$frame = array_pop($stack);
				if ($frame['closeType'] !== $type) {
					return null;
				}
				$node = [
					'kind' => $frame['kind'],
					'name' => $frame['name'],
					'start' => $frame['start'],
					'end' => $tokenizer->tokenEnd,
					'innerStart' => $frame['innerStart'],
					'innerEnd' => $tokenizer->tokenStart,
					'components' => $frame['components'],
					'hasEscapes' => $frame['hasEscapes'],
				];
				if ($frame['kind'] === 'function' && isset(self::SUBSTITUTION_FUNCTIONS[(string) $frame['name']])) {
					$potentialSubstitutions[] = $node;
				}
				$stack[count($stack) - 1]['components'][] = $node;
				continue;
			}

			$name = null;
			if ($type === CSSTokenType::Ident || $type === CSSTokenType::AtKeyword || $type === CSSTokenType::Hash) {
				$name = $tokenizer->decodeCurrentIdentInternal();
			}

			$stack[count($stack) - 1]['components'][] = [
				'kind' => 'token',
				'tokenType' => $type,
				'name' => $name,
				'start' => $tokenizer->tokenStart,
				'end' => $tokenizer->tokenEnd,
				'hasEscapes' => $tokenizer->tokenHasEscapes,
			];
		}

		if (count($stack) !== 1) {
			return null;
		}

		$components = $stack[0]['components'];
		if ($components === [] && !$allowEmpty) {
			return null;
		}

		// Validate arbitrary substitution functions if any were seen
		$hasSubstitution = false;
		foreach ($potentialSubstitutions as $subNode) {
			if (!self::validateSubstitutionFunction($subNode)) {
				return null;
			}
			$hasSubstitution = true;
		}

		$isCssWideKeyword = count($components) === 1
			&& $components[0]['kind'] === 'token'
			&& ($components[0]['tokenType'] ?? null) === CSSTokenType::Ident
			&& isset(self::CSS_WIDE_KEYWORDS[strtolower((string) ($components[0]['name'] ?? ''))]);

		return [
			'source' => $value,
			'components' => $components,
			'hasSubstitution' => $hasSubstitution,
			'isCssWideKeyword' => $isCssWideKeyword,
			'hasComments' => $hasComments,
			'hasEscapes' => $hasEscapes,
		];
	}

	/**
	 * Decodes numeric metadata `(value, isInteger, unit)` for a Number/Percentage/Dimension token component.
	 *
	 * @param ParsedComponent $component
	 * @return array{value: float, isInteger: bool, unit: string}
	 */
	public static function decodeNumericToken(string $source, array $component): array
	{
		$start = $component['start'];
		$end = $component['end'];
		$raw = substr($source, $start, $end - $start);
		$rawLen = strlen($raw);

		$numEnd = 0;
		if ($numEnd < $rawLen && ($raw[$numEnd] === '+' || $raw[$numEnd] === '-')) {
			$numEnd++;
		}
		$numEnd += strspn($raw, '0123456789', $numEnd);
		$isInteger = true;
		if ($numEnd + 1 < $rawLen && $raw[$numEnd] === '.' && $raw[$numEnd + 1] >= '0' && $raw[$numEnd + 1] <= '9') {
			$isInteger = false;
			$numEnd += 2;
			$numEnd += strspn($raw, '0123456789', $numEnd);
		}
		if ($numEnd + 1 < $rawLen && ($raw[$numEnd] === 'e' || $raw[$numEnd] === 'E')) {
			$expPos = $numEnd + 1;
			if ($raw[$expPos] === '+' || $raw[$expPos] === '-') {
				$expPos++;
			}
			if ($expPos < $rawLen && $raw[$expPos] >= '0' && $raw[$expPos] <= '9') {
				$isInteger = false;
				$numEnd = $expPos + strspn($raw, '0123456789', $expPos);
			}
		}

		$numVal = (float) substr($raw, 0, $numEnd);
		$unitRaw = substr($raw, $numEnd);
		$unit = str_contains($unitRaw, '\\') ? CSSSyntaxScanner::decodeCssIdentEscapes($unitRaw) : $unitRaw;

		return [
			'value' => $numVal,
			'isInteger' => $isInteger,
			'unit' => $unit,
		];
	}

	/**
	 * Returns a comment-stripped slice of `$source` between `$start` and `$end` for downstream parsers.
	 */
	public static function sliceClean(string $source, int $start, int $end, bool $hasComments): string
	{
		$slice = substr($source, $start, $end - $start);

		return $hasComments && str_contains($slice, '/*')
			? CSSSyntaxScanner::removeComments($slice)
			: $slice;
	}

	/**
	 * Validates `var()`, `env()`, `attr()`, `if()`, and `first-valid()` argument structure iteratively.
	 *
	 * @param array<string, mixed> $node
	 */
	private static function validateSubstitutionFunction(array $node): bool
	{
		$fn = (string) ($node['name'] ?? '');
		/** @var list<array<string, mixed>> $args */
		$args = $node['components'] ?? [];

		if ($args === []) {
			return false;
		}

		if ($fn === 'var') {
			$first = $args[0];
			if (($first['kind'] ?? '') !== 'token' || ($first['tokenType'] ?? null) !== CSSTokenType::Ident) {
				return false;
			}
			$ident = (string) ($first['name'] ?? '');
			// Must be a valid <dashed-ident> starting with '--' and at least one trailing character
			if (!str_starts_with($ident, '--') || strlen($ident) <= 2) {
				return false;
			}
			if (count($args) === 1) {
				return true;
			}
			// Second component must be Comma; fallback after comma MAY be empty (`var(--a, )` is valid per CSS Variables L1 §3)
			$second = $args[1];

			return ($second['kind'] ?? '') === 'token' && ($second['tokenType'] ?? null) === CSSTokenType::Comma;
		}

		if ($fn === 'env') {
			$first = $args[0];
			if (($first['kind'] ?? '') !== 'token' || ($first['tokenType'] ?? null) !== CSSTokenType::Ident) {
				return false;
			}
			if (count($args) === 1) {
				return true;
			}
			$second = $args[1];

			return ($second['kind'] ?? '') === 'token' && ($second['tokenType'] ?? null) === CSSTokenType::Comma;
		}

		if ($fn === 'attr') {
			// attr(<attr-name> [type]? [, <fallback>]?)
			$first = $args[0];
			if (($first['kind'] ?? '') === 'token') {
				$tokType = $first['tokenType'] ?? null;

				return $tokType === CSSTokenType::Ident || $tokType === CSSTokenType::Delim;
			}

			return false;
		}

		// `if(...)` / `first-valid(...)`: non-empty balanced argument list
		return true;
	}
}
