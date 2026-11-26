<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Grammar;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
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
 *   name?: ?string,
 *   lower?: string,
 *   start: int,
 *   end: int,
 *   numValue?: float,
 *   isInt?: bool,
 *   unitLower?: string,
 *   components?: list<mixed>
 * }
 * @phpstan-type ParsedResult array{
 *   source: string,
 *   components: list<ParsedComponent>,
 *   hasSubstitution: bool,
 *   isCssWideKeyword: bool,
 *   hasComments: bool
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

	private const int MAX_COMPONENT_TOKENS = 131_072;

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
		$maxMathDepth = $options->maxMathDepth;
		/** @var list<array{kind: 'brace'|'bracket'|'function'|'paren'|'root', closeType: ?CSSTokenType, name: ?string, start: int, isMath: bool, mathDepth: int, components: list<mixed>}> $stack */
		$stack = [
			[
				'kind' => 'root',
				'closeType' => null,
				'name' => null,
				'start' => 0,
				'isMath' => false,
				'mathDepth' => 0,
				'components' => [],
			],
		];
		$stackDepth = 1;

		$componentCount = 0;
		$topLevelCommaCount = 0;
		$hasComments = false;
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
			if ($tokenizer->tokenParseErrorMessage !== null) {
				return null;
			}
			if ($type === CSSTokenType::Whitespace) {
				continue;
			}

			// Structural CSS delimiters disallowed outside custom property raw blocks at top-level
			if ($stackDepth === 1) {
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
				if ($maxDepth !== null && $stackDepth > $maxDepth) {
					throw new CSSParserLimitException(sprintf('CSS nesting depth exceeds maximum limit of %d.', $maxDepth));
				}

				$parentFrame = $stack[$stackDepth - 1];
				$fnName = null;
				$kind = 'paren';
				$closeType = CSSTokenType::RightParenthesis;
				$frameIsMath = false;
				$frameMathDepth = 0;

				if ($type === CSSTokenType::Function) {
					$fnName = strtolower($tokenizer->decodeCurrentIdentInternal());
					$kind = 'function';
					if ($parentFrame['isMath']) {
						$frameIsMath = true;
						$frameMathDepth = $parentFrame['mathDepth'] + 1;
					} elseif (CSSCalcParser::isMathFunctionName($fnName)) {
						$frameIsMath = true;
						$frameMathDepth = $fnName === 'calc' ? 0 : 1;
					}
				} elseif ($type === CSSTokenType::LeftSquareBracket) {
					$kind = 'bracket';
					$closeType = CSSTokenType::RightSquareBracket;
				} elseif ($type === CSSTokenType::LeftCurlyBracket) {
					$kind = 'brace';
					$closeType = CSSTokenType::RightCurlyBracket;
				} elseif ($parentFrame['isMath']) {
					$frameIsMath = true;
					$frameMathDepth = $parentFrame['mathDepth'] + 1;
				}

				if ($frameIsMath && $maxMathDepth !== null && $frameMathDepth > $maxMathDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS math nesting depth of %d exceeded.', $maxMathDepth));
				}

				$stack[] = [
					'kind' => $kind,
					'closeType' => $closeType,
					'name' => $fnName,
					'start' => $tokenizer->tokenStart,
					'isMath' => $frameIsMath,
					'mathDepth' => $frameMathDepth,
					'components' => [],
				];
				$stackDepth++;
				continue;
			}

			if ($type === CSSTokenType::RightParenthesis
				|| $type === CSSTokenType::RightSquareBracket
				|| $type === CSSTokenType::RightCurlyBracket
			) {
				if ($stackDepth <= 1) {
					return null;
				}
				$frame = array_pop($stack);
				$stackDepth--;
				if ($frame['closeType'] !== $type) {
					return null;
				}
				$node = [
					'kind' => $frame['kind'],
					'name' => $frame['name'],
					'start' => $frame['start'],
					'end' => $tokenizer->tokenEnd,
					'components' => $frame['components'],
				];
				if ($frame['kind'] === 'function' && isset(self::SUBSTITUTION_FUNCTIONS[(string) $frame['name']])) {
					$potentialSubstitutions[] = $node;
				}
				$stack[$stackDepth - 1]['components'][] = $node;
				continue;
			}

			if ($type === CSSTokenType::Ident) {
				$name = $tokenizer->decodeCurrentIdentInternal();
				$stack[$stackDepth - 1]['components'][] = [
					'kind' => 'token',
					'tokenType' => $type,
					'name' => $name,
					'lower' => strtolower($name),
					'start' => $tokenizer->tokenStart,
					'end' => $tokenizer->tokenEnd,
				];
				continue;
			}

			if ($type === CSSTokenType::Number || $type === CSSTokenType::Percentage || $type === CSSTokenType::Dimension) {
				$tokStart = $tokenizer->tokenStart;
				$numValue = (float) substr($value, $tokStart, $tokenizer->tokenNumEnd - $tokStart);
				$unitLower = match ($type) {
					CSSTokenType::Percentage => '%',
					CSSTokenType::Dimension => strtolower(
						$tokenizer->tokenHasEscapes
							? CSSSyntaxScanner::decodeCssIdentEscapes(substr($value, $tokenizer->tokenUnitStart, $tokenizer->tokenUnitEnd - $tokenizer->tokenUnitStart))
							: substr($value, $tokenizer->tokenUnitStart, $tokenizer->tokenUnitEnd - $tokenizer->tokenUnitStart),
					),
					default => '',
				};
				$stack[$stackDepth - 1]['components'][] = [
					'kind' => 'token',
					'tokenType' => $type,
					'name' => null,
					'start' => $tokStart,
					'end' => $tokenizer->tokenEnd,
					'numValue' => $numValue,
					'isInt' => $tokenizer->tokenIsInteger,
					'unitLower' => $unitLower,
				];
				continue;
			}

			$name = ($type === CSSTokenType::AtKeyword || $type === CSSTokenType::Hash)
				? $tokenizer->decodeCurrentIdentInternal()
				: null;

			$stack[$stackDepth - 1]['components'][] = [
				'kind' => 'token',
				'tokenType' => $type,
				'name' => $name,
				'start' => $tokenizer->tokenStart,
				'end' => $tokenizer->tokenEnd,
			];
		}

		if ($stackDepth !== 1) {
			return null;
		}

		$components = $stack[0]['components'];
		if ($components === [] && !$allowEmpty) {
			return null;
		}

		// Validate arbitrary substitution functions if any were seen
		$hasSubstitution = false;
		foreach ($potentialSubstitutions as $subNode) {
			if (!self::validateSubstitutionFunction($subNode, $value)) {
				return null;
			}
			$hasSubstitution = true;
		}

		$isCssWideKeyword = count($components) === 1
			&& $components[0]['kind'] === 'token'
			&& ($components[0]['tokenType'] ?? null) === CSSTokenType::Ident
			&& isset(self::CSS_WIDE_KEYWORDS[$components[0]['lower'] ?? '']);

		return [
			'source' => $value,
			'components' => $components,
			'hasSubstitution' => $hasSubstitution,
			'isCssWideKeyword' => $isCssWideKeyword,
			'hasComments' => $hasComments,
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
	private static function validateSubstitutionFunction(array $node, string $source): bool
	{
		$fn = (string) ($node['name'] ?? '');
		/** @var list<array<string, mixed>> $args */
		$args = $node['components'] ?? [];
		$argCount = count($args);

		if ($argCount === 0) {
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
			if ($argCount === 1) {
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
			// CSS Environment Variables L1: `env(<ident> <integer>* [ , <declaration-value>? ]?)`
			$idx = 1;
			while ($idx < $argCount) {
				$tok = $args[$idx];
				if (($tok['kind'] ?? '') !== 'token') {
					return false;
				}
				$tt = $tok['tokenType'] ?? null;
				if ($tt === CSSTokenType::Comma) {
					return true;
				}
				if ($tt !== CSSTokenType::Number || !($tok['isInt'] ?? false)) {
					return false;
				}
				$idx++;
			}

			return true;
		}

		if ($fn === 'attr') {
			// attr([ <ident-token> | '*' ] '|' <ident-token> | <ident-token> ... )
			$first = $args[0];
			if (($first['kind'] ?? '') !== 'token') {
				return false;
			}
			$tt = $first['tokenType'] ?? null;
			$hasPipeAfter0 = $argCount >= 3
				&& ($args[1]['kind'] ?? '') === 'token'
				&& ($args[1]['tokenType'] ?? null) === CSSTokenType::Delim
				&& ($args[1]['end'] - $args[1]['start']) === 1
				&& $source[$args[1]['start']] === '|';
			if ($tt === CSSTokenType::Ident) {
				return !$hasPipeAfter0 || (($args[2]['kind'] ?? '') === 'token' && ($args[2]['tokenType'] ?? null) === CSSTokenType::Ident);
			}
			if ($tt === CSSTokenType::Delim && ($first['end'] - $first['start']) === 1) {
				$ch = $source[$first['start']];
				if ($ch === '*' && $hasPipeAfter0) {
					return ($args[2]['kind'] ?? '') === 'token' && ($args[2]['tokenType'] ?? null) === CSSTokenType::Ident;
				}
				if ($ch === '|' && $argCount >= 2) {
					return ($args[1]['kind'] ?? '') === 'token' && ($args[1]['tokenType'] ?? null) === CSSTokenType::Ident;
				}
			}

			return false;
		}

		// `if(...)` / `first-valid(...)`: must contain at least one non-separator component (reject `first-valid(,,,)` / `if(;)`)
		foreach ($args as $arg) {
			if (($arg['kind'] ?? '') !== 'token') {
				return true;
			}
			$tt = $arg['tokenType'] ?? null;
			if ($tt !== CSSTokenType::Comma && $tt !== CSSTokenType::Semicolon) {
				return true;
			}
		}

		return false;
	}
}
