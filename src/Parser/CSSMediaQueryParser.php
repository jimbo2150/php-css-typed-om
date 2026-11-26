<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

/**
 * Iterative, stack-safe Media Queries Level 4 & Level 5 parser and CSSOM canonicalizer.
 *
 * Supports boolean `not`/`and`/`or` logic, media types, plain/boolean/range media features
 * (including interval syntax like `(400px <= width <= 800px)`), and MQ Level 5 `<dashed-ident>`
 * custom media queries in boolean context.
 *
 * @see https://drafts.csswg.org/mediaqueries-4/
 * @see https://drafts.csswg.org/mediaqueries-5/
 */
final class CSSMediaQueryParser
{
	public const int MAX_MEDIA_NODES = 32_768;

	/** @var array<string, true> */
	private const array RESERVED_MEDIA_TYPES = [
		'only' => true,
		'not' => true,
		'and' => true,
		'or' => true,
		'layer' => true,
	];

	/** @var array<string, true> */
	private const array RESERVED_FEATURE_KEYWORDS = [
		'only' => true,
		'not' => true,
		'and' => true,
		'or' => true,
	];

	/**
	 * Parses and canonically serializes a Media Queries Level 4/5 `<media-query-list>`.
	 *
	 * @return list<string> Canonically serialized media queries
	 * @throws InvalidArgumentException On invalid MQ L4/L5 grammar when `$browserErrorRecovery` is false
	 * @throws CSSParserLimitException On byte, depth, list-item, or node/token limit overflow
	 */
	public static function parseList(
		string $mediaText,
		?CSSParserOptions $options = null,
		bool $browserErrorRecovery = false,
	): array {
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($mediaText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('Media query list exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if ($mediaText === '') {
			return [];
		}

		[$tokens, $pairEnd, $items] = CSSSyntaxScanner::tokenizeBracketedInternal(
			$mediaText,
			$options,
			self::MAX_MEDIA_NODES,
			'Media query',
			true,
			$browserErrorRecovery,
			true,
			CSSStyleValue::MAX_LIST_ITEMS,
		);

		$queries = [];
		foreach ($items as [$start, $end, $hasError]) {
			CSSSyntaxScanner::trimTokenRange($tokens, $start, $end);
			if ($start >= $end) {
				if ($hasError && !$browserErrorRecovery) {
					throw new InvalidArgumentException('Media query contains disallowed tokens or unclosed comments.');
				}
				continue;
			}

			$queries[] = self::parseSingleFromSlice(
				$mediaText,
				$tokens,
				$pairEnd,
				$start,
				$end,
				$hasError,
				$browserErrorRecovery,
			);
		}

		return $queries;
	}

	/**
	 * Parses and canonically serializes a single `<media-query>` (rejects empty input and top-level commas).
	 *
	 * @throws InvalidArgumentException On empty, multi-query, or malformed MQ L4/L5 input
	 * @throws CSSParserLimitException On byte, depth, or token limit overflow
	 */
	public static function parseSingle(
		string $queryText,
		?CSSParserOptions $options = null,
		bool $browserErrorRecovery = false,
	): string {
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($queryText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('Media query exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		[$tokens, $pairEnd, $items] = CSSSyntaxScanner::tokenizeBracketedInternal(
			$queryText,
			$options,
			self::MAX_MEDIA_NODES,
			'Media query',
			true,
			$browserErrorRecovery,
			true,
			CSSStyleValue::MAX_LIST_ITEMS,
		);
		if (count($items) > 1) {
			throw new InvalidArgumentException('A single media query cannot contain top-level commas.');
		}
		[$start, $end, $hasError] = $items[0] ?? [0, 0, false];
		CSSSyntaxScanner::trimTokenRange($tokens, $start, $end);
		if ($start >= $end) {
			throw new InvalidArgumentException('A single non-empty media query is required.');
		}

		return self::parseSingleFromSlice(
			$queryText,
			$tokens,
			$pairEnd,
			$start,
			$end,
			$hasError,
			$browserErrorRecovery,
		);
	}

	/**
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 */
	private static function parseSingleFromSlice(
		string $source,
		array $tokens,
		array $pairEnd,
		int $start,
		int $end,
		bool $hasError,
		bool $browserErrorRecovery,
	): string {
		if ($hasError) {
			if ($browserErrorRecovery) {
				return 'not all';
			}
			throw new InvalidArgumentException('Malformed media query syntax.');
		}

		$result = self::parseMediaQueryTokens($source, $tokens, $pairEnd, $start, $end);
		if ($result === null) {
			if ($browserErrorRecovery) {
				return 'not all';
			}
			throw new InvalidArgumentException('Invalid media query: ' . CSSSyntaxScanner::trimCssWhitespace(substr($source, $tokens[$start][1], $tokens[$end - 1][2] - $tokens[$start][1])));
		}

		return $result;
	}

	/**
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 */
	private static function parseMediaQueryTokens(
		string $source,
		array $tokens,
		array $pairEnd,
		int $start,
		int $end,
	): ?string {
		$first = $tokens[$start];
		if ($first[0] === CSSTokenType::LeftParenthesis) {
			return self::parseConditionIterative($source, $tokens, $pairEnd, $start, $end, true);
		}
		if ($first[0] !== CSSTokenType::Ident) {
			return null;
		}

		$kw = strtolower($first[3]);
		$pos = $start + 1;
		while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
			$pos++;
		}

		if ($kw === 'not') {
			if ($pos >= $end) {
				return null;
			}
			if ($tokens[$pos][0] === CSSTokenType::LeftParenthesis) {
				return self::parseConditionIterative($source, $tokens, $pairEnd, $start, $end, true);
			}
			if ($tokens[$pos][0] !== CSSTokenType::Ident) {
				return null;
			}
			$prefix = 'not ';
			$typeToken = $tokens[$pos];
			$pos++;
		} elseif ($kw === 'only') {
			if ($pos >= $end || $tokens[$pos][0] !== CSSTokenType::Ident) {
				return null;
			}
			$prefix = 'only ';
			$typeToken = $tokens[$pos];
			$pos++;
		} else {
			$prefix = '';
			$typeToken = $first;
		}

		$rawType = $typeToken[3];
		if ($rawType === '' || str_starts_with($rawType, '--')) {
			return null;
		}
		$lowerType = strtolower($rawType);
		if (isset(self::RESERVED_MEDIA_TYPES[$lowerType])) {
			return null;
		}
		$serializedType = $prefix . ($typeToken[7] ? CSS::escape($lowerType) : $lowerType);

		while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
			$pos++;
		}
		if ($pos >= $end) {
			return $serializedType;
		}

		if ($tokens[$pos][0] !== CSSTokenType::Ident || strtolower($tokens[$pos][3]) !== 'and') {
			return null;
		}
		$pos++;
		while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
			$pos++;
		}
		if ($pos >= $end) {
			return null;
		}

