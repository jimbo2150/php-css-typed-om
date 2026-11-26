<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use Countable;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Stringable;

/**
 * Iterative, stack-safe Selectors Level 4 parser, CSSOM §6.7.1 canonical serializer,
 * and Selectors Level 4 §16 `(A, B, C)` specificity calculator.
 *
 * @see https://drafts.csswg.org/selectors-4/
 * @see https://drafts.csswg.org/cssom/#serialize-a-selector
 */
final readonly class CSSSelectorList implements Countable, Stringable
{
	public const int MAX_SELECTOR_NODES = 16_384;

	/** @var array<string, string> */
	private const array LEGACY_PSEUDO_ELEMENTS = [
		'before' => 'before',
		'after' => 'after',
		'first-line' => 'first-line',
		'first-letter' => 'first-letter',
	];

	/** @var array<string, true> */
	private const array COMPOUND_ONLY_PSEUDOS = [
		'host' => true,
		'host-context' => true,
	];

	/** @var array<string, true> */
	private const array NTH_CHILD_PSEUDOS = [
		'nth-child' => true,
		'nth-last-child' => true,
	];

	/** @var array<string, true> */
	private const array NTH_SIMPLE_PSEUDOS = [
		'nth-of-type' => true,
		'nth-last-of-type' => true,
		'nth-col' => true,
		'nth-last-col' => true,
	];

	/** @var array<string, true> */
	private const array ATTR_MATCHERS = [
		'=' => true,
		'~=' => true,
		'|=' => true,
		'^=' => true,
		'$=' => true,
		'*=' => true,
	];

	/**
	 * @param list<array{
	 *   leadingCombinator: string,
	 *   compounds: list<array<string, mixed>>,
	 *   combinators: list<string>,
	 *   text: string,
	 *   hasNesting: bool
	 * }> $complexSelectors
	 */
	private function __construct(
		private array $complexSelectors,
		private string $cssText,
		private bool $leadingCombinator,
	) {
	}

	/**
	 * Parses a strict Selectors Level 4 `<selector-list>`.
	 *
	 * @throws InvalidArgumentException On invalid selector syntax
	 * @throws CSSParserLimitException On byte, depth, item, or node limits
	 */
	public static function parse(string $selectorText, ?CSSParserOptions $options = null): self
	{
		return self::parseInternal($selectorText, $options, false, false);
	}

	/**
	 * Parses a `<forgiving-selector-list>`, dropping invalid comma-separated items.
	 *
	 * @throws InvalidArgumentException On structural errors (control bytes, unclosed comments/strings)
	 * @throws CSSParserLimitException On byte, depth, item, or node limits
	 */
	public static function parseForgiving(string $selectorText, ?CSSParserOptions $options = null): self
	{
		return self::parseInternal($selectorText, $options, true, false);
	}

	/**
	 * Parses a `<relative-selector-list>` (allowing leading `>`, `+`, `~` combinators).
	 *
	 * @throws InvalidArgumentException On invalid selector syntax (when `$forgiving` is false)
	 * @throws CSSParserLimitException On byte, depth, item, or node limits
	 */
	public static function parseRelative(
		string $selectorText,
		?CSSParserOptions $options = null,
		bool $forgiving = false,
	): self {
		return self::parseInternal($selectorText, $options, $forgiving, true);
	}

	/**
	 * @internal Entry point for `CSSStyleRule` / `CSSParser` supporting browser-mode comment control bytes.
	 */
	public static function parseForStyleRuleInternal(
		string $selectorText,
		?CSSParserOptions $options,
		bool $allowRelative,
		bool $allowCommentControlBytes,
	): self {
		return self::parseInternal($selectorText, $options, false, $allowRelative, !$allowCommentControlBytes);
	}

	/** @return list<string> Canonically serialized complex selectors */
	public function getSelectors(): array
	{
		$out = [];
		foreach ($this->complexSelectors as $complex) {
			$out[] = $complex['text'];
		}

		return $out;
	}

	public function getCssText(): string
	{
		return $this->cssText;
	}

	public function __toString(): string
	{
		return $this->cssText;
	}

	public function count(): int
	{
		return count($this->complexSelectors);
	}

	public function hasLeadingCombinator(): bool
	{
		return $this->leadingCombinator;
	}

	/**
	 * Returns `(A, B, C)` specificity for each complex selector in the list.
	 *
	 * @param ?array{0: int, 1: int, 2: int} $parentSpecificity Specificity substituted for `&` nesting selectors
	 * @return list<array{0: int, 1: int, 2: int}>
	 */
	public function getSpecificities(?array $parentSpecificity = null): array
	{
		$parent = $parentSpecificity ?? [0, 0, 0];
		$result = [];
		foreach ($this->complexSelectors as $complex) {
			$result[] = self::evaluateComplexSpecificity($complex, $parent);
		}

		return $result;
	}

	/**
	 * Returns the maximum `(A, B, C)` specificity across the list (or `[0, 0, 0]` if empty).
	 *
	 * @param ?array{0: int, 1: int, 2: int} $parentSpecificity
	 * @return array{0: int, 1: int, 2: int}
	 */
	public function getMaxSpecificity(?array $parentSpecificity = null): array
	{
		$max = [0, 0, 0];
		foreach ($this->getSpecificities($parentSpecificity) as $spec) {
			if (self::compareSpecificity($spec, $max) > 0) {
				$max = $spec;
			}
		}

		return $max;
	}

	/**
	 * Lexicographically compares two `(A, B, C)` specificity tuples (`-1`, `0`, `1`).
	 *
	 * @param array{0: int, 1: int, 2: int} $left
	 * @param array{0: int, 1: int, 2: int} $right
	 */
	public static function compareSpecificity(array $left, array $right): int
	{
		for ($i = 0; $i < 3; $i++) {
			if ($left[$i] < $right[$i]) {
				return -1;
			}
			if ($left[$i] > $right[$i]) {
				return 1;
			}
		}

		return 0;
	}

	private static function parseInternal(
		string $selectorText,
		?CSSParserOptions $options,
		bool $forgiving,
		bool $relative,
		bool $checkCommentControlBytes = true,
	): self {
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($selectorText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS selector exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		[$tokens, $pairEnd, $hasControl, $hasUnclosed] = self::tokenizeSelector($selectorText, $options, $checkCommentControlBytes);
		if ($hasControl) {
			throw new InvalidArgumentException('CSS selector contains disallowed control characters.');
		}
		if ($hasUnclosed) {
			throw new InvalidArgumentException('CSS selector contains unclosed string, url, comment, or delimiter.');
		}

		$tokenCount = count($tokens);
		$start = 0;
		$end = $tokenCount;
		while ($start < $end && $tokens[$start]['type'] === CSSTokenType::Whitespace) {
			$start++;
		}
		while ($end > $start && $tokens[$end - 1]['type'] === CSSTokenType::Whitespace) {
			$end--;
		}

		if ($start >= $end) {
			if ($forgiving) {
				return new self([], '', false);
			}
			throw new InvalidArgumentException('CSS selector list cannot be empty.');
		}

		$nodeCount = 0;
		$lists = self::parseSelectorListIterative(
			$selectorText,
			$tokens,
			$pairEnd,
			$start,
			$end,
			$forgiving,
			$relative,
			$options->maxDepth,
			$nodeCount,
		);

		$rootComplexes = $lists[0];
		$texts = [];
		$hasLeading = false;
		foreach ($rootComplexes as $complex) {
			$texts[] = $complex['text'];
			if ($complex['leadingCombinator'] !== '') {
				$hasLeading = true;
			}
		}

		return new self($rootComplexes, implode(', ', $texts), $hasLeading);
	}

	/**
	 * @return array{
	 *   0: list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, unit: ?string, repr: ?string, hasEscapes: bool}>,
	 *   1: array<int, int>,
	 *   2: bool,
	 *   3: bool
	 * }
	 */
	private static function tokenizeSelector(string $source, CSSParserOptions $options, bool $checkCommentControlBytes = true): array
	{
		$tokenizer = CSSTokenizer::forParserInternal($source, $options, false, true, 0, null, true);
		$tokens = [];
		$pairEnd = [];
		$openStack = [];
		$hasControl = false;
		$hasUnclosed = false;
		$idx = 0;

		while (true) {
			$type = $tokenizer->advanceInternal();
			if (($checkCommentControlBytes && $tokenizer->commentControlErrorOffsets !== [])
				|| $tokenizer->tokenControlByteOffset !== null
			) {
				$hasControl = true;
			}
			if ($tokenizer->tokenUnclosedCommentOffset !== null || $tokenizer->tokenClosedAtEof) {
				$hasUnclosed = true;
			}
			if ($type === CSSTokenType::BadString || $type === CSSTokenType::BadUrl) {
				$hasUnclosed = true;
			}
			if ($type === CSSTokenType::Eof) {
				if ($openStack !== []) {
					$hasUnclosed = true;
				}
				break;
			}

			if ($idx >= self::MAX_SELECTOR_NODES) {
				throw new CSSParserLimitException(sprintf('CSS selector exceeds maximum node limit of %d.', self::MAX_SELECTOR_NODES));
			}

			if ($type === CSSTokenType::Semicolon || $type === CSSTokenType::LeftCurlyBracket || $type === CSSTokenType::RightCurlyBracket) {
				$hasUnclosed = true;
			} elseif ($type === CSSTokenType::LeftParenthesis || $type === CSSTokenType::Function) {
				$openStack[] = [$idx, ')'];
				if ($options->maxDepth !== null && count($openStack) > $options->maxDepth) {
					throw new CSSParserLimitException(sprintf('CSS selector exceeds maximum nesting depth of %d.', $options->maxDepth));
				}
			} elseif ($type === CSSTokenType::LeftSquareBracket) {
				$openStack[] = [$idx, ']'];
				if ($options->maxDepth !== null && count($openStack) > $options->maxDepth) {
					throw new CSSParserLimitException(sprintf('CSS selector exceeds maximum nesting depth of %d.', $options->maxDepth));
				}
			} elseif ($type === CSSTokenType::RightParenthesis || $type === CSSTokenType::RightSquareBracket) {
				$closer = $type === CSSTokenType::RightParenthesis ? ')' : ']';
				if ($openStack !== [] && $openStack[count($openStack) - 1][1] === $closer) {
					[$openIdx] = array_pop($openStack);
					$pairEnd[$openIdx] = $idx;
				} else {
					$hasUnclosed = true;
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
				'hasEscapes' => $tokenizer->tokenHasEscapes,
			];
			$idx++;
		}

		return [$tokens, $pairEnd, $hasControl, $hasUnclosed];
	}

	/**
	 * Iterative parser over nested selector-list frames (`:is`, `:where`, `:not`, `:has`, `:nth-child(... of ...)`, `:host`, `::slotted`).
	 *
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, repr: ?string, hasEscapes: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 * @return array<int, list<array<string, mixed>>>
	 */
	private static function parseSelectorListIterative(
		string $source,
		array $tokens,
		array $pairEnd,
		int $rootStart,
		int $rootEnd,
		bool $rootForgiving,
		bool $rootRelative,
		?int $maxDepth,
		int &$nodeCount,
	): array {
		$listSlots = [];
		$nextListId = 1;
		/** @var list<array<string, mixed>> $stack */
		$stack = [[
			'phase' => 0,
			'listId' => 0,
			'start' => $rootStart,
			'end' => $rootEnd,
			'forgiving' => $rootForgiving,
			'relative' => $rootRelative,
			'allowPseudoElements' => true,
			'insideHas' => false,
			'compoundOnly' => false,
			'depth' => 0,
			'forgivingItem' => false,
			'complexes' => [],
			'pendingComplex' => null,
			'pendingChildren' => [],
			'remainingRanges' => [],
		]];

		while ($stack !== []) {
			$topIdx = count($stack) - 1;
			$frame = $stack[$topIdx];

			if ($frame['phase'] === 0) {
				if ($maxDepth !== null && $frame['depth'] > $maxDepth) {
					if ($frame['forgivingItem']) {
						array_pop($stack);
						continue;
					}
					throw new CSSParserLimitException(sprintf('CSS selector exceeds maximum nesting depth of %d.', $maxDepth));
				}

				$ranges = self::splitCommaRanges($tokens, $pairEnd, $frame['start'], $frame['end']);
				if (count($ranges) > CSSStyleValue::MAX_LIST_ITEMS) {
					throw new CSSParserLimitException(sprintf('CSS selector list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
				}
				if ($frame['compoundOnly'] && count($ranges) > 1) {
					if ($frame['forgivingItem']) {
						$parentIdx = self::findAbortableForgivingItemFrame($stack, $topIdx);
						if ($parentIdx !== null) {
							array_splice($stack, $parentIdx + 1);
							$stack[$parentIdx]['pendingComplex'] = null;
							$stack[$parentIdx]['phase'] = 2;
							continue;
						}
					}
					throw new InvalidArgumentException('Expected a single compound selector.');
				}

				$stack[$topIdx]['remainingRanges'] = array_reverse($ranges);
				$stack[$topIdx]['phase'] = 1;
				continue;
			}

			if ($frame['phase'] === 1) {
				if ($frame['remainingRanges'] === []) {
					if (!$frame['forgiving'] && $frame['complexes'] === []) {
						if ($frame['forgivingItem']) {
							$parentIdx = self::findAbortableForgivingItemFrame($stack, $topIdx);
							if ($parentIdx !== null) {
								array_splice($stack, $parentIdx + 1);
								$stack[$parentIdx]['pendingComplex'] = null;
								$stack[$parentIdx]['phase'] = 2;
								continue;
							}
						}
						throw new InvalidArgumentException('CSS selector list cannot be empty.');
					}
					$listSlots[$frame['listId']] = $frame['complexes'];
					array_pop($stack);
					continue;
				}

				[$cStart, $cEnd] = array_pop($stack[$topIdx]['remainingRanges']);
				while ($cStart < $cEnd && $tokens[$cStart]['type'] === CSSTokenType::Whitespace) {
					$cStart++;
				}
				while ($cEnd > $cStart && $tokens[$cEnd - 1]['type'] === CSSTokenType::Whitespace) {
					$cEnd--;
				}

				if ($cStart >= $cEnd) {
					if ($frame['forgiving']) {
						continue;
					}
					if ($frame['forgivingItem']) {
						$parentIdx = self::findAbortableForgivingItemFrame($stack, $topIdx);
						if ($parentIdx !== null) {
							array_splice($stack, $parentIdx + 1);
							$stack[$parentIdx]['pendingComplex'] = null;
							$stack[$parentIdx]['phase'] = 2;
							continue;
						}
					}
					throw new InvalidArgumentException('Empty selector inside CSS selector list.');
				}

				$parsed = self::parseComplexSelectorStructure(
					$source,
					$tokens,
					$pairEnd,
					$cStart,
					$cEnd,
					$frame['relative'],
					$frame['allowPseudoElements'],
					$frame['insideHas'],
					$frame['compoundOnly'],
					$nextListId,
					$nodeCount,
				);

				if ($parsed === null) {
					if ($frame['forgiving']) {
						continue;
					}
					if ($frame['forgivingItem']) {
						$parentIdx = self::findAbortableForgivingItemFrame($stack, $topIdx);
						if ($parentIdx !== null) {
							array_splice($stack, $parentIdx + 1);
							$stack[$parentIdx]['pendingComplex'] = null;
							$stack[$parentIdx]['phase'] = 2;
							continue;
						}
					}
					$badSlice = substr($source, $tokens[$cStart]['start'], $tokens[$cEnd - 1]['end'] - $tokens[$cStart]['start']);
					throw new InvalidArgumentException('Invalid CSS selector: ' . $badSlice);
				}

				$stack[$topIdx]['pendingComplex'] = $parsed['complex'];
				$stack[$topIdx]['phase'] = 2;

				if ($parsed['childFrames'] !== []) {
					$childForgivingItem = $frame['forgiving'] || $frame['forgivingItem'];
					$childDepth = (int) $frame['depth'] + 1;
					if ($maxDepth !== null && $childDepth > $maxDepth) {
						throw new CSSParserLimitException(sprintf('CSS selector exceeds maximum nesting depth of %d.', $maxDepth));
					}
					for ($i = count($parsed['childFrames']) - 1; $i >= 0; $i--) {
						$cf = $parsed['childFrames'][$i];
						$stack[] = [
							'phase' => 0,
							'listId' => $cf['listId'],
							'start' => $cf['start'],
							'end' => $cf['end'],
							'forgiving' => $cf['forgiving'],
							'relative' => $cf['relative'],
							'allowPseudoElements' => $cf['allowPseudoElements'],
							'insideHas' => $cf['insideHas'],
							'compoundOnly' => $cf['compoundOnly'],
							'depth' => $childDepth,
							'forgivingItem' => $childForgivingItem && !$cf['forgiving'],
							'complexes' => [],
							'pendingComplex' => null,
							'pendingChildren' => [],
							'remainingRanges' => [],
						];
					}
				}
				continue;
			}

			// phase === 2: finalize pending complex selector with resolved child lists
			$pending = $frame['pendingComplex'];
			$stack[$topIdx]['pendingComplex'] = null;
			$stack[$topIdx]['phase'] = 1;
			if ($pending !== null) {
				$stack[$topIdx]['complexes'][] = self::finalizeComplexSelector($pending, $listSlots);
			}
		}

		return $listSlots;
	}

	/**
	 * @param list<array<string, mixed>> $stack
	 */
	private static function findAbortableForgivingItemFrame(array $stack, int $currentIdx): ?int
	{
		for ($i = $currentIdx; $i >= 0; $i--) {
			if ($stack[$i]['forgiving'] && $stack[$i]['phase'] === 2) {
				return $i;
			}
		}

		return null;
	}

	/**
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, repr: ?string, hasEscapes: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 * @return list<array{0: int, 1: int}>
	 */
	private static function splitCommaRanges(array $tokens, array $pairEnd, int $start, int $end): array
	{
		$ranges = [];
		$segStart = $start;
		$pos = $start;
		while ($pos < $end) {
			$tok = $tokens[$pos];
			if ($tok['type'] === CSSTokenType::LeftParenthesis || $tok['type'] === CSSTokenType::Function || $tok['type'] === CSSTokenType::LeftSquareBracket) {
				$close = $pairEnd[$pos] ?? null;
				$pos = ($close !== null && $close < $end) ? $close + 1 : $end;
				continue;
			}
			if ($tok['type'] === CSSTokenType::Comma) {
				$ranges[] = [$segStart, $pos];
				$segStart = $pos + 1;
			}
			$pos++;
		}
		$ranges[] = [$segStart, $end];

		return $ranges;
	}

	/**
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, repr: ?string, hasEscapes: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 * @return ?array{
	 *   complex: array<string, mixed>,
	 *   childFrames: list<array{listId: int, start: int, end: int, forgiving: bool, relative: bool, allowPseudoElements: bool, insideHas: bool, compoundOnly: bool}>
	 * }
	 */
	private static function parseComplexSelectorStructure(
		string $source,
		array $tokens,
		array $pairEnd,
		int $start,
		int $end,
		bool $relative,
		bool $allowPseudoElements,
		bool $insideHas,
		bool $compoundOnly,
		int &$nextListId,
		int &$nodeCount,
	): ?array {
		$pos = $start;
		$leadingCombinator = '';

		if ($tokens[$pos]['type'] === CSSTokenType::Delim) {
			$ch = $tokens[$pos]['value'];
			if ($ch === '>' || $ch === '+' || $ch === '~') {
				if (!$relative || $compoundOnly) {
					return null;
				}
				$leadingCombinator = $ch;
				$pos++;
				while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
					$pos++;
				}
				if ($pos >= $end) {
					return null;
				}
			}
		}

		$compounds = [];
		$combinators = [];
		$childFrames = [];

		while ($pos < $end) {
			$compound = self::parseCompoundSelector(
				$source,
				$tokens,
				$pairEnd,
				$pos,
				$end,
				$allowPseudoElements,
				$insideHas,
				$nextListId,
				$nodeCount,
				$childFrames,
			);
			if ($compound === null) {
				return null;
			}
			$compounds[] = $compound;

			if ($pos >= $end) {
				break;
			}
			if ($compoundOnly) {
				return null;
			}

			$hadWs = false;
			while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
				$hadWs = true;
				$pos++;
			}
			if ($pos >= $end) {
				break;
			}

			$comb = null;
			if ($tokens[$pos]['type'] === CSSTokenType::Delim) {
				$ch = $tokens[$pos]['value'];
				if ($ch === '>' || $ch === '+' || $ch === '~') {
					$comb = $ch;
					$pos++;
				} elseif ($ch === '|' && $pos + 1 < $end && $tokens[$pos + 1]['type'] === CSSTokenType::Delim && $tokens[$pos + 1]['value'] === '|') {
					$comb = '||';
					$pos += 2;
				}
			}

			if ($comb !== null) {
				while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
					$pos++;
				}
				if ($pos >= $end) {
					return null;
				}
				$combinators[] = $comb;
			} elseif ($hadWs) {
				$combinators[] = ' ';
			} else {
				return null;
			}
		}

		if ($compounds === []) {
			return null;
		}

		return [
			'complex' => [
				'leadingCombinator' => $leadingCombinator,
				'compounds' => $compounds,
				'combinators' => $combinators,
			],
			'childFrames' => $childFrames,
		];
	}

	/**
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, repr: ?string, hasEscapes: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 * @param list<array{listId: int, start: int, end: int, forgiving: bool, relative: bool, allowPseudoElements: bool, insideHas: bool, compoundOnly: bool}> $childFrames
	 * @return ?array<string, mixed>
	 */
	private static function parseCompoundSelector(
		string $source,
		array $tokens,
		array $pairEnd,
		int &$pos,
		int $end,
		bool $allowPseudoElements,
		bool $insideHas,
		int &$nextListId,
		int &$nodeCount,
		array &$childFrames,
	): ?array {
		$typeSelector = null;
		$subclasses = [];
		$pseudoElements = [];
		$afterPseudoElement = false;
		$hasAny = false;
		$hasNesting = false;

		// Optional <type-selector> or universal `*` with optional <ns-prefix>
		$tok = $tokens[$pos];
		if ($tok['type'] === CSSTokenType::Ident) {
			if ($pos + 1 < $end
				&& $tokens[$pos + 1]['type'] === CSSTokenType::Delim
				&& $tokens[$pos + 1]['value'] === '|'
				&& !($pos + 2 < $end && $tokens[$pos + 2]['type'] === CSSTokenType::Delim && $tokens[$pos + 2]['value'] === '|')
			) {
				if ($pos + 2 >= $end) {
					return null;
				}
				$nsPrefix = CSS::escape($tok['value']) . '|';
				$pos += 2;
				$target = $tokens[$pos];
				if ($target['type'] === CSSTokenType::Delim && $target['value'] === '*') {
					$typeSelector = ['ns' => $nsPrefix, 'name' => '*', 'isUniversal' => true, 'hasExplicitNs' => true];
					$pos++;
				} elseif ($target['type'] === CSSTokenType::Ident) {
					$typeSelector = ['ns' => $nsPrefix, 'name' => CSS::escape($target['value']), 'isUniversal' => false, 'hasExplicitNs' => true];
					$pos++;
				} else {
					return null;
				}
			} else {
				$typeSelector = ['ns' => '', 'name' => CSS::escape($tok['value']), 'isUniversal' => false, 'hasExplicitNs' => false];
				$pos++;
			}
			self::incrementNodes($nodeCount);
			$hasAny = true;
		} elseif ($tok['type'] === CSSTokenType::Delim && $tok['value'] === '*') {
			if ($pos + 1 < $end
				&& $tokens[$pos + 1]['type'] === CSSTokenType::Delim
				&& $tokens[$pos + 1]['value'] === '|'
				&& !($pos + 2 < $end && $tokens[$pos + 2]['type'] === CSSTokenType::Delim && $tokens[$pos + 2]['value'] === '|')
			) {
				if ($pos + 2 >= $end) {
					return null;
				}
				$pos += 2;
				$target = $tokens[$pos];
				if ($target['type'] === CSSTokenType::Delim && $target['value'] === '*') {
					$typeSelector = ['ns' => '*|', 'name' => '*', 'isUniversal' => true, 'hasExplicitNs' => false];
					$pos++;
				} elseif ($target['type'] === CSSTokenType::Ident) {
					$typeSelector = ['ns' => '*|', 'name' => CSS::escape($target['value']), 'isUniversal' => false, 'hasExplicitNs' => false];
					$pos++;
				} else {
					return null;
				}
			} else {
				$typeSelector = ['ns' => '', 'name' => '*', 'isUniversal' => true, 'hasExplicitNs' => false];
				$pos++;
			}
			self::incrementNodes($nodeCount);
			$hasAny = true;
		} elseif ($tok['type'] === CSSTokenType::Delim && $tok['value'] === '|') {
			// Ensure `|` is NOT `||` column combinator
			if ($pos + 1 >= $end || ($tokens[$pos + 1]['type'] === CSSTokenType::Delim && $tokens[$pos + 1]['value'] === '|')) {
				return null;
			}
			$pos++;
			$target = $tokens[$pos];
			if ($target['type'] === CSSTokenType::Delim && $target['value'] === '*') {
				$typeSelector = ['ns' => '|', 'name' => '*', 'isUniversal' => true, 'hasExplicitNs' => true];
				$pos++;
			} elseif ($target['type'] === CSSTokenType::Ident) {
				$typeSelector = ['ns' => '|', 'name' => CSS::escape($target['value']), 'isUniversal' => false, 'hasExplicitNs' => true];
				$pos++;
			} else {
				return null;
			}
			self::incrementNodes($nodeCount);
			$hasAny = true;
		}

		while ($pos < $end) {
			$cur = $tokens[$pos];
			$type = $cur['type'];

			if ($type === CSSTokenType::Hash) {
				if ($afterPseudoElement || $cur['flag'] !== CSSToken::HASH_ID) {
					return null;
				}
				self::incrementNodes($nodeCount);
				$subclasses[] = ['kind' => 'id', 'text' => '#' . CSS::escape($cur['value']), 'spec' => [1, 0, 0]];
				$pos++;
				$hasAny = true;
				continue;
			}

			if ($type === CSSTokenType::Delim && $cur['value'] === '.') {
				if ($afterPseudoElement || $pos + 1 >= $end || $tokens[$pos + 1]['type'] !== CSSTokenType::Ident) {
					return null;
				}
				self::incrementNodes($nodeCount);
				$subclasses[] = ['kind' => 'class', 'text' => '.' . CSS::escape($tokens[$pos + 1]['value']), 'spec' => [0, 1, 0]];
				$pos += 2;
				$hasAny = true;
				continue;
			}

			if ($type === CSSTokenType::Delim && $cur['value'] === '&') {
				if ($afterPseudoElement) {
					return null;
				}
				self::incrementNodes($nodeCount);
				$subclasses[] = ['kind' => 'nesting', 'text' => '&'];
				$hasNesting = true;
				$pos++;
				$hasAny = true;
				continue;
			}

			if ($type === CSSTokenType::LeftSquareBracket) {
				if ($afterPseudoElement) {
					return null;
				}
				$closeIdx = $pairEnd[$pos] ?? null;
				if ($closeIdx === null || $closeIdx >= $end) {
					return null;
				}
				$attrText = self::parseAttributeSelector($tokens, $pos + 1, $closeIdx);
				if ($attrText === null) {
					return null;
				}
				self::incrementNodes($nodeCount);
				$subclasses[] = ['kind' => 'attr', 'text' => $attrText, 'spec' => [0, 1, 0]];
				$pos = $closeIdx + 1;
				$hasAny = true;
				continue;
			}

			if ($type === CSSTokenType::Colon) {
				if ($pos + 1 >= $end) {
					return null;
				}
				$next = $tokens[$pos + 1];
				if ($next['type'] === CSSTokenType::Colon) {
					if (!$allowPseudoElements || $afterPseudoElement || $pos + 2 >= $end) {
						return null;
					}
					$pe = self::parsePseudoElement(
						$source,
						$tokens,
						$pairEnd,
						$pos + 2,
						$end,
						$nextListId,
						$nodeCount,
						$childFrames,
					);
					if ($pe === null) {
						return null;
					}
					$pseudoElements[] = $pe['node'];
					$pos = $pe['nextPos'];
					$afterPseudoElement = true;
					$hasAny = true;
					continue;
				}

				$pc = self::parsePseudoClassOrLegacyElement(
					$source,
					$tokens,
					$pairEnd,
					$pos + 1,
					$end,
					$allowPseudoElements,
					$insideHas,
					$afterPseudoElement,
					$nextListId,
					$nodeCount,
					$childFrames,
				);
				if ($pc === null) {
					return null;
				}
				if ($pc['isPseudoElement']) {
					$pseudoElements[] = $pc['node'];
					$afterPseudoElement = true;
				} elseif ($afterPseudoElement) {
					$pseudoElements[] = $pc['node'];
				} else {
					$subclasses[] = $pc['node'];
				}
				$pos = $pc['nextPos'];
				$hasAny = true;
				continue;
			}

			break;
		}

		if (!$hasAny) {
			return null;
		}

		return [
			'type' => $typeSelector,
			'subclasses' => $subclasses,
			'pseudoElements' => $pseudoElements,
			'hasNesting' => $hasNesting,
		];
	}

	/**
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, repr: ?string, hasEscapes: bool}> $tokens
	 */
	private static function parseAttributeSelector(array $tokens, int $start, int $end): ?string
	{
		$pos = $start;
		while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
			$pos++;
		}
		if ($pos >= $end) {
			return null;
		}

		// `<wq-name>` = `<ns-prefix>? <ident-token>`
		$nsPrefix = '';
		$cur = $tokens[$pos];
		if ($cur['type'] === CSSTokenType::Delim && $cur['value'] === '|') {
			if ($pos + 1 >= $end || $tokens[$pos + 1]['type'] !== CSSTokenType::Ident) {
				return null;
			}
			$nsPrefix = '|';
			$attrName = CSS::escape($tokens[$pos + 1]['value']);
			$pos += 2;
		} elseif ($cur['type'] === CSSTokenType::Delim && $cur['value'] === '*') {
			if ($pos + 2 >= $end
				|| $tokens[$pos + 1]['type'] !== CSSTokenType::Delim
				|| $tokens[$pos + 1]['value'] !== '|'
				|| $tokens[$pos + 2]['type'] !== CSSTokenType::Ident
			) {
				return null;
			}
			$nsPrefix = '*|';
			$attrName = CSS::escape($tokens[$pos + 2]['value']);
			$pos += 3;
		} elseif ($cur['type'] === CSSTokenType::Ident) {
			if ($pos + 1 < $end && $tokens[$pos + 1]['type'] === CSSTokenType::Delim && $tokens[$pos + 1]['value'] === '|') {
				if ($pos + 2 >= $end || $tokens[$pos + 2]['type'] !== CSSTokenType::Ident) {
					return null;
				}
				$nsPrefix = CSS::escape($cur['value']) . '|';
				$attrName = CSS::escape($tokens[$pos + 2]['value']);
				$pos += 3;
			} else {
				$attrName = CSS::escape($cur['value']);
				$pos++;
			}
		} else {
			return null;
		}

		$wqName = $nsPrefix . $attrName;
		while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
			$pos++;
		}
		if ($pos >= $end) {
			return '[' . $wqName . ']';
		}

		$matcher = null;
		if ($tokens[$pos]['type'] === CSSTokenType::Delim) {
			$c0 = $tokens[$pos]['value'];
			if ($c0 === '=') {
				$matcher = '=';
				$pos++;
			} elseif ($pos + 1 < $end
				&& $tokens[$pos + 1]['type'] === CSSTokenType::Delim
				&& $tokens[$pos + 1]['value'] === '='
				&& isset(self::ATTR_MATCHERS[$c0 . '='])
			) {
				$matcher = $c0 . '=';
				$pos += 2;
			}
		}
		if ($matcher === null) {
			return null;
		}

		while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
			$pos++;
		}
		if ($pos >= $end) {
			return null;
		}

		$valTok = $tokens[$pos];
		if ($valTok['type'] !== CSSTokenType::String && $valTok['type'] !== CSSTokenType::Ident) {
			return null;
		}
		$serializedValue = self::serializeDoubleQuotedString($valTok['value']);
		$pos++;

		while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
			$pos++;
		}

		$modifier = '';
		if ($pos < $end) {
			if ($tokens[$pos]['type'] !== CSSTokenType::Ident) {
				return null;
			}
			$modLower = strtolower($tokens[$pos]['value']);
			if ($modLower !== 'i' && $modLower !== 's') {
				return null;
			}
			$modifier = ' ' . $modLower;
			$pos++;
			while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos < $end) {
				return null;
			}
		}

		return '[' . $wqName . $matcher . $serializedValue . $modifier . ']';
	}

	/**
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, repr: ?string, hasEscapes: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 * @param list<array{listId: int, start: int, end: int, forgiving: bool, relative: bool, allowPseudoElements: bool, insideHas: bool, compoundOnly: bool}> $childFrames
	 * @return ?array{node: array<string, mixed>, nextPos: int}
	 */
	private static function parsePseudoElement(
		string $source,
		array $tokens,
		array $pairEnd,
		int $pos,
		int $end,
		int &$nextListId,
		int &$nodeCount,
		array &$childFrames,
	): ?array {
		$tok = $tokens[$pos];
		if ($tok['type'] === CSSTokenType::Ident) {
			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-elem', 'text' => '::' . CSS::escape(strtolower($tok['value'])), 'spec' => [0, 0, 1]],
				'nextPos' => $pos + 1,
			];
		}

		if ($tok['type'] === CSSTokenType::Function) {
			$closeIdx = $pairEnd[$pos] ?? null;
			if ($closeIdx === null || $closeIdx >= $end) {
				return null;
			}
			$fnLower = strtolower($tok['value']);
			$innerStart = $pos + 1;
			$innerEnd = $closeIdx;
			while ($innerStart < $innerEnd && $tokens[$innerStart]['type'] === CSSTokenType::Whitespace) {
				$innerStart++;
			}
			while ($innerEnd > $innerStart && $tokens[$innerEnd - 1]['type'] === CSSTokenType::Whitespace) {
				$innerEnd--;
			}
			if ($innerStart >= $innerEnd) {
				return null;
			}

			if ($fnLower === 'slotted') {
				$listId = $nextListId++;
				$childFrames[] = [
					'listId' => $listId,
					'start' => $innerStart,
					'end' => $innerEnd,
					'forgiving' => false,
					'relative' => false,
					'allowPseudoElements' => false,
					'insideHas' => false,
					'compoundOnly' => true,
				];
				self::incrementNodes($nodeCount);
				return [
					'node' => ['kind' => 'pseudo-elem-func', 'name' => 'slotted', 'listId' => $listId],
					'nextPos' => $closeIdx + 1,
				];
			}

			if ($fnLower === 'part') {
				$parts = [];
				for ($i = $innerStart; $i < $innerEnd; $i++) {
					if ($tokens[$i]['type'] === CSSTokenType::Whitespace) {
						continue;
					}
					if ($tokens[$i]['type'] !== CSSTokenType::Ident) {
						return null;
					}
					$parts[] = CSS::escape($tokens[$i]['value']);
				}
				if ($parts === []) {
					return null;
				}
				self::incrementNodes($nodeCount);
				return [
					'node' => ['kind' => 'pseudo-elem', 'text' => '::part(' . implode(' ', $parts) . ')', 'spec' => [0, 0, 1]],
					'nextPos' => $closeIdx + 1,
				];
			}

			$rawInner = substr($source, $tokens[$innerStart]['start'], $tokens[$innerEnd - 1]['end'] - $tokens[$innerStart]['start']);
			$cleanInner = str_contains($rawInner, '/*')
				? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($rawInner))
				: CSSSyntaxScanner::trimCssWhitespace($rawInner);
			if ($cleanInner === '') {
				return null;
			}
			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-elem', 'text' => '::' . CSS::escape($fnLower) . '(' . $cleanInner . ')', 'spec' => [0, 0, 1]],
				'nextPos' => $closeIdx + 1,
			];
		}

		return null;
	}

	/**
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, repr: ?string, hasEscapes: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 * @param list<array{listId: int, start: int, end: int, forgiving: bool, relative: bool, allowPseudoElements: bool, insideHas: bool, compoundOnly: bool}> $childFrames
	 * @return ?array{node: array<string, mixed>, nextPos: int, isPseudoElement: bool}
	 */
	private static function parsePseudoClassOrLegacyElement(
		string $source,
		array $tokens,
		array $pairEnd,
		int $pos,
		int $end,
		bool $allowPseudoElements,
		bool $insideHas,
		bool $afterPseudoElement,
		int &$nextListId,
		int &$nodeCount,
		array &$childFrames,
	): ?array {
		$tok = $tokens[$pos];
		if ($tok['type'] === CSSTokenType::Ident) {
			$lower = strtolower($tok['value']);
			if (isset(self::LEGACY_PSEUDO_ELEMENTS[$lower])) {
				if (!$allowPseudoElements || $afterPseudoElement) {
					return null;
				}
				self::incrementNodes($nodeCount);
				return [
					'node' => ['kind' => 'pseudo-elem', 'text' => '::' . $lower, 'spec' => [0, 0, 1]],
					'nextPos' => $pos + 1,
					'isPseudoElement' => true,
				];
			}

			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-class', 'text' => ':' . CSS::escape($lower), 'spec' => [0, 1, 0]],
				'nextPos' => $pos + 1,
				'isPseudoElement' => false,
			];
		}

		if ($tok['type'] !== CSSTokenType::Function) {
			return null;
		}
		$closeIdx = $pairEnd[$pos] ?? null;
		if ($closeIdx === null || $closeIdx >= $end) {
			return null;
		}

		$fnLower = strtolower($tok['value']);
		$innerStart = $pos + 1;
		$innerEnd = $closeIdx;
		while ($innerStart < $innerEnd && $tokens[$innerStart]['type'] === CSSTokenType::Whitespace) {
			$innerStart++;
		}
		while ($innerEnd > $innerStart && $tokens[$innerEnd - 1]['type'] === CSSTokenType::Whitespace) {
			$innerEnd--;
		}

		if ($fnLower === 'is' || $fnLower === 'where') {
			$listId = $nextListId++;
			$childFrames[] = [
				'listId' => $listId,
				'start' => $innerStart,
				'end' => $innerEnd,
				'forgiving' => true,
				'relative' => false,
				'allowPseudoElements' => false,
				'insideHas' => $insideHas,
				'compoundOnly' => false,
			];
			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-list', 'name' => $fnLower, 'listId' => $listId, 'zeroSpec' => $fnLower === 'where'],
				'nextPos' => $closeIdx + 1,
				'isPseudoElement' => false,
			];
		}

		if ($fnLower === 'not') {
			if ($innerStart >= $innerEnd) {
				return null;
			}
			$listId = $nextListId++;
			$childFrames[] = [
				'listId' => $listId,
				'start' => $innerStart,
				'end' => $innerEnd,
				'forgiving' => false,
				'relative' => false,
				'allowPseudoElements' => false,
				'insideHas' => $insideHas,
				'compoundOnly' => false,
			];
			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-list', 'name' => 'not', 'listId' => $listId, 'zeroSpec' => false],
				'nextPos' => $closeIdx + 1,
				'isPseudoElement' => false,
			];
		}

		if ($fnLower === 'has') {
			if ($insideHas || $innerStart >= $innerEnd) {
				return null;
			}
			$listId = $nextListId++;
			$childFrames[] = [
				'listId' => $listId,
				'start' => $innerStart,
				'end' => $innerEnd,
				'forgiving' => false,
				'relative' => true,
				'allowPseudoElements' => false,
				'insideHas' => true,
				'compoundOnly' => false,
			];
			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-list', 'name' => 'has', 'listId' => $listId, 'zeroSpec' => false],
				'nextPos' => $closeIdx + 1,
				'isPseudoElement' => false,
			];
		}

		if (isset(self::COMPOUND_ONLY_PSEUDOS[$fnLower])) {
			if ($innerStart >= $innerEnd) {
				return null;
			}
			$listId = $nextListId++;
			$childFrames[] = [
				'listId' => $listId,
				'start' => $innerStart,
				'end' => $innerEnd,
				'forgiving' => false,
				'relative' => false,
				'allowPseudoElements' => false,
				'insideHas' => $insideHas,
				'compoundOnly' => true,
			];
			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-host', 'name' => $fnLower, 'listId' => $listId],
				'nextPos' => $closeIdx + 1,
				'isPseudoElement' => false,
			];
		}

		if (isset(self::NTH_CHILD_PSEUDOS[$fnLower]) || isset(self::NTH_SIMPLE_PSEUDOS[$fnLower])) {
			$allowOf = isset(self::NTH_CHILD_PSEUDOS[$fnLower]);
			$nth = self::parseNthArgument($tokens, $innerStart, $innerEnd, $allowOf);
			if ($nth === null) {
				return null;
			}
			self::incrementNodes($nodeCount);
			if ($nth['ofStart'] !== null) {
				$listId = $nextListId++;
				$childFrames[] = [
					'listId' => $listId,
					'start' => $nth['ofStart'],
					'end' => $innerEnd,
					'forgiving' => false,
					'relative' => false,
					'allowPseudoElements' => false,
					'insideHas' => $insideHas,
					'compoundOnly' => false,
				];
				return [
					'node' => ['kind' => 'pseudo-nth', 'name' => $fnLower, 'anb' => $nth['anb'], 'listId' => $listId],
					'nextPos' => $closeIdx + 1,
					'isPseudoElement' => false,
				];
			}

			return [
				'node' => ['kind' => 'pseudo-class', 'text' => ':' . $fnLower . '(' . $nth['anb'] . ')', 'spec' => [0, 1, 0]],
				'nextPos' => $closeIdx + 1,
				'isPseudoElement' => false,
			];
		}

		if ($innerStart >= $innerEnd) {
			return null;
		}

		if ($fnLower === 'lang') {
			$langs = self::parseLangArguments($tokens, $innerStart, $innerEnd);
			if ($langs === null) {
				return null;
			}
			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-class', 'text' => ':lang(' . implode(', ', $langs) . ')', 'spec' => [0, 1, 0]],
				'nextPos' => $closeIdx + 1,
				'isPseudoElement' => false,
			];
		}

		if ($fnLower === 'dir' || $fnLower === 'state') {
			if ($innerEnd - $innerStart !== 1 || $tokens[$innerStart]['type'] !== CSSTokenType::Ident) {
				return null;
			}
			$arg = $fnLower === 'dir'
				? CSS::escape(strtolower($tokens[$innerStart]['value']))
				: CSS::escape($tokens[$innerStart]['value']);
			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-class', 'text' => ':' . $fnLower . '(' . $arg . ')', 'spec' => [0, 1, 0]],
				'nextPos' => $closeIdx + 1,
				'isPseudoElement' => false,
			];
		}

		if ($fnLower === 'active-view-transition-type') {
			$types = self::parseIdentCommaArguments($tokens, $innerStart, $innerEnd);
			if ($types === null) {
				return null;
			}
			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-class', 'text' => ':active-view-transition-type(' . implode(', ', $types) . ')', 'spec' => [0, 1, 0]],
				'nextPos' => $closeIdx + 1,
				'isPseudoElement' => false,
			];
		}

		$rawInner = substr($source, $tokens[$innerStart]['start'], $tokens[$innerEnd - 1]['end'] - $tokens[$innerStart]['start']);
		$cleanInner = str_contains($rawInner, '/*')
			? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($rawInner))
			: CSSSyntaxScanner::trimCssWhitespace($rawInner);
		if ($cleanInner === '') {
			return null;
		}
		self::incrementNodes($nodeCount);
		return [
			'node' => ['kind' => 'pseudo-class', 'text' => ':' . CSS::escape($fnLower) . '(' . $cleanInner . ')', 'spec' => [0, 1, 0]],
			'nextPos' => $closeIdx + 1,
			'isPseudoElement' => false,
		];
	}

	/**
	 * Parses CSS Syntax L3 §6 `<an+b>` optionally followed by `of <selector-list>`.
	 *
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, repr: ?string, hasEscapes: bool}> $tokens
	 * @return ?array{anb: string, ofStart: ?int}
	 */
	private static function parseNthArgument(array $tokens, int $start, int $end, bool $allowOf): ?array
	{
		if ($start >= $end) {
			return null;
		}

		$pos = $start;
		$tok = $tokens[$pos];
		$a = null;
		$b = null;
		$needSignOrEnd = false;
		$needUnsignedAfterMinus = false;

		if ($tok['type'] === CSSTokenType::Ident) {
			$lower = strtolower($tok['value']);
			if ($lower === 'odd') {
				$a = 2;
				$b = 1;
				$pos++;
			} elseif ($lower === 'even') {
				$a = 2;
				$b = 0;
				$pos++;
			} elseif ($lower === 'n' || $lower === '-n') {
				$a = $lower === '-n' ? -1 : 1;
				$pos++;
				$needSignOrEnd = true;
			} elseif (preg_match('/^(-?)n-(\d+)$/iD', $lower, $m) === 1) {
				$a = $m[1] === '-' ? -1 : 1;
				$b = -(int) $m[2];
				$pos++;
			} elseif ($lower === 'n-' || $lower === '-n-') {
				$a = $lower === '-n-' ? -1 : 1;
				$pos++;
				$needUnsignedAfterMinus = true;
			} else {
				return null;
			}
		} elseif ($tok['type'] === CSSTokenType::Delim && $tok['value'] === '+') {
			$pos++;
			if ($pos >= $end || $tokens[$pos]['type'] !== CSSTokenType::Ident) {
				return null;
			}
			$lower = strtolower($tokens[$pos]['value']);
			if ($lower === 'n') {
				$a = 1;
				$pos++;
				$needSignOrEnd = true;
			} elseif (preg_match('/^n-(\d+)$/iD', $lower, $m) === 1) {
				$a = 1;
				$b = -(int) $m[1];
				$pos++;
			} elseif ($lower === 'n-') {
				$a = 1;
				$pos++;
				$needUnsignedAfterMinus = true;
			} else {
				return null;
			}
		} elseif ($tok['type'] === CSSTokenType::Number && $tok['flag'] === CSSToken::NUMERIC_INTEGER) {
			if (!is_finite((float) $tok['num'])) {
				return null;
			}
			$a = 0;
			$b = (int) $tok['num'];
			$pos++;
		} elseif ($tok['type'] === CSSTokenType::Dimension && $tok['flag'] === CSSToken::NUMERIC_INTEGER) {
			if (!is_finite((float) $tok['num'])) {
				return null;
			}
			$a = (int) $tok['num'];
			$unitLower = strtolower($tok['unit'] ?? '');
			if ($unitLower === 'n') {
				$pos++;
				$needSignOrEnd = true;
			} elseif (preg_match('/^n-(\d+)$/iD', $unitLower, $m) === 1) {
				$b = -(int) $m[1];
				$pos++;
			} elseif ($unitLower === 'n-') {
				$pos++;
				$needUnsignedAfterMinus = true;
			} else {
				return null;
			}
		} else {
			return null;
		}

		if ($needUnsignedAfterMinus) {
			while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos >= $end || $tokens[$pos]['type'] !== CSSTokenType::Number || $tokens[$pos]['flag'] !== CSSToken::NUMERIC_INTEGER) {
				return null;
			}
			$repr = $tokens[$pos]['repr'] ?? '';
			if ($repr === '' || $repr[0] === '+' || $repr[0] === '-' || !is_finite((float) $tokens[$pos]['num'])) {
				return null;
			}
			$b = -(int) $tokens[$pos]['num'];
			$pos++;
		} elseif ($needSignOrEnd) {
			$wsBefore = $pos;
			while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos < $end) {
				$next = $tokens[$pos];
				if ($next['type'] === CSSTokenType::Number && $next['flag'] === CSSToken::NUMERIC_INTEGER) {
					$repr = $next['repr'] ?? '';
					if ($repr === '' || ($repr[0] !== '+' && $repr[0] !== '-') || !is_finite((float) $next['num'])) {
						return null;
					}
					$b = (int) $next['num'];
					$pos++;
				} elseif ($next['type'] === CSSTokenType::Delim && ($next['value'] === '+' || $next['value'] === '-')) {
					$sign = $next['value'] === '-' ? -1 : 1;
					$pos++;
					while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
						$pos++;
					}
					if ($pos >= $end || $tokens[$pos]['type'] !== CSSTokenType::Number || $tokens[$pos]['flag'] !== CSSToken::NUMERIC_INTEGER) {
						return null;
					}
					$repr = $tokens[$pos]['repr'] ?? '';
					if ($repr === '' || $repr[0] === '+' || $repr[0] === '-' || !is_finite((float) $tokens[$pos]['num'])) {
						return null;
					}
					$b = $sign * (int) $tokens[$pos]['num'];
					$pos++;
				} else {
					$b = 0;
					$pos = $wsBefore;
				}
			} else {
				$b = 0;
			}
		}

		while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
			$pos++;
		}

		$ofStart = null;
		if ($pos < $end) {
			if (!$allowOf || $tokens[$pos]['type'] !== CSSTokenType::Ident || strtolower($tokens[$pos]['value']) !== 'of') {
				return null;
			}
			$pos++;
			while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos >= $end) {
				return null;
			}
			$ofStart = $pos;
		}

		$b ??= 0;
		if ($a === 0) {
			$anb = (string) $b;
		} else {
			$aStr = $a === 1 ? 'n' : ($a === -1 ? '-n' : $a . 'n');
			if ($b === 0) {
				$anb = $aStr;
			} elseif ($b > 0) {
				$anb = $aStr . '+' . $b;
			} else {
				$anb = $aStr . $b;
			}
		}

		return ['anb' => $anb, 'ofStart' => $ofStart];
	}

	/**
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, repr: ?string, hasEscapes: bool}> $tokens
	 * @return ?list<string>
	 */
	private static function parseLangArguments(array $tokens, int $start, int $end): ?array
	{
		$args = [];
		$pos = $start;
		while ($pos < $end) {
			while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos >= $end) {
				return null;
			}
			$tok = $tokens[$pos];
			if ($tok['type'] === CSSTokenType::String) {
				$args[] = self::serializeDoubleQuotedString($tok['value']);
			} elseif ($tok['type'] === CSSTokenType::Ident) {
				$args[] = CSS::escape($tok['value']);
			} else {
				return null;
			}
			$pos++;
			while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos >= $end) {
				break;
			}
			if ($tokens[$pos]['type'] !== CSSTokenType::Comma) {
				return null;
			}
			$pos++;
		}

		return $args !== [] ? $args : null;
	}

	/**
	 * @param list<array{type: CSSTokenType, start: int, end: int, value: string, flag: ?string, num: ?float, repr: ?string, hasEscapes: bool}> $tokens
	 * @return ?list<string>
	 */
	private static function parseIdentCommaArguments(array $tokens, int $start, int $end): ?array
	{
		$args = [];
		$pos = $start;
		while ($pos < $end) {
			while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos >= $end || $tokens[$pos]['type'] !== CSSTokenType::Ident) {
				return null;
			}
			$args[] = CSS::escape($tokens[$pos]['value']);
			$pos++;
			while ($pos < $end && $tokens[$pos]['type'] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos >= $end) {
				break;
			}
			if ($tokens[$pos]['type'] !== CSSTokenType::Comma) {
				return null;
			}
			$pos++;
		}

		return $args !== [] ? $args : null;
	}

	/**
	 * @param array<string, mixed> $complex
	 * @param array<int, list<array<string, mixed>>> $listSlots
	 * @return array{leadingCombinator: string, compounds: list<array<string, mixed>>, combinators: list<string>, text: string, hasNesting: bool}
	 */
	private static function finalizeComplexSelector(array $complex, array $listSlots): array
	{
		$compounds = [];
		$out = $complex['leadingCombinator'] !== '' ? $complex['leadingCombinator'] . ' ' : '';
		$hasNesting = false;
		$combinators = $complex['combinators'];

		foreach ($complex['compounds'] as $idx => $compound) {
			if ($idx > 0) {
				$comb = $combinators[$idx - 1];
				$out .= $comb === ' ' ? ' ' : ' ' . $comb . ' ';
			}

			$finalSubclasses = [];
			$subText = '';
			foreach ($compound['subclasses'] as $sub) {
				$resolved = self::resolveSimpleNode($sub, $listSlots);
				$finalSubclasses[] = $resolved;
				$subText .= $resolved['text'];
			}

			$finalPseudoElems = [];
			$peText = '';
			foreach ($compound['pseudoElements'] as $pe) {
				$resolved = self::resolveSimpleNode($pe, $listSlots);
				$finalPseudoElems[] = $resolved;
				$peText .= $resolved['text'];
			}

			$typeText = '';
			$typeNode = $compound['type'];
			if ($typeNode !== null) {
				$omitUniversal = $typeNode['isUniversal']
					&& !$typeNode['hasExplicitNs']
					&& ($finalSubclasses !== [] || $finalPseudoElems !== []);
				if (!$omitUniversal) {
					$typeText = $typeNode['ns'] . $typeNode['name'];
				}
			}

			$out .= $typeText . $subText . $peText;
			if ($compound['hasNesting']) {
				$hasNesting = true;
			}
			$compounds[] = [
				'type' => $typeNode,
				'subclasses' => $finalSubclasses,
				'pseudoElements' => $finalPseudoElems,
				'hasNesting' => $compound['hasNesting'],
			];
		}

		return [
			'leadingCombinator' => $complex['leadingCombinator'],
			'compounds' => $compounds,
			'combinators' => $combinators,
			'text' => $out,
			'hasNesting' => $hasNesting,
		];
	}

	/**
	 * @param array<string, mixed> $node
	 * @param array<int, list<array<string, mixed>>> $listSlots
	 * @return array<string, mixed>
	 */
	private static function resolveSimpleNode(array $node, array $listSlots): array
	{
		$kind = $node['kind'];
		if ($kind === 'pseudo-list') {
			$children = $listSlots[$node['listId']] ?? [];
			$parts = [];
			foreach ($children as $child) {
				$parts[] = $child['text'];
			}
			$node['text'] = ':' . $node['name'] . '(' . implode(', ', $parts) . ')';
			$node['children'] = $children;
			return $node;
		}
		if ($kind === 'pseudo-host' || $kind === 'pseudo-elem-func') {
			$children = $listSlots[$node['listId']] ?? [];
			$parts = [];
			foreach ($children as $child) {
				$parts[] = $child['text'];
			}
			$prefix = $kind === 'pseudo-elem-func' ? '::' : ':';
			$node['text'] = $prefix . $node['name'] . '(' . implode(', ', $parts) . ')';
			$node['children'] = $children;
			return $node;
		}
		if ($kind === 'pseudo-nth') {
			$children = $listSlots[$node['listId']] ?? [];
			$parts = [];
			foreach ($children as $child) {
				$parts[] = $child['text'];
			}
			$node['text'] = ':' . $node['name'] . '(' . $node['anb'] . ' of ' . implode(', ', $parts) . ')';
			$node['children'] = $children;
			return $node;
		}

		return $node;
	}

	/**
	 * Iterative post-order `(A, B, C)` specificity calculation per Selectors Level 4 §16.
	 *
	 * @param array<string, mixed> $rootComplex
	 * @param array{0: int, 1: int, 2: int} $parentSpecificity
	 * @return array{0: int, 1: int, 2: int}
	 */
	private static function evaluateComplexSpecificity(array $rootComplex, array $parentSpecificity): array
	{
		$slots = [];
		$nextSlot = 1;
		// Frame: [phase (0=expand complex, 1=sum complex, 2=max list), payload, destSlot]
		$stack = [[0, $rootComplex, 0]];

		while ($stack !== []) {
			$frame = array_pop($stack);
			$phase = $frame[0];

			if ($phase === 1) {
				/** @var array{base: array{0: int, 1: int, 2: int}, childSlots: list<int>} $info */
				$info = $frame[1];
				$destSlot = $frame[2];
				$acc = $info['base'];
				foreach ($info['childSlots'] as $cSlot) {
					$cSpec = $slots[$cSlot];
					$acc[0] += $cSpec[0];
					$acc[1] += $cSpec[1];
					$acc[2] += $cSpec[2];
				}
				$slots[$destSlot] = $acc;
				continue;
			}

			if ($phase === 2) {
				/** @var array{base: array{0: int, 1: int, 2: int}, childSlots: list<int>} $info */
				$info = $frame[1];
				$destSlot = $frame[2];
				$max = [0, 0, 0];
				foreach ($info['childSlots'] as $cSlot) {
					$cSpec = $slots[$cSlot];
					if (self::compareSpecificity($cSpec, $max) > 0) {
						$max = $cSpec;
					}
				}
				$slots[$destSlot] = [
					$info['base'][0] + $max[0],
					$info['base'][1] + $max[1],
					$info['base'][2] + $max[2],
				];
				continue;
			}

			/** @var array<string, mixed> $complex */
			$complex = $frame[1];
			$destSlot = $frame[2];
			$base = [0, 0, 0];
			$childWork = [];
			$childSlots = [];

			foreach ($complex['compounds'] as $compound) {
				if ($compound['type'] !== null && !$compound['type']['isUniversal']) {
					$base[2]++;
				}
				foreach ($compound['subclasses'] as $sub) {
					self::collectNodeSpecificityWork($sub, $parentSpecificity, $base, $childSlots, $childWork, $nextSlot);
				}
				foreach ($compound['pseudoElements'] as $pe) {
					self::collectNodeSpecificityWork($pe, $parentSpecificity, $base, $childSlots, $childWork, $nextSlot);
				}
			}

			if ($childWork === []) {
				$slots[$destSlot] = $base;
				continue;
			}

			$stack[] = [1, ['base' => $base, 'childSlots' => $childSlots], $destSlot];
			for ($i = count($childWork) - 1; $i >= 0; $i--) {
				[$workKind, $workPayload, $workSlot] = $childWork[$i];
				if ($workKind === 'list') {
					/** @var array{base: array{0: int, 1: int, 2: int}, children: list<array<string, mixed>>} $workPayload */
					$listChildSlots = [];
					$listSubWork = [];
					foreach ($workPayload['children'] as $childComplex) {
						$s = $nextSlot++;
						$listChildSlots[] = $s;
						$listSubWork[] = [$childComplex, $s];
					}
					$stack[] = [2, ['base' => $workPayload['base'], 'childSlots' => $listChildSlots], $workSlot];
					for ($j = count($listSubWork) - 1; $j >= 0; $j--) {
						$stack[] = [0, $listSubWork[$j][0], $listSubWork[$j][1]];
					}
				}
			}
		}

		return $slots[0] ?? [0, 0, 0];
	}

	/**
	 * @param array<string, mixed> $node
	 * @param array{0: int, 1: int, 2: int} $parentSpecificity
	 * @param array{0: int, 1: int, 2: int} $base
	 * @param list<int> $childSlots
	 * @param list<array{0: string, 1: mixed, 2: int}> $childWork
	 */
	private static function collectNodeSpecificityWork(
		array $node,
		array $parentSpecificity,
		array &$base,
		array &$childSlots,
		array &$childWork,
		int &$nextSlot,
	): void {
		$kind = $node['kind'];
		if ($kind === 'nesting') {
			$base[0] += $parentSpecificity[0];
			$base[1] += $parentSpecificity[1];
			$base[2] += $parentSpecificity[2];
			return;
		}
		if ($kind === 'pseudo-list') {
			if ($node['zeroSpec']) {
				return;
			}
			$children = $node['children'] ?? [];
			if ($children === []) {
				return;
			}
			$slot = $nextSlot++;
			$childSlots[] = $slot;
			$childWork[] = ['list', ['base' => [0, 0, 0], 'children' => $children], $slot];
			return;
		}
		if ($kind === 'pseudo-nth') {
			$base[1]++;
			$children = $node['children'] ?? [];
			if ($children !== []) {
				$slot = $nextSlot++;
				$childSlots[] = $slot;
				$childWork[] = ['list', ['base' => [0, 0, 0], 'children' => $children], $slot];
			}
			return;
		}
		if ($kind === 'pseudo-host') {
			$base[1]++;
			$children = $node['children'] ?? [];
			if ($children !== []) {
				$slot = $nextSlot++;
				$childSlots[] = $slot;
				$childWork[] = ['list', ['base' => [0, 0, 0], 'children' => $children], $slot];
			}
			return;
		}
		if ($kind === 'pseudo-elem-func') {
			$base[2]++;
			$children = $node['children'] ?? [];
			if ($children !== []) {
				$slot = $nextSlot++;
				$childSlots[] = $slot;
				$childWork[] = ['list', ['base' => [0, 0, 0], 'children' => $children], $slot];
			}
			return;
		}

		/** @var array{0: int, 1: int, 2: int} $spec */
		$spec = $node['spec'] ?? [0, 0, 0];
		$base[0] += (int) $spec[0];
		$base[1] += (int) $spec[1];
		$base[2] += (int) $spec[2];
	}

	private static function serializeDoubleQuotedString(string $value): string
	{
		$len = strlen($value);
		$out = '"';
		for ($i = 0; $i < $len; $i++) {
			$ch = $value[$i];
			$ord = ord($ch);
			if ($ord === 0x00) {
				$out .= "\u{FFFD}";
			} elseif ($ord <= 0x1F || $ord === 0x7F) {
				$out .= '\\' . dechex($ord) . ' ';
			} elseif ($ch === '"' || $ch === '\\') {
				$out .= '\\' . $ch;
			} else {
				$out .= $ch;
			}
		}

		return $out . '"';
	}

	private static function incrementNodes(int &$nodeCount): void
	{
		if (++$nodeCount > self::MAX_SELECTOR_NODES) {
			throw new CSSParserLimitException(sprintf('CSS selector exceeds maximum AST node limit of %d.', self::MAX_SELECTOR_NODES));
		}
	}
}
