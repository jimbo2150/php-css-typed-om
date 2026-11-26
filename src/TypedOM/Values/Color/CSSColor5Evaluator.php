<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstant;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSimplifier;

/**
 * Internal iterative evaluator for CSS Color Level 5 expressions (`color-mix()`,
 * Relative Color Syntax `from <color>`, and `light-dark()`).
 *
 * Uses a single `O(N)` `strcspn`-accelerated index scan and an explicit work stack
 * over `(start, end)` byte slices—never unbounded PHP recursion or per-level
 * substring copies—enforcing `CSSParserOptions` and node budgets deterministically.
 *
 * @internal
 */
final class CSSColor5Evaluator
{
	public const int MAX_COLOR_NODES = 1024;

	private const int FRAME_PARSE = 0;
	private const int FRAME_COLOR_MIX = 1;
	private const int FRAME_LIGHT_DARK = 2;
	private const int FRAME_RELATIVE = 3;

	/** @var array<string, bool> Value true = polar space, false = rectangular space */
	private const array INTERPOLATION_SPACES = [
		'srgb' => false,
		'srgb-linear' => false,
		'display-p3' => false,
		'a98-rgb' => false,
		'prophoto-rgb' => false,
		'rec2020' => false,
		'lab' => false,
		'oklab' => false,
		'xyz' => false,
		'xyz-d50' => false,
		'xyz-d65' => false,
		'hsl' => true,
		'hwb' => true,
		'lch' => true,
		'oklch' => true,
	];

	/** @var array<string, true> */
	private const array HUE_MODES = [
		'shorter' => true,
		'longer' => true,
		'increasing' => true,
		'decreasing' => true,
	];

	/** @var array<string, true> */
	private const array RELATIVE_FUNCTIONS = [
		'rgb' => true,
		'rgba' => true,
		'hsl' => true,
		'hsla' => true,
		'hwb' => true,
		'lab' => true,
		'lch' => true,
		'oklab' => true,
		'oklch' => true,
		'color' => true,
	];

	/**
	 * Evaluates a trimmed, fragment-validated CSS color string using iterative Color 5 resolution.
	 *
	 * @throws CSSDeferredColorException When syntactically valid but requiring runtime context
	 * @throws CSSParserLimitException When resource or node limits are exceeded
	 * @throws InvalidArgumentException When CSS color syntax is invalid
	 */
	public static function evaluate(
		string $source,
		CSSParserOptions $options,
		?CSSContext $context = null,
		bool $deferLightDark = false,
	): CSSColorValue|CSSKeywordValue {
		$len = strlen($source);
		$index = self::buildColorIndex($source, 0, $len);

		$colorNodes = 0;
		$mathBudget = 0;
		$slots = [];
		$nextSlot = 1;

		// Deferred sentinel value: ['deferred' => true]
		$workStack = [[self::FRAME_PARSE, 0, $len, 0, 0]];

		while ($workStack !== []) {
			$frame = array_pop($workStack);
			$kind = $frame[0];

			if ($kind === self::FRAME_COLOR_MIX) {
				[, $space, $hueMode, $slot1, $pct1, $slot2, $pct2, $targetSlot] = $frame;
				$val1 = $slots[$slot1] ?? null;
				$val2 = $slots[$slot2] ?? null;
				unset($slots[$slot1], $slots[$slot2]);

				$slots[$targetSlot] = self::reduceColorMix($space, $hueMode, $val1, $pct1, $val2, $pct2);
				continue;
			}

			if ($kind === self::FRAME_LIGHT_DARK) {
				[, $slotLight, $slotDark, $targetSlot] = $frame;
				$lightVal = $slots[$slotLight] ?? null;
				$darkVal = $slots[$slotDark] ?? null;
				unset($slots[$slotLight], $slots[$slotDark]);

				if ($deferLightDark) {
					$slots[$targetSlot] = ['deferred' => true];
					continue;
				}
				$scheme = $context?->getColorScheme() ?? 'light';
				$chosen = $scheme === 'dark' ? $darkVal : $lightVal;
				$slots[$targetSlot] = $chosen;
				continue;
			}

			if ($kind === self::FRAME_RELATIVE) {
				[, $fn, $targetSpace, $originSlot, $channelSlices, $alphaSlice, $depth, $targetSlot] = $frame;
				$originVal = $slots[$originSlot] ?? null;
				unset($slots[$originSlot]);

				$slots[$targetSlot] = self::reduceRelativeColor(
					$source,
					$index,
					$fn,
					$targetSpace,
					$originVal,
					$channelSlices,
					$alphaSlice,
					$options,
					$depth,
					$mathBudget,
				);
				continue;
			}

			// FRAME_PARSE: [FRAME_PARSE, start, end, depth, targetSlot]
			[, $start, $end, $depth, $targetSlot] = $frame;

			if ($options->maxDepth !== null && $depth > $options->maxDepth) {
				throw new CSSParserLimitException(sprintf('Maximum CSS color nesting depth of %d exceeded.', $options->maxDepth));
			}
			if (++$colorNodes > self::MAX_COLOR_NODES) {
				throw new CSSParserLimitException(sprintf('Maximum CSS color expression node count of %d exceeded.', self::MAX_COLOR_NODES));
			}

			self::trimSlice($source, $start, $end, $index);
			if ($start >= $end) {
				throw new InvalidArgumentException('CSS color value cannot be empty.');
			}

			if ($source[$start] === '#') {
				$hex = substr($source, $start, $end - $start);
				$slots[$targetSlot] = CSSColorValue::parseHexInternal($hex);
				continue;
			}

			// Try identifier (named color, transparent, currentcolor)
			$ident = self::decodeWholeIdentSlice($source, $start, $end);
			if ($ident !== null) {
				$lowerIdent = strtolower($ident);
				if ($lowerIdent === 'currentcolor') {
					$current = $context?->getCurrentColor();
					$slots[$targetSlot] = $current !== null ? $current : new CSSKeywordValue('currentcolor');
					continue;
				}
				$named = CSSColorValue::tryParseNamedColorIdentInternal($lowerIdent);
				if ($named === null) {
					throw new InvalidArgumentException('Unsupported CSS color value: ' . substr($source, $start, $end - $start));
				}
				$slots[$targetSlot] = $named;
				continue;
			}

			// Must be a function call `fn(...)` spanning the entire trimmed slice
			$fnIdent = CSSSyntaxScanner::decodeIdentAt($source, $start, $end);
			if ($fnIdent === null || $fnIdent['end'] >= $end || $source[$fnIdent['end']] !== '(') {
				throw new InvalidArgumentException('Unsupported CSS color value: ' . substr($source, $start, $end - $start));
			}
			$openPos = $fnIdent['end'];
			$closePos = $index['matchingParens'][$openPos] ?? null;
			if ($closePos !== $end - 1) {
				throw new InvalidArgumentException('Invalid CSS color function syntax: ' . substr($source, $start, $end - $start));
			}

			$fn = strtolower($fnIdent['ident']);
			$bodyStart = $openPos + 1;
			$bodyEnd = $closePos;
			self::trimSlice($source, $bodyStart, $bodyEnd, $index);
			if ($bodyStart >= $bodyEnd) {
				throw new InvalidArgumentException('Empty CSS color function body.');
			}

			if ($fn === 'light-dark') {
				$commas = $index['commasByOpen'][$openPos] ?? [];
				if (count($commas) !== 1) {
					throw new InvalidArgumentException('light-dark() requires exactly 2 color arguments.');
				}
				$s0 = $nextSlot++;
				$s1 = $nextSlot++;
				$workStack[] = [self::FRAME_LIGHT_DARK, $s0, $s1, $targetSlot];
				$workStack[] = [self::FRAME_PARSE, $commas[0] + 1, $closePos, $depth + 1, $s1];
				$workStack[] = [self::FRAME_PARSE, $openPos + 1, $commas[0], $depth + 1, $s0];
				continue;
			}

			if ($fn === 'color-mix') {
				$commas = $index['commasByOpen'][$openPos] ?? [];
				if (count($commas) !== 2) {
					throw new InvalidArgumentException('color-mix() requires exactly 3 comma-separated arguments.');
				}

				[$interpSpace, $hueMode] = self::parseInterpolationMethod(
					$source,
					$openPos + 1,
					$commas[0],
					$index,
				);

				[$c1Start, $c1End, $pct1] = self::parseColorMixComponent(
					$source,
					$commas[0] + 1,
					$commas[1],
					$index,
					$options,
					$depth,
					$mathBudget,
				);
				[$c2Start, $c2End, $pct2] = self::parseColorMixComponent(
					$source,
					$commas[1] + 1,
					$closePos,
					$index,
					$options,
					$depth,
					$mathBudget,
				);

				// Validate percentage sum eagerly before evaluating children
				$effP1 = $pct1 ?? (100.0 - ($pct2 ?? 50.0));
				$effP2 = $pct2 ?? (100.0 - ($pct1 ?? 50.0));
				if (($effP1 + $effP2) <= 0.0) {
					throw new InvalidArgumentException('color-mix() percentages must sum to greater than 0%.');
				}

				$s1 = $nextSlot++;
				$s2 = $nextSlot++;
				$workStack[] = [self::FRAME_COLOR_MIX, $interpSpace, $hueMode, $s1, $pct1, $s2, $pct2, $targetSlot];
				$workStack[] = [self::FRAME_PARSE, $c2Start, $c2End, $depth + 1, $s2];
				$workStack[] = [self::FRAME_PARSE, $c1Start, $c1End, $depth + 1, $s1];
				continue;
			}

			if (isset(self::RELATIVE_FUNCTIONS[$fn]) && self::startsWithFromKeyword($source, $bodyStart, $bodyEnd, $index, $afterFrom)) {
				$commas = $index['commasByOpen'][$openPos] ?? [];
				if ($commas !== []) {
					throw new InvalidArgumentException('Relative color syntax does not support comma-separated arguments.');
				}
				$slashes = $index['slashesByOpen'][$openPos] ?? [];
				if (count($slashes) > 1) {
					throw new InvalidArgumentException('Too many "/" separators in relative color syntax.');
				}

				$beforeSlashEnd = $slashes === [] ? $bodyEnd : $slashes[0];
				$tokens = self::splitSliceTopLevelTokens($source, $afterFrom, $beforeSlashEnd, $index, 8);
				$alphaSlice = null;
				if ($slashes !== []) {
					$alphaTokens = self::splitSliceTopLevelTokens($source, $slashes[0] + 1, $bodyEnd, $index, 4);
					if (count($alphaTokens) !== 1) {
						throw new InvalidArgumentException('Invalid alpha component in relative color syntax.');
					}
					$alphaSlice = $alphaTokens[0];
				}

				$targetSpace = $fn;
				$expectedTokenCount = $fn === 'color' ? 5 : 4;
				if (count($tokens) !== $expectedTokenCount) {
					throw new InvalidArgumentException(sprintf('Invalid channel count in relative %s() color syntax.', $fn));
				}

				$originSlice = $tokens[0];
				if ($fn === 'color') {
					[$spStart, $spEnd] = $tokens[1];
					$spaceIdent = self::decodeWholeIdentSlice($source, $spStart, $spEnd);
					if ($spaceIdent === null) {
						throw new InvalidArgumentException('Invalid color space in relative color() syntax.');
					}
					$lowerSpace = strtolower($spaceIdent);
					if (!isset(CSSColor::PREDEFINED_SPACES[$lowerSpace])) {
						throw new InvalidArgumentException('Relative color() syntax only supports predefined color spaces: ' . $spaceIdent);
					}
					$targetSpace = $lowerSpace;
					$channelSlices = [$tokens[2], $tokens[3], $tokens[4]];
				} else {
					$channelSlices = [$tokens[1], $tokens[2], $tokens[3]];
				}

				$originSlot = $nextSlot++;
				$workStack[] = [self::FRAME_RELATIVE, $fn, $targetSpace, $originSlot, $channelSlices, $alphaSlice, $depth, $targetSlot];
				$workStack[] = [self::FRAME_PARSE, $originSlice[0], $originSlice[1], $depth + 1, $originSlot];
				continue;
			}

			// Leaf Color 4 function (rgb, hsl, hwb, lab, lch, oklab, oklch, color)
			// Only slice at leaf level (outside Color 5 nesting) to preserve exact Color 4 parser semantics & shared math budget.
			$leafText = substr($source, $start, $end - $start);
			$slots[$targetSlot] = CSSColorValue::parseLeafFunctionInternal($fn, $leafText, $options, $mathBudget);
		}

		$root = $slots[0] ?? null;
		if (is_array($root) && isset($root['deferred'])) {
			throw new CSSDeferredColorException('CSS color expression requires runtime context to resolve.');
		}
		if (!$root instanceof CSSColorValue && !$root instanceof CSSKeywordValue) {
			throw new InvalidArgumentException('Failed to evaluate CSS color value.');
		}

		return $root;
	}