		$cond = self::parseConditionIterative($source, $tokens, $pairEnd, $pos, $end, false);
		if ($cond === null) {
			return null;
		}

		return $serializedType . ' and ' . $cond;
	}

	/**
	 * Iterative parser for `<media-condition>` and `<media-condition-without-or>`.
	 *
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 */
	private static function parseConditionIterative(
		string $source,
		array $tokens,
		array $pairEnd,
		int $rootStart,
		int $rootEnd,
		bool $rootAllowOr,
	): ?string {
		// Flat single `(feature: value)` / `(feature)` fast path: skip work-stack & slot allocation.
		if ($tokens[$rootStart][0] === CSSTokenType::LeftParenthesis && ($pairEnd[$rootStart] ?? null) === ($rootEnd - 1)) {
			$innerStart = $rootStart + 1;
			$innerEnd = $rootEnd - 1;
			CSSSyntaxScanner::trimTokenRange($tokens, $innerStart, $innerEnd);
			if ($innerStart >= $innerEnd) {
				return null;
			}
			$first = $tokens[$innerStart];
			if ($first[0] !== CSSTokenType::LeftParenthesis
				&& !($first[0] === CSSTokenType::Ident && strtolower($first[3]) === 'not')
			) {
				$feature = self::parseMediaFeature($source, $tokens, $pairEnd, $innerStart, $innerEnd);
				return $feature !== null ? '(' . $feature . ')' : null;
			}
		}

		$slots = [null];
		$nextSlot = 1;
		// Frame: [phase (0=parse, 1=assemble), start, end, allowOr, destSlot, prefix, op, childSlots]
		$stack = [[0, $rootStart, $rootEnd, $rootAllowOr, 0, '', '', []]];

		while ($stack !== []) {
			$frame = array_pop($stack);
			if ($frame[0] === 1) {
				/** @var int $destSlot */
				$destSlot = $frame[4];
				/** @var string $prefix */
				$prefix = $frame[5];
				/** @var string $op */
				$op = $frame[6];
				/** @var list<int> $childSlots */
				$childSlots = $frame[7];

				$parts = [];
				foreach ($childSlots as $cSlot) {
					$val = $slots[$cSlot];
					if ($val === null) {
						return null;
					}
					$parts[] = '(' . $val . ')';
				}
				$slots[$destSlot] = $prefix !== ''
					? $prefix . ' ' . $parts[0]
					: implode(' ' . $op . ' ', $parts);
				continue;
			}

			[, $start, $end, $allowOr, $destSlot] = $frame;
			CSSSyntaxScanner::trimTokenRange($tokens, $start, $end);
			if ($start >= $end) {
				return null;
			}

			// `<media-not> = not <media-in-parens>`
			if ($tokens[$start][0] === CSSTokenType::Ident && strtolower($tokens[$start][3]) === 'not') {
				$pos = $start + 1;
				while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
					$pos++;
				}
				if ($pos >= $end || $tokens[$pos][0] !== CSSTokenType::LeftParenthesis) {
					return null;
				}
				$closeIdx = $pairEnd[$pos] ?? null;
				if ($closeIdx === null || $closeIdx + 1 !== $end) {
					return null;
				}
				$childSlot = $nextSlot++;
				$slots[$childSlot] = null;

				$stack[] = [1, $start, $end, $allowOr, $destSlot, 'not', '', [$childSlot]];
				self::pushInParensWork($source, $tokens, $pairEnd, $pos + 1, $closeIdx, $childSlot, $slots, $stack);
				continue;
			}

			// `<media-in-parens> [ and <media-in-parens> ]* | <media-in-parens> [ or <media-in-parens> ]*`
			$pos = $start;
			$expectedOp = '';
			$children = [];
			while ($pos < $end) {
				if ($tokens[$pos][0] !== CSSTokenType::LeftParenthesis) {
					return null;
				}
				$closeIdx = $pairEnd[$pos] ?? null;
				if ($closeIdx === null || $closeIdx >= $end) {
					return null;
				}
				$childSlot = $nextSlot++;
				$slots[$childSlot] = null;
				$children[] = [$pos + 1, $closeIdx, $childSlot];

				$pos = $closeIdx + 1;
				while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
					$pos++;
				}
				if ($pos >= $end) {
					break;
				}

				if ($tokens[$pos][0] !== CSSTokenType::Ident) {
					return null;
				}
				$op = strtolower($tokens[$pos][3]);
				if ($op !== 'and' && $op !== 'or') {
					return null;
				}
				if ($op === 'or' && !$allowOr) {
					return null;
				}
				if ($expectedOp === '') {
					$expectedOp = $op;
				} elseif ($expectedOp !== $op) {
					return null;
				}

				$pos++;
				while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
					$pos++;
				}
				if ($pos >= $end) {
					return null;
				}
			}

			if ($children === []) {
				return null;
			}

			$childSlots = [];
			foreach ($children as $c) {
				$childSlots[] = $c[2];
			}
			$stack[] = [1, $start, $end, $allowOr, $destSlot, '', $expectedOp, $childSlots];
			for ($i = count($children) - 1; $i >= 0; $i--) {
				[$cStart, $cEnd, $cSlot] = $children[$i];
				if (!self::pushInParensWork($source, $tokens, $pairEnd, $cStart, $cEnd, $cSlot, $slots, $stack)) {
					return null;
				}
			}
		}

		return $slots[0];
	}

	/**
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 * @param array<int, ?string> $slots
	 * @param list<array<int, mixed>> $stack
	 */
	private static function pushInParensWork(
		string $source,
		array $tokens,
		array $pairEnd,
		int $innerStart,
		int $innerEnd,
		int $destSlot,
		array &$slots,
		array &$stack,
	): bool {
		CSSSyntaxScanner::trimTokenRange($tokens, $innerStart, $innerEnd);
		if ($innerStart >= $innerEnd) {
			return false;
		}

		$first = $tokens[$innerStart];
		$isNestedCondition = $first[0] === CSSTokenType::LeftParenthesis;
		if (!$isNestedCondition && $first[0] === CSSTokenType::Ident && strtolower($first[3]) === 'not') {
			$afterNot = $innerStart + 1;
			while ($afterNot < $innerEnd && $tokens[$afterNot][0] === CSSTokenType::Whitespace) {
				$afterNot++;
			}
			if ($afterNot >= $innerEnd || $tokens[$afterNot][0] !== CSSTokenType::LeftParenthesis) {
				return false;
			}
			$isNestedCondition = true;
		}

		if ($isNestedCondition) {
			$stack[] = [0, $innerStart, $innerEnd, true, $destSlot, '', '', []];
			return true;
		}

		$feature = self::parseMediaFeature($source, $tokens, $pairEnd, $innerStart, $innerEnd);
		if ($feature === null) {
			return false;
		}
		$slots[$destSlot] = $feature;
		return true;
	}

	/**
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 */
	private static function parseMediaFeature(
		string $source,
		array $tokens,
		array $pairEnd,
		int $start,
		int $end,
	): ?string {
		// Single top-level scan collecting non-ws indices, first colon, and range comparison operators.
		$nonWs = [];
		$colonPos = null;
		$ops = [];
		$pos = $start;
		while ($pos < $end) {
			$tok = $tokens[$pos];
			$type = $tok[0];
			if ($type === CSSTokenType::Whitespace) {
				$pos++;
				continue;
			}
			if ($type === CSSTokenType::Colon) {
				$colonPos ??= count($nonWs);
			} elseif ($type === CSSTokenType::Delim) {
				$ch = $tok[3];
				if ($ch === '<' || $ch === '>') {
					if ($pos + 1 < $end && $tokens[$pos + 1][0] === CSSTokenType::Delim && $tokens[$pos + 1][3] === '=') {
						$ops[] = [$ch . '=', $pos, $pos + 2];
						$nonWs[] = $pos;
						$nonWs[] = $pos + 1;
						$pos += 2;
						continue;
					}
					$ops[] = [$ch, $pos, $pos + 1];
				} elseif ($ch === '=') {
					$ops[] = ['=', $pos, $pos + 1];
				}
			}
			$nonWs[] = $pos;
			if ($type === CSSTokenType::Function || $type === CSSTokenType::LeftParenthesis || $type === CSSTokenType::LeftSquareBracket) {
				$close = $pairEnd[$pos] ?? null;
				if ($close === null || $close >= $end) {
					return null;
				}
				$pos = $close + 1;
				continue;
			}
			$pos++;
		}

		if ($nonWs === []) {
			return null;
		}

		// 1. `<mf-boolean>` = `<mf-name>` (including MQ5 `<dashed-ident>`)
		if (count($nonWs) === 1 && $tokens[$nonWs[0]][0] === CSSTokenType::Ident) {
			return self::serializeFeatureName($tokens[$nonWs[0]][3], $tokens[$nonWs[0]][7], true);
		}

		// 2. `<mf-plain>` = `<mf-name> : <mf-value>`
		if ($colonPos !== null) {
			if ($colonPos !== 1 || $tokens[$nonWs[0]][0] !== CSSTokenType::Ident || count($nonWs) < 3) {
				return null;
			}
			$name = self::serializeFeatureName($tokens[$nonWs[0]][3], $tokens[$nonWs[0]][7], false);
			if ($name === null) {
				return null;
			}
			$val = self::parseMediaFeatureValue($source, $tokens, $pairEnd, $nonWs[2], $end);
			if ($val === null) {
				return null;
			}

			return $name . ': ' . $val;
		}

		// 3. `<mf-range>` using operators captured during the single scan above
		return self::parseMediaFeatureRange($source, $tokens, $pairEnd, $start, $end, $ops);
	}

	private static function serializeFeatureName(string $ident, bool $hasEscapes, bool $allowDashed): ?string
	{
		if ($ident === '') {
			return null;
		}
		if (str_starts_with($ident, '--')) {
			if (!$allowDashed || strlen($ident) < 3) {
				return null;
			}
			return $hasEscapes ? CSS::escape($ident) : $ident;
		}
		$lower = strtolower($ident);
		if (isset(self::RESERVED_FEATURE_KEYWORDS[$lower])) {
			return null;
		}

		return $hasEscapes ? CSS::escape($lower) : $lower;
	}

	/**
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 * @param list<array{0: string, 1: int, 2: int}> $ops
	 */
	private static function parseMediaFeatureRange(
		string $source,
		array $tokens,
		array $pairEnd,
		int $start,
		int $end,
		array $ops,
	): ?string {
		$opCount = count($ops);
		if ($opCount === 1) {
			[$opStr, $opStart, $opEnd] = $ops[0];
			$lhsName = self::trySingleFeatureName($tokens, $start, $opStart);
			if ($lhsName !== null) {
				$rhsVal = self::parseMediaFeatureValue($source, $tokens, $pairEnd, $opEnd, $end);
				if ($rhsVal !== null) {
					return $lhsName . ' ' . $opStr . ' ' . $rhsVal;
				}
			}
			$rhsName = self::trySingleFeatureName($tokens, $opEnd, $end);
			if ($rhsName !== null) {
				$lhsVal = self::parseMediaFeatureValue($source, $tokens, $pairEnd, $start, $opStart);
				if ($lhsVal !== null) {
					return $lhsVal . ' ' . $opStr . ' ' . $rhsName;
				}
			}
			return null;
		}

		if ($opCount === 2) {
			[$op1Str, $op1Start, $op1End] = $ops[0];
			[$op2Str, $op2Start, $op2End] = $ops[1];
			$bothLt = ($op1Str === '<' || $op1Str === '<=') && ($op2Str === '<' || $op2Str === '<=');
			$bothGt = ($op1Str === '>' || $op1Str === '>=') && ($op2Str === '>' || $op2Str === '>=');
			if (!$bothLt && !$bothGt) {
				return null;
			}

			$midName = self::trySingleFeatureName($tokens, $op1End, $op2Start);
			if ($midName === null) {
				return null;
			}
			$leftVal = self::parseMediaFeatureValue($source, $tokens, $pairEnd, $start, $op1Start);
			$rightVal = self::parseMediaFeatureValue($source, $tokens, $pairEnd, $op2End, $end);
			if ($leftVal === null || $rightVal === null) {
				return null;
			}

			return $leftVal . ' ' . $op1Str . ' ' . $midName . ' ' . $op2Str . ' ' . $rightVal;
		}

		return null;
	}

	/**
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 */
	private static function trySingleFeatureName(array $tokens, int $start, int $end): ?string
	{
		CSSSyntaxScanner::trimTokenRange($tokens, $start, $end);
		if ($end - $start !== 1 || $tokens[$start][0] !== CSSTokenType::Ident) {
			return null;
		}

		return self::serializeFeatureName($tokens[$start][3], $tokens[$start][7], false);
	}

	/**
	 * Parses `<mf-value>` (`<number>`, `<dimension>`, `<ident>`, `<ratio>`, or CSS math/env function).
	 *
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 */
	private static function parseMediaFeatureValue(
		string $source,
		array $tokens,
		array $pairEnd,
		int $start,
		int $end,
	): ?string {
		$items = [];
		$pos = $start;
		while ($pos < $end) {
			$tok = $tokens[$pos];
			if ($tok[0] === CSSTokenType::Whitespace) {
				$pos++;
				continue;
			}
			$items[] = $pos;
			if ($tok[0] === CSSTokenType::Function) {
				$close = $pairEnd[$pos] ?? null;
				if ($close === null || $close >= $end) {
					return null;
				}
				$pos = $close + 1;
				continue;
			}
			$pos++;
		}

		$count = count($items);
		if ($count === 1) {
			$tok = $tokens[$items[0]];
			$type = $tok[0];
			if ($type === CSSTokenType::Number) {
				return self::formatNumber($tok[5]);
			}
			if ($type === CSSTokenType::Dimension) {
				$num = self::formatNumber($tok[5]);
				$unit = $tok[6] ?? '';
				if ($num === null || $unit === '') {
					return null;
				}
				$lowerUnit = strtolower($unit);
				return $num . ($tok[7] ? CSS::escape($lowerUnit) : $lowerUnit);
			}
			if ($type === CSSTokenType::Ident) {
				$lower = strtolower($tok[3]);
				if (isset(self::RESERVED_FEATURE_KEYWORDS[$lower])) {
					return null;
				}
				return $tok[7] ? CSS::escape($lower) : $lower;
			}
			if ($type === CSSTokenType::Function) {
				$fnLower = strtolower($tok[3]);
				if ($fnLower !== 'env' && !isset(CSSCalcParser::MATH_FUNCTIONS[$fnLower])) {
					return null;
				}
				$closeIdx = $pairEnd[$items[0]] ?? null;
				if ($closeIdx === null) {
					return null;
				}
				$innerStart = $items[0] + 1;
				$innerEnd = $closeIdx;
				$rawInner = $innerStart < $innerEnd
					? substr($source, $tokens[$innerStart][1], $tokens[$innerEnd - 1][2] - $tokens[$innerStart][1])
					: '';
				$cleanInner = str_contains($rawInner, '/*')
					? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($rawInner))
					: CSSSyntaxScanner::trimCssWhitespace($rawInner);
				if ($cleanInner === '') {
					return null;
				}
				return $fnLower . '(' . $cleanInner . ')';
			}

			return null;
		}

		// `<ratio> = <number [0,∞]> / <number [0,∞]>`
		if ($count === 3
			&& $tokens[$items[0]][0] === CSSTokenType::Number
			&& $tokens[$items[1]][0] === CSSTokenType::Delim
			&& $tokens[$items[1]][3] === '/'
			&& $tokens[$items[2]][0] === CSSTokenType::Number
		) {
			$n1 = $tokens[$items[0]][5];
			$n2 = $tokens[$items[2]][5];
			if ($n1 === null || $n2 === null || !is_finite($n1) || !is_finite($n2) || $n1 < 0.0 || $n2 < 0.0) {
				return null;
			}
			$s1 = self::formatNumber($n1);
			$s2 = self::formatNumber($n2);
			return ($s1 !== null && $s2 !== null) ? $s1 . '/' . $s2 : null;
		}

		return null;
	}

	private static function formatNumber(?float $value): ?string
	{
		if ($value === null || !is_finite($value)) {
			return null;
		}

		return $value === 0.0 ? '0' : (string) $value;
	}
}
