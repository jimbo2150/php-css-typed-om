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

	/** @var array<string, true> */
	private const array ALLOWED_VALUE_FUNCTIONS = [
		'calc' => true,
		'min' => true,
		'max' => true,
		'clamp' => true,
		'env' => true,
		'round' => true,
		'mod' => true,
		'rem' => true,
		'abs' => true,
		'sign' => true,
		'sin' => true,
		'cos' => true,
		'tan' => true,
		'asin' => true,
		'acos' => true,
		'atan' => true,
		'atan2' => true,
		'pow' => true,
		'sqrt' => true,
		'hypot' => true,
		'log' => true,
		'exp' => true,
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

		[$tokens, $pairEnd, $items] = self::tokenizeAndSplitList($mediaText, $options, $browserErrorRecovery);

		$queries = [];
		foreach ($items as $item) {
			$start = $item['start'];
			$end = $item['end'];
			while ($start < $end && $tokens[$start]['type'] === CSSTokenType::Whitespace) {
				$start++;
			}
			while ($end > $start && $tokens[$end - 1]['type'] === CSSTokenType::Whitespace) {
				$end--;
			}
			if ($start >= $end) {
				if ($item['hasError'] && !$browserErrorRecovery) {
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
				$item['hasError'],
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

		[$tokens, $pairEnd, $items] = self::tokenizeAndSplitList($queryText, $options, $browserErrorRecovery);
		if (count($items) > 1) {
			throw new InvalidArgumentException('A single media query cannot contain top-level commas.');
		}
		$item = $items[0] ?? ['start' => 0, 'end' => 0, 'hasError' => false];
		$start = $item['start'];
		$end = $item['end'];
		while ($start < $end && $tokens[$start]['type'] === CSSTokenType::Whitespace) {
			$start++;
		}
		while ($end > $start && $tokens[$end - 1]['type'] === CSSTokenType::Whitespace) {
			$end--;
		}
		if ($start >= $end) {
			throw new InvalidArgumentException('A single non-empty media query is required.');
		}

		return self::parseSingleFromSlice(
			$queryText,
			$tokens,
			$pairEnd,
			$start,
			$end,
			$item['hasError'],
			$browserErrorRecovery,
		);
	}

	/**
	 * @return array{
	 *   0: list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, unit: ?string, repr: ?string}>,
	 *   1: array<int, int>,
	 *   2: list<array{start: int, end: int, hasError: bool}>
	 * }
	 */
	private static function tokenizeAndSplitList(
		string $source,
		CSSParserOptions $options,
		bool $browserErrorRecovery,
	): array {
		$tokenizer = CSSTokenizer::forParserInternal($source, $options, false, true, 0, null, true);
		$tokens = [];
		$pairEnd = [];
		$openStack = [];
		$items = [];
		$itemStart = 0;
		$itemNonWs = 0;
		$itemError = false;
		$totalTokens = 0;

		while (true) {
			$type = $tokenizer->advanceInternal();
			if ($tokenizer->commentControlErrorOffsets !== [] || $tokenizer->tokenControlByteOffset !== null) {
				$itemError = true;
			}
			if ($tokenizer->tokenUnclosedCommentOffset !== null) {
				$itemError = true;
			}
			if ($type === CSSTokenType::Eof) {
				if ($openStack !== []) {
					$itemError = true;
				}
				$items[] = ['start' => $itemStart, 'end' => $totalTokens, 'hasError' => $itemError];
				if (count($items) > CSSStyleValue::MAX_LIST_ITEMS) {
					throw new CSSParserLimitException(sprintf('Media query list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
				}
				break;
			}

			if ($totalTokens >= self::MAX_MEDIA_NODES) {
				throw new CSSParserLimitException(sprintf('Media query exceeds maximum node limit of %d.', self::MAX_MEDIA_NODES));
			}

			if ($openStack === [] && $type === CSSTokenType::Comma) {
				$items[] = ['start' => $itemStart, 'end' => $totalTokens, 'hasError' => $itemError];
				if (count($items) > CSSStyleValue::MAX_LIST_ITEMS) {
					throw new CSSParserLimitException(sprintf('Media query list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
				}
				$itemStart = $totalTokens + 1;
				$itemNonWs = 0;
				$itemError = false;
			} else {
				if ($type !== CSSTokenType::Whitespace) {
					$itemNonWs++;
					if ($itemNonWs > CSSStyleValue::MAX_LIST_ITEMS) {
						throw new CSSParserLimitException(sprintf('Media query exceeds maximum limit of %d tokens.', CSSStyleValue::MAX_LIST_ITEMS));
					}
				}
				if ($type === CSSTokenType::BadString || $type === CSSTokenType::BadUrl) {
					$itemError = true;
				} elseif (!$browserErrorRecovery && $tokenizer->tokenClosedAtEof) {
					$itemError = true;
				} elseif ($type === CSSTokenType::Semicolon || $type === CSSTokenType::LeftCurlyBracket || $type === CSSTokenType::RightCurlyBracket) {
					$itemError = true;
				}

				if ($type === CSSTokenType::LeftParenthesis || $type === CSSTokenType::Function) {
					$openStack[] = [$totalTokens, ')'];
					if ($options->maxDepth !== null && count($openStack) > $options->maxDepth) {
						throw new CSSParserLimitException(sprintf('Media query exceeds maximum nesting depth of %d.', $options->maxDepth));
					}
				} elseif ($type === CSSTokenType::LeftSquareBracket) {
					$openStack[] = [$totalTokens, ']'];
					if ($options->maxDepth !== null && count($openStack) > $options->maxDepth) {
						throw new CSSParserLimitException(sprintf('Media query exceeds maximum nesting depth of %d.', $options->maxDepth));
					}
				} elseif ($type === CSSTokenType::RightParenthesis || $type === CSSTokenType::RightSquareBracket) {
					$closer = $type === CSSTokenType::RightParenthesis ? ')' : ']';
					if ($openStack !== [] && $openStack[count($openStack) - 1][1] === $closer) {
						[$openIdx] = array_pop($openStack);
						$pairEnd[$openIdx] = $totalTokens;
					} else {
						$itemError = true;
					}
				}
			}

			$tokens[] = [
				'type' => $type,
				'start' => $tokenizer->tokenStart,
				'end' => $tokenizer->tokenEnd,
				'value' => $tokenizer->tokenValue,
				'flag' => $tokenizer->tokenFlag,
				'num' => $tokenizer->tokenNumericValue,
				'unit' => $tokenizer->tokenUnit,
				'repr' => $tokenizer->tokenRepr,
			];
			$totalTokens++;
		}

		return [$tokens, $pairEnd, $items];
	}

	/**
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, unit: ?string, repr: ?string}> $tokens
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
			throw new InvalidArgumentException('Invalid media query: ' . CSSSyntaxScanner::trimCssWhitespace(substr($source, $tokens[$start]['start'], $tokens[$end - 1]['end'] - $tokens[$start]['start'])));
		}

		return $result;
	}

	/**
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, unit: ?string, repr: ?string}> $tokens
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
		if ($first['type'] === CSSTokenType::LeftParenthesis) {
			return self::parseConditionIterative($source, $tokens, $pairEnd, $start, $end, true);
		}
		if ($first['type'] !== CSSTokenType::Ident) {
			return null;
		}

		$kw = strtolower($first['value']);
		$pos = $start + 1;
		while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
			$pos++;
		}

		if ($kw === 'not') {
			if ($pos >= $end) {
				return null;
			}
			if ($tokens[$pos]['type'] === CSSTokenType::LeftParenthesis) {
				return self::parseConditionIterative($source, $tokens, $pairEnd, $start, $end, true);
			}
			if ($tokens[$pos]['type'] !== CSSTokenType::Ident) {
				return null;
			}
			$prefix = 'not ';
			$typeToken = $tokens[$pos];
			$pos++;
		} elseif ($kw === 'only') {
			if ($pos >= $end || $tokens[$pos]['type'] !== CSSTokenType::Ident) {
				return null;
			}
			$prefix = 'only ';
			$typeToken = $tokens[$pos];
			$pos++;
		} else {
			$prefix = '';
			$typeToken = $first;
		}

		$rawType = $typeToken['value'];
		if ($rawType === '' || str_starts_with($rawType, '--')) {
			return null;
		}
		$lowerType = strtolower($rawType);
		if (isset(self::RESERVED_MEDIA_TYPES[$lowerType])) {
			return null;
		}
		$serializedType = $prefix . CSS::escape($lowerType);

		while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
			$pos++;
		}
		if ($pos >= $end) {
			return $serializedType;
		}

		if ($tokens[$pos]['type'] !== CSSTokenType::Ident || strtolower($tokens[$pos]['value']) !== 'and') {
			return null;
		}
		$pos++;
		while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
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
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, unit: ?string, repr: ?string}> $tokens
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
			while ($start < $end && $tokens[$start]['type'] === CSSTokenType::Whitespace) {
				$start++;
			}
			while ($end > $start && $tokens[$end - 1]['type'] === CSSTokenType::Whitespace) {
				$end--;
			}
			if ($start >= $end) {
				return null;
			}

			// `<media-not> = not <media-in-parens>`
			if ($tokens[$start]['type'] === CSSTokenType::Ident && strtolower($tokens[$start]['value']) === 'not') {
				$pos = $start + 1;
				while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
					$pos++;
				}
				if ($pos >= $end || $tokens[$pos]['type'] !== CSSTokenType::LeftParenthesis) {
					return null;
				}
				$closeIdx = $pairEnd[$pos] ?? null;
				if ($closeIdx === null || $closeIdx + 1 !== $end) {
					return null;
				}
				$innerStart = $pos + 1;
				$innerEnd = $closeIdx;
				$childSlot = $nextSlot++;
				$slots[$childSlot] = null;

				$stack[] = [1, $start, $end, $allowOr, $destSlot, 'not', '', [$childSlot]];
				self::pushInParensWork($source, $tokens, $pairEnd, $innerStart, $innerEnd, $childSlot, $slots, $stack);
				continue;
			}

			// `<media-in-parens> [ and <media-in-parens> ]* | <media-in-parens> [ or <media-in-parens> ]*`
			$pos = $start;
			$expectedOp = '';
			$children = [];
			while ($pos < $end) {
				if ($tokens[$pos]['type'] !== CSSTokenType::LeftParenthesis) {
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
				while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
					$pos++;
				}
				if ($pos >= $end) {
					break;
				}

				if ($tokens[$pos]['type'] !== CSSTokenType::Ident) {
					return null;
				}
				$op = strtolower($tokens[$pos]['value']);
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
				while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
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
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, unit: ?string, repr: ?string}> $tokens
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
		while ($innerStart < $innerEnd && $tokens[$innerStart]['type'] === CSSTokenType::Whitespace) {
			$innerStart++;
		}
		while ($innerEnd > $innerStart && $tokens[$innerEnd - 1]['type'] === CSSTokenType::Whitespace) {
			$innerEnd--;
		}
		if ($innerStart >= $innerEnd) {
			return false;
		}

		$first = $tokens[$innerStart];
		$isNestedCondition = $first['type'] === CSSTokenType::LeftParenthesis;
		if (!$isNestedCondition && $first['type'] === CSSTokenType::Ident && strtolower($first['value']) === 'not') {
			$afterNot = $innerStart + 1;
			while ($afterNot < $innerEnd && $tokens[$afterNot]['type'] === CSSTokenType::Whitespace) {
				$afterNot++;
			}
			if ($afterNot >= $innerEnd || $tokens[$afterNot]['type'] !== CSSTokenType::LeftParenthesis) {
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
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, unit: ?string, repr: ?string}> $tokens
	 * @param array<int, int> $pairEnd
	 */
	private static function parseMediaFeature(
		string $source,
		array $tokens,
		array $pairEnd,
		int $start,
		int $end,
	): ?string {
		// Collect non-whitespace top-level indices inside feature parens
		$nonWs = [];
		$colonPos = null;
		$pos = $start;
		while ($pos < $end) {
			$tok = $tokens[$pos];
			if ($tok['type'] === CSSTokenType::Whitespace) {
				$pos++;
				continue;
			}
			if ($tok['type'] === CSSTokenType::Colon) {
				$colonPos ??= count($nonWs);
			}
			$nonWs[] = $pos;
			if ($tok['type'] === CSSTokenType::Function || $tok['type'] === CSSTokenType::LeftParenthesis || $tok['type'] === CSSTokenType::LeftSquareBracket) {
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
		if (count($nonWs) === 1 && $tokens[$nonWs[0]]['type'] === CSSTokenType::Ident) {
			return self::serializeFeatureName($tokens[$nonWs[0]]['value'], true);
		}

		// 2. `<mf-plain>` = `<mf-name> : <mf-value>`
		if ($colonPos !== null) {
			if ($colonPos !== 1 || $tokens[$nonWs[0]]['type'] !== CSSTokenType::Ident || count($nonWs) < 3) {
				return null;
			}
			$name = self::serializeFeatureName($tokens[$nonWs[0]]['value'], false);
			if ($name === null) {
				return null;
			}
			$valStart = $nonWs[2];
			$val = self::parseMediaFeatureValue($source, $tokens, $pairEnd, $valStart, $end);
			if ($val === null) {
				return null;
			}

			return $name . ': ' . $val;
		}

		// 3. `<mf-range>`
		return self::parseMediaFeatureRange($source, $tokens, $pairEnd, $start, $end);
	}

	private static function serializeFeatureName(string $ident, bool $allowDashed): ?string
	{
		if ($ident === '') {
			return null;
		}
		if (str_starts_with($ident, '--')) {
			if (!$allowDashed || strlen($ident) < 3) {
				return null;
			}
			return CSS::escape($ident);
		}
		$lower = strtolower($ident);
		if (isset(self::RESERVED_FEATURE_KEYWORDS[$lower])) {
			return null;
		}

		return CSS::escape($lower);
	}

	/**
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, unit: ?string, repr: ?string}> $tokens
	 * @param array<int, int> $pairEnd
	 */
	private static function parseMediaFeatureRange(
		string $source,
		array $tokens,
		array $pairEnd,
		int $start,
		int $end,
	): ?string {
		// Find top-level comparison operators: <, <=, =, >=, >
		$ops = [];
		$pos = $start;
		while ($pos < $end) {
			$tok = $tokens[$pos];
			if ($tok['type'] === CSSTokenType::Function || $tok['type'] === CSSTokenType::LeftParenthesis || $tok['type'] === CSSTokenType::LeftSquareBracket) {
				$close = $pairEnd[$pos] ?? null;
				if ($close === null || $close >= $end) {
					return null;
				}
				$pos = $close + 1;
				continue;
			}
			if ($tok['type'] === CSSTokenType::Delim) {
				$ch = $tok['value'];
				if ($ch === '<' || $ch === '>') {
					if ($pos + 1 < $end && $tokens[$pos + 1]['type'] === CSSTokenType::Delim && $tokens[$pos + 1]['value'] === '=') {
						$ops[] = ['op' => $ch . '=', 'start' => $pos, 'end' => $pos + 2];
						$pos += 2;
						continue;
					}
					$ops[] = ['op' => $ch, 'start' => $pos, 'end' => $pos + 1];
					$pos++;
					continue;
				}
				if ($ch === '=') {
					$ops[] = ['op' => '=', 'start' => $pos, 'end' => $pos + 1];
					$pos++;
					continue;
				}
			}
			$pos++;
		}

		$opCount = count($ops);
		if ($opCount === 1) {
			$op = $ops[0];
			$lhsName = self::trySingleFeatureName($tokens, $start, $op['start']);
			if ($lhsName !== null) {
				$rhsVal = self::parseMediaFeatureValue($source, $tokens, $pairEnd, $op['end'], $end);
				if ($rhsVal !== null) {
					return $lhsName . ' ' . $op['op'] . ' ' . $rhsVal;
				}
			}
			$rhsName = self::trySingleFeatureName($tokens, $op['end'], $end);
			if ($rhsName !== null) {
				$lhsVal = self::parseMediaFeatureValue($source, $tokens, $pairEnd, $start, $op['start']);
				if ($lhsVal !== null) {
					return $lhsVal . ' ' . $op['op'] . ' ' . $rhsName;
				}
			}
			return null;
		}

		if ($opCount === 2) {
			$op1 = $ops[0];
			$op2 = $ops[1];
			$bothLt = ($op1['op'] === '<' || $op1['op'] === '<=') && ($op2['op'] === '<' || $op2['op'] === '<=');
			$bothGt = ($op1['op'] === '>' || $op1['op'] === '>=') && ($op2['op'] === '>' || $op2['op'] === '>=');
			if (!$bothLt && !$bothGt) {
				return null;
			}

			$midName = self::trySingleFeatureName($tokens, $op1['end'], $op2['start']);
			if ($midName === null) {
				return null;
			}
			$leftVal = self::parseMediaFeatureValue($source, $tokens, $pairEnd, $start, $op1['start']);
			$rightVal = self::parseMediaFeatureValue($source, $tokens, $pairEnd, $op2['end'], $end);
			if ($leftVal === null || $rightVal === null) {
				return null;
			}

			return $leftVal . ' ' . $op1['op'] . ' ' . $midName . ' ' . $op2['op'] . ' ' . $rightVal;
		}

		return null;
	}

	/**
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, unit: ?string, repr: ?string}> $tokens
	 */
	private static function trySingleFeatureName(array $tokens, int $start, int $end): ?string
	{
		while ($start < $end && $tokens[$start]['type'] === CSSTokenType::Whitespace) {
			$start++;
		}
		while ($end > $start && $tokens[$end - 1]['type'] === CSSTokenType::Whitespace) {
			$end--;
		}
		if ($end - $start !== 1 || $tokens[$start]['type'] !== CSSTokenType::Ident) {
			return null;
		}

		return self::serializeFeatureName($tokens[$start]['value'], false);
	}

	/**
	 * Parses `<mf-value>` (`<number>`, `<dimension>`, `<ident>`, `<ratio>`, or CSS math/env function).
	 *
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, unit: ?string, repr: ?string}> $tokens
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
			if ($tok['type'] === CSSTokenType::Whitespace) {
				$pos++;
				continue;
			}
			$items[] = $pos;
			if ($tok['type'] === CSSTokenType::Function) {
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
			if ($tok['type'] === CSSTokenType::Number) {
				return self::formatNumber($tok['num']);
			}
			if ($tok['type'] === CSSTokenType::Dimension) {
				$num = self::formatNumber($tok['num']);
				$unit = $tok['unit'] ?? '';
				if ($num === null || $unit === '') {
					return null;
				}
				return $num . CSS::escape(strtolower($unit));
			}
			if ($tok['type'] === CSSTokenType::Ident) {
				$lower = strtolower($tok['value']);
				if (isset(self::RESERVED_FEATURE_KEYWORDS[$lower])) {
					return null;
				}
				return CSS::escape($lower);
			}
			if ($tok['type'] === CSSTokenType::Function) {
				$fnLower = strtolower($tok['value']);
				if (!isset(self::ALLOWED_VALUE_FUNCTIONS[$fnLower])) {
					return null;
				}
				$closeIdx = $pairEnd[$items[0]] ?? null;
				if ($closeIdx === null) {
					return null;
				}
				$innerStart = $items[0] + 1;
				$innerEnd = $closeIdx;
				$rawInner = $innerStart < $innerEnd
					? substr($source, $tokens[$innerStart]['start'], $tokens[$innerEnd - 1]['end'] - $tokens[$innerStart]['start'])
					: '';
				$cleanInner = str_contains($rawInner, '/*')
					? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($rawInner))
					: CSSSyntaxScanner::trimCssWhitespace($rawInner);
				if ($cleanInner === '') {
					return null;
				}
				return CSS::escape($fnLower) . '(' . $cleanInner . ')';
			}

			return null;
		}

		// `<ratio> = <number [0,∞]> / <number [0,∞]>`
		if ($count === 3
			&& $tokens[$items[0]]['type'] === CSSTokenType::Number
			&& $tokens[$items[1]]['type'] === CSSTokenType::Delim
			&& $tokens[$items[1]]['value'] === '/'
			&& $tokens[$items[2]]['type'] === CSSTokenType::Number
		) {
			$n1 = $tokens[$items[0]]['num'];
			$n2 = $tokens[$items[2]]['num'];
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