	/**
	 * Single O(N) scan indexing matching parens, top-level commas/slashes per open paren, and comment spans.
	 *
	 * @return array{
	 *   matchingParens: array<int, int>,
	 *   commasByOpen: array<int, list<int>>,
	 *   slashesByOpen: array<int, list<int>>,
	 *   commentEndByStart: array<int, int>,
	 *   commentStartByEnd: array<int, int>
	 * }
	 */
	private static function buildColorIndex(string $source, int $start, int $end): array
	{
		$matchingParens = [];
		$commasByOpen = [];
		$slashesByOpen = [];
		$commentEndByStart = [];
		$commentStartByEnd = [];
		$indexedNodes = 0;

		/** @var list<array{0: string, 1: int, 2: list<int>, 3: list<int>}> $delimStack */
		$delimStack = [];
		$offset = $start;
		$mask = "\\/\"'()[]{},";

		while ($offset < $end) {
			$offset += strcspn($source, $mask, $offset, $end - $offset);
			if ($offset >= $end) {
				break;
			}

			$ch = $source[$offset];
			if ($ch === '"' || $ch === "'") {
				$valid = false;
				$strEnd = CSSSyntaxScanner::scanQuotedStringEnd($source, $offset, $end, $valid);
				if (!$valid) {
					throw new InvalidArgumentException('Unclosed string in CSS color expression.');
				}
				$offset = $strEnd + 1;
				continue;
			}

			if ($ch === '\\') {
				$offset = self::advanceEscapeEnd($source, $offset, $end);
				continue;
			}

			if ($ch === '/') {
				if ($offset + 1 < $end && $source[$offset + 1] === '*') {
					$cEnd = strpos($source, '*/', $offset + 2);
					if ($cEnd === false || $cEnd + 1 >= $end) {
						throw new InvalidArgumentException('Unclosed comment in CSS color expression.');
					}
					if (++$indexedNodes > CSSNumericValue::MAX_MATH_NODES) {
						throw new CSSParserLimitException(sprintf('Maximum CSS color index node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
					}
					$afterComment = $cEnd + 2;
					$commentEndByStart[$offset] = $afterComment;
					$commentStartByEnd[$afterComment] = $offset;
					$offset = $afterComment;
					continue;
				}
				if ($delimStack !== []) {
					$topIdx = count($delimStack) - 1;
					if ($delimStack[$topIdx][0] === '(') {
						if (++$indexedNodes > CSSNumericValue::MAX_MATH_NODES) {
							throw new CSSParserLimitException(sprintf('Maximum CSS color index node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
						}
						$delimStack[$topIdx][3][] = $offset;
					}
				}
				$offset++;
				continue;
			}

			if ($ch === '(' || $ch === '[' || $ch === '{') {
				if (++$indexedNodes > CSSNumericValue::MAX_MATH_NODES) {
					throw new CSSParserLimitException(sprintf('Maximum CSS color index node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
				}
				$delimStack[] = [$ch, $offset, [], []];
				$offset++;
				continue;
			}

			if ($ch === ')' || $ch === ']' || $ch === '}') {
				if ($delimStack === []) {
					throw new InvalidArgumentException('Unbalanced delimiter in CSS color expression.');
				}
				[$openCh, $openPos, $commas, $slashes] = array_pop($delimStack);
				$expectedClose = $openCh === '(' ? ')' : ($openCh === '[' ? ']' : '}');
				if ($ch !== $expectedClose) {
					throw new InvalidArgumentException('Mismatched delimiter in CSS color expression.');
				}
				if ($openCh === '(') {
					$matchingParens[$openPos] = $offset;
					if ($commas !== []) {
						$commasByOpen[$openPos] = $commas;
					}
					if ($slashes !== []) {
						$slashesByOpen[$openPos] = $slashes;
					}
				}
				$offset++;
				continue;
			}

			// $ch === ','
			if ($delimStack !== []) {
				$topIdx = count($delimStack) - 1;
				if ($delimStack[$topIdx][0] === '(') {
					if (++$indexedNodes > CSSNumericValue::MAX_MATH_NODES) {
						throw new CSSParserLimitException(sprintf('Maximum CSS color index node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
					}
					$delimStack[$topIdx][2][] = $offset;
				}
			}
			$offset++;
		}

		if ($delimStack !== []) {
			throw new InvalidArgumentException('Unclosed delimiter in CSS color expression.');
		}

		return [
			'matchingParens' => $matchingParens,
			'commasByOpen' => $commasByOpen,
			'slashesByOpen' => $slashesByOpen,
			'commentEndByStart' => $commentEndByStart,
			'commentStartByEnd' => $commentStartByEnd,
		];
	}

	private static function advanceEscapeEnd(string $source, int $offset, int $end): int
	{
		if ($offset + 1 >= $end) {
			return $end;
		}
		$hexLen = strspn($source, '0123456789abcdefABCDEF', $offset + 1, min(6, $end - ($offset + 1)));
		if ($hexLen > 0) {
			$after = $offset + 1 + $hexLen;
			if ($after < $end) {
				$ws = $source[$after];
				if ($ws === "\r" && $after + 1 < $end && $source[$after + 1] === "\n") {
					return $after + 2;
				}
				if ($ws === ' ' || $ws === "\t" || $ws === "\n" || $ws === "\r" || $ws === "\f") {
					return $after + 1;
				}
			}

			return $after;
		}
		if ($source[$offset + 1] === "\r" && $offset + 2 < $end && $source[$offset + 2] === "\n") {
			return $offset + 3;
		}

		return $offset + 2;
	}

	/**
	 * @param array{matchingParens: array<int, int>, commasByOpen: array<int, list<int>>, slashesByOpen: array<int, list<int>>, commentEndByStart: array<int, int>, commentStartByEnd: array<int, int>} $index
	 */
	private static function trimSlice(string $source, int &$start, int &$end, array $index): void
	{
		while ($start < $end) {
			$start += strspn($source, CSSSyntaxScanner::CSS_WHITESPACE, $start, $end - $start);
			if (isset($index['commentEndByStart'][$start])) {
				$start = min($end, $index['commentEndByStart'][$start]);
				continue;
			}
			break;
		}
		while ($end > $start) {
			while ($end > $start && str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $source[$end - 1])) {
				$end--;
			}
			if (isset($index['commentStartByEnd'][$end]) && $index['commentStartByEnd'][$end] >= $start) {
				$end = $index['commentStartByEnd'][$end];
				continue;
			}
			break;
		}
	}

	/**
	 * Splits a slice into top-level whitespace/comment-separated `[start, end]` byte ranges.
	 *
	 * @param array{matchingParens: array<int, int>, commasByOpen: array<int, list<int>>, slashesByOpen: array<int, list<int>>, commentEndByStart: array<int, int>, commentStartByEnd: array<int, int>} $index
	 * @return list<array{0: int, 1: int}>
	 */
	private static function splitSliceTopLevelTokens(
		string $source,
		int $start,
		int $end,
		array $index,
		int $maxTokens,
	): array {
		$tokens = [];
		$tokenStart = null;
		$offset = $start;
		$mask = "\\/\"'()[]{} \t\n\r\f";

		while ($offset < $end) {
			$span = strcspn($source, $mask, $offset, $end - $offset);
			if ($span > 0) {
				$tokenStart ??= $offset;
				$offset += $span;
				if ($offset >= $end) {
					break;
				}
			}

			$ch = $source[$offset];
			if (str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $ch)) {
				if ($tokenStart !== null) {
					if (count($tokens) >= $maxTokens) {
						throw new InvalidArgumentException('Too many tokens in CSS color expression.');
					}
					$tokens[] = [$tokenStart, $offset];
					$tokenStart = null;
				}
				$offset += strspn($source, CSSSyntaxScanner::CSS_WHITESPACE, $offset, $end - $offset);
				continue;
			}

			if ($ch === '/' && isset($index['commentEndByStart'][$offset])) {
				if ($tokenStart !== null) {
					if (count($tokens) >= $maxTokens) {
						throw new InvalidArgumentException('Too many tokens in CSS color expression.');
					}
					$tokens[] = [$tokenStart, $offset];
					$tokenStart = null;
				}
				$offset = min($end, $index['commentEndByStart'][$offset]);
				continue;
			}

			$tokenStart ??= $offset;

			if ($ch === '"' || $ch === "'") {
				$valid = false;
				$strEnd = CSSSyntaxScanner::scanQuotedStringEnd($source, $offset, $end, $valid);
				$offset = $valid ? $strEnd + 1 : $end;
				continue;
			}
			if ($ch === '\\') {
				$offset = self::advanceEscapeEnd($source, $offset, $end);
				continue;
			}
			if ($ch === '(' && isset($index['matchingParens'][$offset])) {
				$offset = min($end, $index['matchingParens'][$offset] + 1);
				continue;
			}
			$offset++;
		}

		if ($tokenStart !== null) {
			if (count($tokens) >= $maxTokens) {
				throw new InvalidArgumentException('Too many tokens in CSS color expression.');
			}
			$tokens[] = [$tokenStart, $end];
		}

		return $tokens;
	}

	private static function decodeWholeIdentSlice(string $source, int $start, int $end): ?string
	{
		if ($start >= $end) {
			return null;
		}
		$decoded = CSSSyntaxScanner::decodeIdentAt($source, $start, $end);
		if ($decoded === null || $decoded['end'] !== $end) {
			return null;
		}

		return $decoded['ident'];
	}

	/**
	 * Checks whether the slice starts with the `from` keyword followed by CSS whitespace or a comment.
	 *
	 * @param array{matchingParens: array<int, int>, commasByOpen: array<int, list<int>>, slashesByOpen: array<int, list<int>>, commentEndByStart: array<int, int>, commentStartByEnd: array<int, int>} $index
	 */
	private static function startsWithFromKeyword(
		string $source,
		int $start,
		int $end,
		array $index,
		?int &$afterFrom,
	): bool {
		$ident = CSSSyntaxScanner::decodeIdentAt($source, $start, $end);
		if ($ident === null || strcasecmp($ident['ident'], 'from') !== 0) {
			return false;
		}
		$pos = $ident['end'];
		if ($pos >= $end) {
			return false;
		}
		$next = $source[$pos];
		if (!str_contains(CSSSyntaxScanner::CSS_WHITESPACE, $next) && !isset($index['commentEndByStart'][$pos])) {
			return false;
		}
		self::trimSlice($source, $pos, $end, $index);
		$afterFrom = $pos;

		return true;
	}

	/**
	 * Parses `<color-interpolation-method>`: `in <rect-space>` or `in <polar-space> [<hue-mode> hue]`.
	 *
	 * @param array{matchingParens: array<int, int>, commasByOpen: array<int, list<int>>, slashesByOpen: array<int, list<int>>, commentEndByStart: array<int, int>, commentStartByEnd: array<int, int>} $index
	 * @return array{0: string, 1: string} [colorSpace, hueInterpolationMode]
	 */
	private static function parseInterpolationMethod(string $source, int $start, int $end, array $index): array
	{
		self::trimSlice($source, $start, $end, $index);
		$tokens = self::splitSliceTopLevelTokens($source, $start, $end, $index, 5);
		$count = count($tokens);
		if ($count < 2 || $count > 4) {
			throw new InvalidArgumentException('Invalid color interpolation method in color-mix().');
		}

		$inIdent = self::decodeWholeIdentSlice($source, $tokens[0][0], $tokens[0][1]);
		if ($inIdent === null || strcasecmp($inIdent, 'in') !== 0) {
			throw new InvalidArgumentException('color-mix() interpolation method must start with "in".');
		}

		$spaceIdent = self::decodeWholeIdentSlice($source, $tokens[1][0], $tokens[1][1]);
		if ($spaceIdent === null) {
			throw new InvalidArgumentException('Invalid color space in color-mix().');
		}
		$space = strtolower($spaceIdent);
		if (!isset(self::INTERPOLATION_SPACES[$space])) {
			throw new InvalidArgumentException('Unsupported color-mix() interpolation space: ' . $spaceIdent);
		}

		$isPolar = self::INTERPOLATION_SPACES[$space];
		if ($count === 2) {
			return [$space, $isPolar ? 'shorter' : ''];
		}
		if (!$isPolar) {
			throw new InvalidArgumentException(sprintf('Hue interpolation method is not allowed for rectangular space "%s".', $space));
		}
		if ($count !== 4) {
			throw new InvalidArgumentException('Polar hue interpolation method requires "<mode> hue".');
		}

		$modeIdent = self::decodeWholeIdentSlice($source, $tokens[2][0], $tokens[2][1]);
		$hueIdent = self::decodeWholeIdentSlice($source, $tokens[3][0], $tokens[3][1]);
		if ($modeIdent === null || $hueIdent === null || strcasecmp($hueIdent, 'hue') !== 0) {
			throw new InvalidArgumentException('Invalid hue interpolation method in color-mix().');
		}
		$mode = strtolower($modeIdent);
		if (!isset(self::HUE_MODES[$mode])) {
			throw new InvalidArgumentException('Unsupported hue interpolation mode: ' . $modeIdent);
		}

		return [$space, $mode];
	}

	/**
	 * Parses `<color> && <percentage [0,100]>?` returning `[$colorStart, $colorEnd, ?float $percentage]`.
	 *
	 * @param array{matchingParens: array<int, int>, commasByOpen: array<int, list<int>>, slashesByOpen: array<int, list<int>>, commentEndByStart: array<int, int>, commentStartByEnd: array<int, int>} $index
	 * @return array{0: int, 1: int, 2: ?float}
	 */
	private static function parseColorMixComponent(
		string $source,
		int $start,
		int $end,
		array $index,
		CSSParserOptions $options,
		int $depth,
		int &$mathBudget,
	): array {
		self::trimSlice($source, $start, $end, $index);
		if ($start >= $end) {
			throw new InvalidArgumentException('Empty color argument in color-mix().');
		}

		$tokens = self::splitSliceTopLevelTokens($source, $start, $end, $index, 3);
		$count = count($tokens);
		if ($count === 1) {
			// Single token must be a color, never a bare percentage
			if (self::tryParseMixPercentage($source, $tokens[0][0], $tokens[0][1], $options, $depth, $mathBudget) !== null) {
				throw new InvalidArgumentException('color-mix() argument is missing a color value.');
			}

			return [$tokens[0][0], $tokens[0][1], null];
		}

		if ($count !== 2) {
			throw new InvalidArgumentException('Invalid component syntax in color-mix().');
		}

		$pct1 = self::tryParseMixPercentage($source, $tokens[1][0], $tokens[1][1], $options, $depth, $mathBudget);
		if ($pct1 !== null) {
			if (self::tryParseMixPercentage($source, $tokens[0][0], $tokens[0][1], $options, $depth, $mathBudget) !== null) {
				throw new InvalidArgumentException('color-mix() argument cannot contain two percentages.');
			}

			return [$tokens[0][0], $tokens[0][1], $pct1];
		}

		$pct0 = self::tryParseMixPercentage($source, $tokens[0][0], $tokens[0][1], $options, $depth, $mathBudget);
		if ($pct0 !== null) {
			return [$tokens[1][0], $tokens[1][1], $pct0];
		}

		throw new InvalidArgumentException('Invalid percentage or extra token in color-mix() argument.');
	}

	/**
	 * Parses an optional `color-mix()` percentage token.
	 *
	 * Per CSS Color 5 §3.1 & CSS Values 4 §10.8:
	 * - Literal percentages outside `[0%, 100%]` are rejected at parse time.
	 * - Math expressions (`calc()`, `min()`, `max()`, etc.) of category `'percent'` are clamped to `[0%, 100%]`.
	 * - Non-percentage numeric values (e.g. `50px`, `0.5`) return `null` unless clearly a malformed math percentage.
	 */
	private static function tryParseMixPercentage(
		string $source,
		int $start,
		int $end,
		CSSParserOptions $options,
		int $depth,
		int &$mathBudget,
	): ?float {
		$c0 = $source[$start];
		$isMathFn = ($c0 >= 'a' && $c0 <= 'z') || ($c0 >= 'A' && $c0 <= 'Z') || $c0 === '\\';
		if ($isMathFn) {
			$ident = CSSSyntaxScanner::decodeIdentAt($source, $start, $end);
			if ($ident === null || $ident['end'] >= $end || $source[$ident['end']] !== '(') {
				return null;
			}
			if (!CSSCalcParser::isMathFunctionName($ident['ident'])) {
				return null;
			}
			$numeric = CSSCalcParser::parseUnitOrMathSlice($source, $start, $end, $options, $depth + 1, true, null, $mathBudget);
			if (!$numeric instanceof CSSNumericValue
				|| $numeric->getNumericCategory() !== 'percent'
				|| $numeric->getNumericTypeInternal()->percentHint !== null
			) {
				throw new InvalidArgumentException('color-mix() math expression must resolve to a percentage.');
			}
			$clamped = CSSMathSimplifier::clampToRange($numeric, 0.0, 100.0);
			if (!$clamped instanceof CSSUnitValue || $clamped->unit !== 'percent') {
				throw new InvalidArgumentException('color-mix() percentage could not be resolved to a concrete percentage.');
			}

			return $clamped->value;
		}

		$numeric = CSSCalcParser::parseUnitOrMathSlice($source, $start, $end, $options, $depth + 1, false, null, $mathBudget);
		if ($numeric === null) {
			return null;
		}
		if (!$numeric instanceof CSSUnitValue || $numeric->unit !== 'percent') {
			throw new InvalidArgumentException('color-mix() weight must be a percentage.');
		}
		if ($numeric->value < 0.0 || $numeric->value > 100.0) {
			throw new InvalidArgumentException('color-mix() percentage must be between 0% and 100%.');
		}

		return $numeric->value;
	}

	/**
	 * @param CSSColorValue|CSSKeywordValue|array{deferred: true}|null $val1
	 * @param CSSColorValue|CSSKeywordValue|array{deferred: true}|null $val2
	 * @return CSSColorValue|array{deferred: true}
	 */
	private static function reduceColorMix(
		string $space,
		string $hueMode,
		CSSColorValue|CSSKeywordValue|array|null $val1,
		?float $pct1,
		CSSColorValue|CSSKeywordValue|array|null $val2,
		?float $pct2,
	): CSSColorValue|array {
		$deferred = false;
		if ((is_array($val1) && isset($val1['deferred'])) || $val1 instanceof CSSKeywordValue) {
			$deferred = true;
		}
		if ((is_array($val2) && isset($val2['deferred'])) || $val2 instanceof CSSKeywordValue) {
			$deferred = true;
		}

		if ($val1 instanceof CSSColorValue) {
			if ($val1->getSpaceNameInternal() !== '' && str_starts_with($val1->getSpaceNameInternal(), '--')) {
				throw new InvalidArgumentException(sprintf('Cannot mix custom color space "%s".', $val1->getSpaceNameInternal()));
			}
			if (!$val1->hasConcreteChannelsInternal()) {
				$deferred = true;
			}
		}
		if ($val2 instanceof CSSColorValue) {
			if ($val2->getSpaceNameInternal() !== '' && str_starts_with($val2->getSpaceNameInternal(), '--')) {
				throw new InvalidArgumentException(sprintf('Cannot mix custom color space "%s".', $val2->getSpaceNameInternal()));
			}
			if (!$val2->hasConcreteChannelsInternal()) {
				$deferred = true;
			}
		}

		if ($deferred) {
			return ['deferred' => true];
		}

		assert($val1 instanceof CSSColorValue && $val2 instanceof CSSColorValue);

		// CSS Color 5 §3.2 Percentage Normalization
		if ($pct1 === null && $pct2 === null) {
			$p1 = 50.0;
			$p2 = 50.0;
		} elseif ($pct1 === null) {
			$p1 = 100.0 - $pct2;
			$p2 = $pct2;
		} elseif ($pct2 === null) {
			$p1 = $pct1;
			$p2 = 100.0 - $pct1;
		} else {
			$p1 = $pct1;
			$p2 = $pct2;
		}

		$sum = $p1 + $p2;
		if ($sum <= 0.0) {
			throw new InvalidArgumentException('color-mix() percentages must sum to greater than 0%.');
		}
		$alphaMultiplier = $sum < 100.0 ? ($sum / 100.0) : 1.0;
		$w1 = $p1 / $sum;
		$w2 = $p2 / $sum;

		$c1 = $val1->to($space);
		$c2 = $val2->to($space);

		[$ch1, $a1] = self::extractInterpChannels($space, $c1);
		[$ch2, $a2] = self::extractInterpChannels($space, $c2);

		// Missing alpha handling & clamping to [0, 1] per CSS Color 4 §12.2
		$bothAlphaMissing = ($a1 === null && $a2 === null);
		if ($bothAlphaMissing) {
			$effA1 = 1.0;
			$effA2 = 1.0;
			$aInterp = 1.0;
		} else {
			$a1Val = self::clampUnitFloat($a1 ?? $a2);
			$a2Val = self::clampUnitFloat($a2 ?? $a1);
			$effA1 = $a1Val;
			$effA2 = $a2Val;
			$aInterp = $w1 * $effA1 + $w2 * $effA2;
		}

		$isPolar = self::INTERPOLATION_SPACES[$space];
		$hueIdx = match ($space) {
			'hsl', 'hwb' => 0,
			'lch', 'oklch' => 2,
			default => -1,
		};

		/** @var array{0: ?float, 1: ?float, 2: ?float} $mixed */
		$mixed = [null, null, null];
		for ($i = 0; $i < 3; $i++) {
			$v1 = $ch1[$i];
			$v2 = $ch2[$i];
			if ($v1 === null && $v2 === null) {
				$mixed[$i] = null;
				continue;
			}
			$f1 = $v1 ?? $v2;
			$f2 = $v2 ?? $v1;

			if ($isPolar && $i === $hueIdx) {
				$mixed[$i] = self::interpolateHue($f1, $f2, $w1, $w2, $hueMode);
			} elseif ($aInterp > 0.0) {
				$val = ($w1 * $f1 * $effA1 + $w2 * $f2 * $effA2) / $aInterp;
				$mixed[$i] = self::assertFinite($val, 'color-mix() channel');
			} else {
				$val = $w1 * $f1 + $w2 * $f2;
				$mixed[$i] = self::assertFinite($val, 'color-mix() channel');
			}
		}

		if ($bothAlphaMissing) {
			$alphaObj = new CSSKeywordValue('none');
		} else {
			$finalAlpha = self::assertFinite($aInterp * $alphaMultiplier, 'color-mix() alpha');
			$alphaObj = new CSSUnitValue($finalAlpha * 100.0, 'percent');
		}

		return self::buildMixedResult($space, $mixed, $alphaObj);
	}

	private static function interpolateHue(float $rawH1, float $rawH2, float $w1, float $w2, string $mode): float
	{
		$h1 = fmod(fmod($rawH1, 360.0) + 360.0, 360.0);
		$h2 = fmod(fmod($rawH2, 360.0) + 360.0, 360.0);
		$diff = $h2 - $h1;

		switch ($mode) {
			case 'shorter':
				if ($diff > 180.0) {
					$h1 += 360.0;
				} elseif ($diff < -180.0) {
					$h2 += 360.0;
				}
				break;
			case 'longer':
				if ($diff > 0.0 && $diff < 180.0) {
					$h1 += 360.0;
				} elseif ($diff > -180.0 && $diff <= 0.0) {
					$h2 += 360.0;
				}
				break;
			case 'increasing':
				if ($h2 < $h1) {
					$h2 += 360.0;
				}
				break;
			case 'decreasing':
				if ($h1 < $h2) {
					$h1 += 360.0;
				}
				break;
		}

		$result = fmod($w1 * $h1 + $w2 * $h2, 360.0);
		if ($result < 0.0) {
			$result += 360.0;
		}

		return $result === 0.0 ? 0.0 : $result;
	}

	/**
	 * Extracts normalized channel floats (or null for `none`) in the interpolation space's canonical scale.
	 *
	 * @return array{0: array{0: ?float, 1: ?float, 2: ?float}, 1: ?float}
	 */
	private static function extractInterpChannels(string $space, CSSColorValue $color): array
	{
		$comps = $color->getComponentValuesInternal();
		$alphaComp = $comps[3];
		$alpha = $alphaComp instanceof CSSKeywordValue ? null : self::resolveUnitFraction($alphaComp);

		$c0 = $comps[0];
		$c1 = $comps[1];
		$c2 = $comps[2];

		return match ($space) {
			'lab' => [[
				$c0 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c0, 100.0),
				$c1 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c1, 125.0),
				$c2 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c2, 125.0),
			], $alpha],
			'lch' => [[
				$c0 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c0, 100.0),
				$c1 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c1, 150.0),
				$c2 instanceof CSSKeywordValue ? null : self::resolveUnitAngle($c2),
			], $alpha],
			'oklab' => [[
				$c0 instanceof CSSKeywordValue ? null : self::resolveUnitFraction($c0),
				$c1 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c1, 0.4),
				$c2 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c2, 0.4),
			], $alpha],
			'oklch' => [[
				$c0 instanceof CSSKeywordValue ? null : self::resolveUnitFraction($c0),
				$c1 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c1, 0.4),
				$c2 instanceof CSSKeywordValue ? null : self::resolveUnitAngle($c2),
			], $alpha],
			'hsl' => [[
				$c0 instanceof CSSKeywordValue ? null : self::resolveUnitAngle($c0),
				$c1 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c1, 100.0),
				$c2 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c2, 100.0),
			], $alpha],
			'hwb' => [[
				$c0 instanceof CSSKeywordValue ? null : self::resolveUnitAngle($c0),
				$c1 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c1, 100.0),
				$c2 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c2, 100.0),
			], $alpha],
			default => [[
				$c0 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c0, 1.0),
				$c1 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c1, 1.0),
				$c2 instanceof CSSKeywordValue ? null : self::resolveUnitLinear($c2, 1.0),
			], $alpha],
		};
	}

	/**
	 * @param array{0: ?float, 1: ?float, 2: ?float} $mixed
	 */
	private static function buildMixedResult(
		string $space,
		array $mixed,
		CSSUnitValue|CSSKeywordValue $alpha,
	): CSSColorValue {
		$noneOrUnit = static fn (?float $v, float $scale, string $unit): CSSUnitValue|CSSKeywordValue => $v === null
			? new CSSKeywordValue('none')
			: new CSSUnitValue(self::assertFinite($v * $scale, 'color-mix() channel'), $unit);

		if ($space === 'lab') {
			return CSSLab::fromValidated(
				$noneOrUnit($mixed[0], 1.0, 'percent'),
				$noneOrUnit($mixed[1], 1.0, 'number'),
				$noneOrUnit($mixed[2], 1.0, 'number'),
				$alpha,
			);
		}
		if ($space === 'lch') {
			return CSSLCH::fromValidated(
				$noneOrUnit($mixed[0], 1.0, 'percent'),
				$noneOrUnit($mixed[1], 1.0, 'number'),
				$noneOrUnit($mixed[2], 1.0, 'deg'),
				$alpha,
			);
		}
		if ($space === 'oklab') {
			return CSSOKLab::fromValidated(
				$noneOrUnit($mixed[0], 100.0, 'percent'),
				$noneOrUnit($mixed[1], 1.0, 'number'),
				$noneOrUnit($mixed[2], 1.0, 'number'),
				$alpha,
			);
		}
		if ($space === 'oklch') {
			return CSSOKLCH::fromValidated(
				$noneOrUnit($mixed[0], 100.0, 'percent'),
				$noneOrUnit($mixed[1], 1.0, 'number'),
				$noneOrUnit($mixed[2], 1.0, 'deg'),
				$alpha,
			);
		}
		if ($space === 'hsl') {
			$hsl = CSSHSL::fromValidated(
				$noneOrUnit($mixed[0], 1.0, 'deg'),
				$noneOrUnit($mixed[1], 1.0, 'percent'),
				$noneOrUnit($mixed[2], 1.0, 'percent'),
				$alpha,
			);

			return $hsl->to('srgb');
		}
		if ($space === 'hwb') {
			$hwb = CSSHWB::fromValidated(
				$noneOrUnit($mixed[0], 1.0, 'deg'),
				$noneOrUnit($mixed[1], 1.0, 'percent'),
				$noneOrUnit($mixed[2], 1.0, 'percent'),
				$alpha,
			);

			return $hwb->to('srgb');
		}

		return CSSColor::fromValidated(
			new CSSKeywordValue($space),
			[
				$noneOrUnit($mixed[0], 1.0, 'number'),
				$noneOrUnit($mixed[1], 1.0, 'number'),
				$noneOrUnit($mixed[2], 1.0, 'number'),
			],
			$alpha,
		);
	}

	/**
	 * @param array{matchingParens: array<int, int>, commasByOpen: array<int, list<int>>, slashesByOpen: array<int, list<int>>, commentEndByStart: array<int, int>, commentStartByEnd: array<int, int>} $index
	 * @param CSSColorValue|CSSKeywordValue|array{deferred: true}|null $originVal
	 * @param list<array{0: int, 1: int}> $channelSlices
	 * @param ?array{0: int, 1: int} $alphaSlice
	 * @return CSSColorValue|array{deferred: true}
	 */
	private static function reduceRelativeColor(
		string $source,
		array $index,
		string $fn,
		string $targetSpace,
		CSSColorValue|CSSKeywordValue|array|null $originVal,
		array $channelSlices,
		?array $alphaSlice,
		CSSParserOptions $options,
		int $depth,
		int &$mathBudget,
	): CSSColorValue|array {
		$deferred = (is_array($originVal) && isset($originVal['deferred']))
			|| $originVal instanceof CSSKeywordValue;

		if ($originVal instanceof CSSColorValue) {
			if (str_starts_with($originVal->getSpaceNameInternal(), '--')) {
				throw new InvalidArgumentException(sprintf('Cannot derive relative color from custom color space "%s".', $originVal->getSpaceNameInternal()));
			}
			if (!$originVal->hasConcreteChannelsInternal()) {
				$deferred = true;
			}
		}

		if ($deferred) {
			[$dummySymbols] = self::buildRelativeChannelBindings($fn, $targetSpace, null);
			// Still validate all outer channel & alpha expressions and budgets before returning deferred sentinel
			foreach ($channelSlices as $idx => [$cs, $ce]) {
				self::evalRelativeChannel(
					$source,
					$cs,
					$ce,
					$fn,
					$targetSpace,
					$idx,
					$dummySymbols,
					[],
					$options,
					$depth,
					$mathBudget,
				);
			}
			if ($alphaSlice !== null) {
				self::evalRelativeAlpha(
					$source,
					$alphaSlice[0],
					$alphaSlice[1],
					$dummySymbols,
					[],
					null,
					$options,
					$depth,
					$mathBudget,
				);
			}

			return ['deferred' => true];
		}

		assert($originVal instanceof CSSColorValue);
		$convertedOrigin = $originVal->to($targetSpace);
		[$channelSymbols, $missingFlags, $originAlpha] = self::buildRelativeChannelBindings($fn, $targetSpace, $convertedOrigin);

		$resolvedChannels = [];
		foreach ($channelSlices as $idx => [$cs, $ce]) {
			$resolvedChannels[] = self::evalRelativeChannel(
				$source,
				$cs,
				$ce,
				$fn,
				$targetSpace,
				$idx,
				$channelSymbols,
				$missingFlags,
				$options,
				$depth,
				$mathBudget,
			);
		}

		$resolvedAlpha = $alphaSlice === null
			? $originAlpha->clone()
			: self::evalRelativeAlpha(
				$source,
				$alphaSlice[0],
				$alphaSlice[1],
				$channelSymbols,
				$missingFlags,
				$originAlpha,
				$options,
				$depth,
				$mathBudget,
			);

		return match ($fn) {
			'rgb', 'rgba' => CSSRGB::fromValidated($resolvedChannels[0], $resolvedChannels[1], $resolvedChannels[2], $resolvedAlpha),
			'hsl', 'hsla' => CSSHSL::fromValidated($resolvedChannels[0], $resolvedChannels[1], $resolvedChannels[2], $resolvedAlpha),
			'hwb' => CSSHWB::fromValidated($resolvedChannels[0], $resolvedChannels[1], $resolvedChannels[2], $resolvedAlpha),
			'lab' => CSSLab::fromValidated($resolvedChannels[0], $resolvedChannels[1], $resolvedChannels[2], $resolvedAlpha),
			'lch' => CSSLCH::fromValidated($resolvedChannels[0], $resolvedChannels[1], $resolvedChannels[2], $resolvedAlpha),
			'oklab' => CSSOKLab::fromValidated($resolvedChannels[0], $resolvedChannels[1], $resolvedChannels[2], $resolvedAlpha),
			'oklch' => CSSOKLCH::fromValidated($resolvedChannels[0], $resolvedChannels[1], $resolvedChannels[2], $resolvedAlpha),
			'color' => CSSColor::fromValidated(new CSSKeywordValue($targetSpace), [$resolvedChannels[0], $resolvedChannels[1], $resolvedChannels[2]], $resolvedAlpha),
			default => throw new InvalidArgumentException('Unsupported relative color function: ' . $fn),
		};
	}

	/**
	 * Builds CSS Color 5 §4 channel keyword bindings (`array<string, float>`) and missing flags.
	 *
	 * @return array{0: array<string, float>, 1: array<string, true>, 2: CSSNumericValue|CSSKeywordValue}
	 */
	private static function buildRelativeChannelBindings(
		string $fn,
		string $targetSpace,
		?CSSColorValue $origin,
	): array {
		$names = match ($fn) {
			'rgb', 'rgba' => ['r', 'g', 'b'],
			'hsl', 'hsla' => ['h', 's', 'l'],
			'hwb' => ['h', 'w', 'b'],
			'lab' => ['l', 'a', 'b'],
			'lch' => ['l', 'c', 'h'],
			'oklab' => ['l', 'a', 'b'],
			'oklch' => ['l', 'c', 'h'],
			'color' => in_array($targetSpace, ['xyz', 'xyz-d50', 'xyz-d65'], true) ? ['x', 'y', 'z'] : ['r', 'g', 'b'],
			default => throw new InvalidArgumentException('Unsupported relative color function: ' . $fn),
		};

		if ($origin === null) {
			return [
				[$names[0] => 0.0, $names[1] => 0.0, $names[2] => 0.0, 'alpha' => 0.0],
				[],
				new CSSUnitValue(100.0, 'percent'),
			];
		}

		$comps = $origin->getComponentValuesInternal();
		$missing = [];
		$symbols = [];

		$scales = match ($fn) {
			'rgb', 'rgba' => [255.0, 255.0, 255.0],
			'hsl', 'hsla' => [1.0, 100.0, 100.0],
			'hwb' => [1.0, 100.0, 100.0],
			'lab' => [100.0, 125.0, 125.0],
			'lch' => [100.0, 150.0, 1.0],
			'oklab' => [1.0, 0.4, 0.4],
			'oklch' => [1.0, 0.4, 1.0],
			'color' => [1.0, 1.0, 1.0],
		};

		for ($i = 0; $i < 3; $i++) {
			$name = $names[$i];
			$comp = $comps[$i];
			if ($comp instanceof CSSKeywordValue) {
				$missing[$name] = true;
				$symbols[$name] = 0.0;
				continue;
			}
			$isAngle = ($fn === 'hsl' || $fn === 'hsla' || $fn === 'hwb') && $i === 0
				|| ($fn === 'lch' || $fn === 'oklch') && $i === 2;
			if ($isAngle) {
				$deg = self::resolveUnitAngle($comp);
				$deg = fmod(fmod($deg, 360.0) + 360.0, 360.0);
				$symbols[$name] = $deg === 0.0 ? 0.0 : $deg;
			} else {
				$symbols[$name] = self::resolveUnitLinear($comp, $scales[$i]);
			}
		}

		$alphaComp = $comps[3];
		if ($alphaComp instanceof CSSKeywordValue) {
			$missing['alpha'] = true;
			$symbols['alpha'] = 0.0;
		} else {
			$symbols['alpha'] = self::resolveUnitFraction($alphaComp);
		}

		return [$symbols, $missing, $alphaComp];
	}

	/**
	 * @param array<string, float> $channelSymbols
	 * @param array<string, true> $missingFlags
	 */
	private static function evalRelativeChannel(
		string $source,
		int $start,
		int $end,
		string $fn,
		string $targetSpace,
		int $channelIdx,
		array $channelSymbols,
		array $missingFlags,
		CSSParserOptions $options,
		int $depth,
		int &$mathBudget,
	): CSSNumericValue|CSSKeywordValue {
		$ident = self::decodeWholeIdentSlice($source, $start, $end);
		if ($ident !== null) {
			$lower = strtolower($ident);
			if ($lower === 'none') {
				return new CSSKeywordValue('none');
			}
			if (isset($channelSymbols[$lower])) {
				if (isset($missingFlags[$lower])) {
					return new CSSKeywordValue('none');
				}
				$val = $channelSymbols[$lower];

				return self::buildChannelFromNumber($fn, $channelIdx, $val);
			}
			throw new InvalidArgumentException('Invalid channel keyword "' . $ident . '" in relative ' . $fn . '().');
		}

		$numeric = CSSCalcParser::parseUnitOrMathSlice($source, $start, $end, $options, $depth + 1, true, $channelSymbols, $mathBudget);
		assert($numeric instanceof CSSNumericValue);

		$kind = self::channelKind($fn, $channelIdx);
		$numeric = self::clampDegenerateChannel($numeric, $fn, $channelIdx, $kind);

		if ($kind === 'angle') {
			return self::normalizeRelativeAngle($numeric);
		}
		if ($kind === 'percent_rgb') {
			return self::normalizeRelativeRgbPercent($numeric);
		}
		if ($kind === 'percent_100') {
			return self::normalizeRelativePercent($numeric, 1.0);
		}
		if ($kind === 'percent_1') {
			return self::normalizeRelativePercent($numeric, 100.0);
		}

		// number_or_percent (lab a/b, lch c, oklab a/b, oklch c, color() channels)
		$cat = $numeric->getNumericCategory();
		if ($numeric->getNumericTypeInternal()->percentHint !== null || ($cat !== 'number' && $cat !== 'percent')) {
			throw new InvalidArgumentException('Relative color channel must be a number, percentage, or "none".');
		}

		return $numeric;
	}

	/**
	 * @param array<string, float> $channelSymbols
	 * @param array<string, true> $missingFlags
	 */
	private static function evalRelativeAlpha(
		string $source,
		int $start,
		int $end,
		array $channelSymbols,
		array $missingFlags,
		CSSNumericValue|CSSKeywordValue|null $originAlpha,
		CSSParserOptions $options,
		int $depth,
		int &$mathBudget,
	): CSSNumericValue|CSSKeywordValue {
		$ident = self::decodeWholeIdentSlice($source, $start, $end);
		if ($ident !== null) {
			$lower = strtolower($ident);
			if ($lower === 'none') {
				return new CSSKeywordValue('none');
			}
			if (isset($channelSymbols[$lower])) {
				if (isset($missingFlags[$lower])) {
					return new CSSKeywordValue('none');
				}
				$val = self::assertFinite($channelSymbols[$lower] * 100.0, 'relative alpha');

				return new CSSUnitValue($val, 'percent');
			}
			throw new InvalidArgumentException('Invalid alpha keyword "' . $ident . '" in relative color.');
		}

		$numeric = CSSCalcParser::parseUnitOrMathSlice($source, $start, $end, $options, $depth + 1, true, $channelSymbols, $mathBudget);
		assert($numeric instanceof CSSNumericValue);
		$numeric = self::clampDegenerateValue($numeric, 0.0, 1.0, 0.0, 100.0);

		return self::normalizeRelativePercent($numeric, 100.0);
	}

	private static function channelKind(string $fn, int $idx): string
	{
		return match ($fn) {
			'rgb', 'rgba' => 'percent_rgb',
			'hsl', 'hsla', 'hwb' => $idx === 0 ? 'angle' : 'percent_100',
			'lab' => $idx === 0 ? 'percent_100' : 'number_or_percent',
			'lch' => match ($idx) {
				0 => 'percent_100',
				1 => 'number_or_percent',
				default => 'angle',
			},
			'oklab' => $idx === 0 ? 'percent_1' : 'number_or_percent',
			'oklch' => match ($idx) {
				0 => 'percent_1',
				1 => 'number_or_percent',
				default => 'angle',
			},
			default => 'number_or_percent',
		};
	}

	private static function buildChannelFromNumber(string $fn, int $idx, float $val): CSSUnitValue
	{
		$kind = self::channelKind($fn, $idx);
		return match ($kind) {
			'angle' => new CSSUnitValue(self::assertFinite($val, 'relative hue'), 'deg'),
			'percent_rgb' => new CSSUnitValue(self::assertFinite(($val / 255.0) * 100.0, 'relative rgb channel'), 'percent'),
			'percent_100' => new CSSUnitValue(self::assertFinite($val, 'relative percent channel'), 'percent'),
			'percent_1' => new CSSUnitValue(self::assertFinite($val * 100.0, 'relative lightness channel'), 'percent'),
			default => new CSSUnitValue(self::assertFinite($val, 'relative channel'), 'number'),
		};
	}

	private static function clampDegenerateChannel(
		CSSNumericValue $numeric,
		string $fn,
		int $idx,
		string $kind,
	): CSSNumericValue {
		if (!self::isDegenerateNumeric($numeric)) {
			return $numeric;
		}
		if ($kind === 'angle') {
			return CSSMathSimplifier::clampToRange($numeric, 0.0, 0.0);
		}
		[$numMin, $numMax, $pctMin, $pctMax] = match ($fn) {
			'rgb', 'rgba' => [0.0, 255.0, 0.0, 100.0],
			'hsl', 'hsla', 'hwb' => [0.0, 100.0, 0.0, 100.0],
			'lab' => $idx === 0 ? [0.0, 100.0, 0.0, 100.0] : [-125.0, 125.0, -100.0, 100.0],
			'lch' => $idx === 0 ? [0.0, 100.0, 0.0, 100.0] : [0.0, 150.0, 0.0, 100.0],
			'oklab' => $idx === 0 ? [0.0, 1.0, 0.0, 100.0] : [-0.4, 0.4, -100.0, 100.0],
			'oklch' => $idx === 0 ? [0.0, 1.0, 0.0, 100.0] : [0.0, 0.4, 0.0, 100.0],
			default => [0.0, 1.0, 0.0, 100.0],
		};

		return self::clampDegenerateValue($numeric, $numMin, $numMax, $pctMin, $pctMax);
	}

	private static function clampDegenerateValue(
		CSSNumericValue $numeric,
		float $numMin,
		float $numMax,
		float $pctMin,
		float $pctMax,
	): CSSNumericValue {
		if (!self::isDegenerateNumeric($numeric)) {
			return $numeric;
		}
		$cat = $numeric->getNumericCategory();

		return $cat === 'percent'
			? CSSMathSimplifier::clampToRange($numeric, $pctMin, $pctMax)
			: CSSMathSimplifier::clampToRange($numeric, $numMin, $numMax);
	}

	private static function isDegenerateNumeric(CSSNumericValue $numeric): bool
	{
		if ($numeric instanceof CSSMathConstant) {
			return !is_finite($numeric->getFloatValue());
		}
		if ($numeric instanceof CSSMathProduct && $numeric->getNumericTypeInternal()->percentHint === null) {
			$factors = $numeric->values->values;

			return count($factors) === 2
				&& $factors[0] instanceof CSSMathConstant
				&& !is_finite($factors[0]->getFloatValue())
				&& $factors[1] instanceof CSSUnitValue
				&& $factors[1]->value === 1.0;
		}

		return false;
	}

	private static function normalizeRelativeAngle(CSSNumericValue $numeric): CSSNumericValue
	{
		if ($numeric instanceof CSSUnitValue) {
			if ($numeric->unit === 'number') {
				return new CSSUnitValue($numeric->value, 'deg');
			}
			if ($numeric->getNumericCategory() === 'angle') {
				return $numeric;
			}
			throw new InvalidArgumentException('Hue channel must be an angle, number, or "none".');
		}
		$cat = $numeric->getNumericCategory();
		if ($numeric->getNumericTypeInternal()->percentHint !== null || ($cat !== 'angle' && $cat !== 'number')) {
			throw new InvalidArgumentException('Hue channel math expression must be an angle or number.');
		}

		return $numeric;
	}

	private static function normalizeRelativeRgbPercent(CSSNumericValue $numeric): CSSNumericValue
	{
		if ($numeric instanceof CSSUnitValue) {
			if ($numeric->unit === 'percent') {
				return $numeric;
			}
			if ($numeric->unit === 'number') {
				return new CSSUnitValue(self::assertFinite(($numeric->value / 255.0) * 100.0, 'relative rgb channel'), 'percent');
			}
			throw new InvalidArgumentException('rgb() channels must be numbers, percentages, or "none".');
		}
		$cat = $numeric->getNumericCategory();
		if ($numeric->getNumericTypeInternal()->percentHint !== null || ($cat !== 'number' && $cat !== 'percent')) {
			throw new InvalidArgumentException('rgb() math channel must be a number or percentage.');
		}
		if ($cat === 'number') {
			return $numeric->mul(new CSSUnitValue(100.0 / 255.0, 'percent'));
		}

		return $numeric;
	}

	private static function normalizeRelativePercent(CSSNumericValue $numeric, float $numberScale): CSSNumericValue
	{
		if ($numeric instanceof CSSUnitValue) {
			if ($numeric->unit === 'percent') {
				return $numeric;
			}
			if ($numeric->unit === 'number') {
				return new CSSUnitValue(self::assertFinite($numeric->value * $numberScale, 'relative color channel'), 'percent');
			}
			throw new InvalidArgumentException('Color channel must be a number, percentage, or "none".');
		}
		$cat = $numeric->getNumericCategory();
		if ($numeric->getNumericTypeInternal()->percentHint !== null || ($cat !== 'number' && $cat !== 'percent')) {
			throw new InvalidArgumentException('Color channel math expression must be a number or percentage.');
		}
		if ($cat === 'number' && $numberScale !== 1.0) {
			return $numeric->mul(new CSSUnitValue($numberScale, 'percent'));
		}

		return $numeric;
	}

	private static function resolveUnitFraction(CSSNumericValue $component): float
	{
		if (!$component instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Color operation requires concrete numeric channels.');
		}

		return $component->unit === 'percent' ? $component->value / 100.0 : $component->value;
	}

	private static function resolveUnitLinear(CSSNumericValue $component, float $percentReference): float
	{
		if (!$component instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Color operation requires concrete numeric channels.');
		}

		return $component->unit === 'percent' ? ($component->value / 100.0) * $percentReference : $component->value;
	}

	private static function resolveUnitAngle(CSSNumericValue $component): float
	{
		if (!$component instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Color operation requires concrete numeric channels.');
		}

		return $component->unit === 'number' ? $component->value : $component->to('deg')->value;
	}

	private static function clampUnitFloat(float $v): float
	{
		if ($v <= 0.0) {
			return 0.0;
		}
		if ($v >= 1.0) {
			return 1.0;
		}

		return $v;
	}

	private static function assertFinite(float $v, string $label): float
	{
		if (!is_finite($v)) {
			throw new InvalidArgumentException(sprintf('Non-finite value produced for %s.', $label));
		}

		return $v === 0.0 ? 0.0 : $v;
	}
}
