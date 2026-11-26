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
	 * @internal Canonical-only fast path for `CSSStyleRule` / `CSSParser` skipping AST specificity tree construction.
	 * @return array{0: string, 1: bool}
	 */
	public static function parseForStyleRuleInternal(
		string $selectorText,
		?CSSParserOptions $options,
		bool $allowRelative,
		bool $allowCommentControlBytes,
	): array {
		$prepared = self::prepareSelectorTokens($selectorText, $options, false, !$allowCommentControlBytes);
		assert($prepared !== null);
		[$tokens, $pairEnd, $start, $end, $maxDepth] = $prepared;

		$nodeCount = 0;
		$lists = self::parseSelectorListIterative(
			$selectorText,
			$tokens,
			$pairEnd,
			$start,
			$end,
			false,
			$allowRelative,
			$maxDepth,
			$nodeCount,
			true,
		);

		$texts = [];
		$hasLeading = false;
		foreach ($lists[0] as $complex) {
			$texts[] = $complex['text'];
			if ($complex['leadingCombinator'] !== '') {
				$hasLeading = true;
			}
		}

		return [implode(', ', $texts), $hasLeading];
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

	/**
	 * @return ?array{
	 *   0: list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}>,
	 *   1: array<int, int>,
	 *   2: int,
	 *   3: int,
	 *   4: ?int
	 * }
	 */
	private static function prepareSelectorTokens(
		string $selectorText,
		?CSSParserOptions $options,
		bool $forgiving,
		bool $checkCommentControlBytes,
	): ?array {
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($selectorText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS selector exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		[$tokens, $pairEnd, , $hasControl, $hasUnclosed] = CSSSyntaxScanner::tokenizeBracketedInternal(
			$selectorText,
			$options,
			self::MAX_SELECTOR_NODES,
			'CSS selector',
			$checkCommentControlBytes,
			false,
			false,
			CSSStyleValue::MAX_LIST_ITEMS,
		);
		if ($hasControl) {
			throw new InvalidArgumentException('CSS selector contains disallowed control characters.');
		}
		if ($hasUnclosed) {
			throw new InvalidArgumentException('CSS selector contains unclosed string, url, comment, or delimiter.');
		}

		$start = 0;
		$end = count($tokens);
		CSSSyntaxScanner::trimTokenRange($tokens, $start, $end);
		if ($start >= $end) {
			if ($forgiving) {
				return null;
			}
			throw new InvalidArgumentException('CSS selector list cannot be empty.');
		}

		return [$tokens, $pairEnd, $start, $end, $options->maxDepth];
	}

	private static function parseInternal(
		string $selectorText,
		?CSSParserOptions $options,
		bool $forgiving,
		bool $relative,
		bool $checkCommentControlBytes = true,
	): self {
		$prepared = self::prepareSelectorTokens($selectorText, $options, $forgiving, $checkCommentControlBytes);
		if ($prepared === null) {
			return new self([], '', false);
		}
		[$tokens, $pairEnd, $start, $end, $maxDepth] = $prepared;

		$nodeCount = 0;
		$lists = self::parseSelectorListIterative(
			$selectorText,
			$tokens,
			$pairEnd,
			$start,
			$end,
			$forgiving,
			$relative,
			$maxDepth,
			$nodeCount,
			false,
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
	 * Iterative parser over nested selector-list frames (`:is`, `:where`, `:not`, `:has`, `:nth-child(... of ...)`, `:host`, `::slotted`).
	 *
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
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
		bool $canonicalOnly = false,
	): array {
		$listSlots = [];
		$nextListId = 1;
		// Packed frame layout: [0=phase, 1=listId, 2=start, 3=end, 4=forgiving, 5=relative, 6=allowPseudoElements,
		// 7=insideHas, 8=compoundOnly, 9=depth, 10=forgivingItem, 11=complexes, 12=pendingComplex, 13=remainingRanges]
		/** @var list<array<int, mixed>> $stack */
		$stack = [[0, 0, $rootStart, $rootEnd, $rootForgiving, $rootRelative, true, false, false, 0, false, [], null, []]];

		while ($stack !== []) {
			$topIdx = count($stack) - 1;
			$phase = $stack[$topIdx][0];

			if ($phase === 0) {
				if ($maxDepth !== null && $stack[$topIdx][9] > $maxDepth) {
					if ($stack[$topIdx][10]) {
						array_pop($stack);
						continue;
					}
					throw new CSSParserLimitException(sprintf('CSS selector exceeds maximum nesting depth of %d.', $maxDepth));
				}

				$ranges = self::splitCommaRanges($tokens, $pairEnd, $stack[$topIdx][2], $stack[$topIdx][3]);
				if (count($ranges) > CSSStyleValue::MAX_LIST_ITEMS) {
					throw new CSSParserLimitException(sprintf('CSS selector list exceeds maximum limit of %d items.', CSSStyleValue::MAX_LIST_ITEMS));
				}
				if ($stack[$topIdx][8] && count($ranges) > 1) {
					if ($stack[$topIdx][10] && self::abortForgivingItem($stack, $topIdx)) {
						continue;
					}
					throw new InvalidArgumentException('Expected a single compound selector.');
				}

				$stack[$topIdx][13] = array_reverse($ranges);
				$stack[$topIdx][0] = 1;
				continue;
			}

			if ($phase === 1) {
				if ($stack[$topIdx][13] === []) {
					if (!$stack[$topIdx][4] && $stack[$topIdx][11] === []) {
						if ($stack[$topIdx][10] && self::abortForgivingItem($stack, $topIdx)) {
							continue;
						}
						throw new InvalidArgumentException('CSS selector list cannot be empty.');
					}
					$listSlots[$stack[$topIdx][1]] = $stack[$topIdx][11];
					array_pop($stack);
					continue;
				}

				[$cStart, $cEnd] = array_pop($stack[$topIdx][13]);
				CSSSyntaxScanner::trimTokenRange($tokens, $cStart, $cEnd);

				if ($cStart >= $cEnd) {
					if ($stack[$topIdx][4]) {
						continue;
					}
					if ($stack[$topIdx][10] && self::abortForgivingItem($stack, $topIdx)) {
						continue;
					}
					throw new InvalidArgumentException('Empty selector inside CSS selector list.');
				}

				$parsed = self::parseComplexSelectorStructure(
					$source,
					$tokens,
					$pairEnd,
					$cStart,
					$cEnd,
					$stack[$topIdx][5],
					$stack[$topIdx][6],
					$stack[$topIdx][7],
					$stack[$topIdx][8],
					$nextListId,
					$nodeCount,
					$canonicalOnly,
				);

				if ($parsed === null) {
					if ($stack[$topIdx][4]) {
						continue;
					}
					if ($stack[$topIdx][10] && self::abortForgivingItem($stack, $topIdx)) {
						continue;
					}
					$badSlice = substr($source, $tokens[$cStart][1], $tokens[$cEnd - 1][2] - $tokens[$cStart][1]);
					throw new InvalidArgumentException('Invalid CSS selector: ' . $badSlice);
				}

				if ($canonicalOnly && $parsed['childFrames'] === []) {
					$stack[$topIdx][11][] = $parsed['complex'];
					continue;
				}

				$stack[$topIdx][12] = $parsed['complex'];
				$stack[$topIdx][0] = 2;

				if ($parsed['childFrames'] !== []) {
					$childForgivingItem = $stack[$topIdx][4] || $stack[$topIdx][10];
					$childDepth = (int) $stack[$topIdx][9] + 1;
					if ($maxDepth !== null && $childDepth > $maxDepth) {
						throw new CSSParserLimitException(sprintf('CSS selector exceeds maximum nesting depth of %d.', $maxDepth));
					}
					for ($i = count($parsed['childFrames']) - 1; $i >= 0; $i--) {
						$cf = $parsed['childFrames'][$i];
						$stack[] = [
							0,
							$cf['listId'],
							$cf['start'],
							$cf['end'],
							$cf['forgiving'],
							$cf['relative'],
							$cf['allowPseudoElements'],
							$cf['insideHas'],
							$cf['compoundOnly'],
							$childDepth,
							$childForgivingItem && !$cf['forgiving'],
							[],
							null,
							[],
						];
					}
				}
				continue;
			}

			// phase === 2: finalize pending complex selector with resolved child lists
			$pending = $stack[$topIdx][12];
			$stack[$topIdx][12] = null;
			$stack[$topIdx][0] = 1;
			if ($pending !== null) {
				$stack[$topIdx][11][] = $canonicalOnly
					? self::finalizeCanonicalComplex($pending, $listSlots)
					: self::finalizeComplexSelector($pending, $listSlots);
			}
		}

		return $listSlots;
	}

	/**
	 * @param list<array<int, mixed>> $stack
	 */
	private static function abortForgivingItem(array &$stack, int $currentIdx): bool
	{
		for ($i = $currentIdx; $i >= 0; $i--) {
			if ($stack[$i][4] && $stack[$i][0] === 2) {
				array_splice($stack, $i + 1);
				$stack[$i][12] = null;
				$stack[$i][0] = 2;
				return true;
			}
		}

		return false;
	}

	/**
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 * @param array<int, int> $pairEnd
	 * @return list<array{0: int, 1: int}>
	 */
	private static function splitCommaRanges(array $tokens, array $pairEnd, int $start, int $end): array
	{
		$ranges = [];
		$segStart = $start;
		$pos = $start;
		while ($pos < $end) {
			$tokType = $tokens[$pos][0];
			if ($tokType === CSSTokenType::LeftParenthesis || $tokType === CSSTokenType::Function || $tokType === CSSTokenType::LeftSquareBracket) {
				$close = $pairEnd[$pos] ?? null;
				$pos = ($close !== null && $close < $end) ? $close + 1 : $end;
				continue;
			}
			if ($tokType === CSSTokenType::Comma) {
				$ranges[] = [$segStart, $pos];
				$segStart = $pos + 1;
			}
			$pos++;
		}
		$ranges[] = [$segStart, $end];

		return $ranges;
	}

	/**
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
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
		bool $canonicalOnly = false,
	): ?array {
		$pos = $start;
		$leadingCombinator = '';

		if ($tokens[$pos][0] === CSSTokenType::Delim) {
			$ch = $tokens[$pos][3];
			if ($ch === '>' || $ch === '+' || $ch === '~') {
				if (!$relative || $compoundOnly) {
					return null;
				}
				$leadingCombinator = $ch;
				$pos++;
				while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
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
				$canonicalOnly,
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
			while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
				$hadWs = true;
				$pos++;
			}
			if ($pos >= $end) {
				break;
			}

			$comb = null;
			if ($tokens[$pos][0] === CSSTokenType::Delim) {
				$ch = $tokens[$pos][3];
				if ($ch === '>' || $ch === '+' || $ch === '~') {
					$comb = $ch;
					$pos++;
				} elseif ($ch === '|' && $pos + 1 < $end && $tokens[$pos + 1][0] === CSSTokenType::Delim && $tokens[$pos + 1][3] === '|') {
					$comb = '||';
					$pos += 2;
				}
			}

			if ($comb !== null) {
				while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
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

		if ($canonicalOnly) {
			if ($childFrames === []) {
				$out = $leadingCombinator !== '' ? $leadingCombinator . ' ' : '';
				foreach ($compounds as $idx => $compound) {
					if ($idx > 0) {
						$comb = $combinators[$idx - 1];
						$out .= $comb === ' ' ? ' ' : ' ' . $comb . ' ';
					}
					$out .= $compound['text'];
				}
				return [
					'complex' => ['leadingCombinator' => $leadingCombinator, 'text' => $out],
					'childFrames' => [],
				];
			}

			$parts = $leadingCombinator !== '' ? [$leadingCombinator . ' '] : [];
			foreach ($compounds as $idx => $compound) {
				if ($idx > 0) {
					$comb = $combinators[$idx - 1];
					$parts[] = $comb === ' ' ? ' ' : ' ' . $comb . ' ';
				}
				foreach ($compound['parts'] as $part) {
					$parts[] = $part;
				}
			}
			return [
				'complex' => ['leadingCombinator' => $leadingCombinator, 'parts' => $parts],
				'childFrames' => $childFrames,
			];
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
	 * Parses `<wq-name>` or `<type-selector>` (with optional `<ns-prefix>`).
	 *
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 * @return ?array{ns: string, name: string, isUniversal: bool, hasExplicitNs: bool}
	 */
	private static function parseQualifiedName(array $tokens, int &$pos, int $end, bool $allowUniversal): ?array
	{
		if ($pos >= $end) {
			return null;
		}
		$tok = $tokens[$pos];
		$type = $tok[0];

		if ($type === CSSTokenType::Delim && $tok[3] === '|') {
			if ($pos + 1 >= $end || ($allowUniversal && $tokens[$pos + 1][0] === CSSTokenType::Delim && $tokens[$pos + 1][3] === '|')) {
				return null;
			}
			$pos++;
			$target = $tokens[$pos];
			if ($target[0] === CSSTokenType::Ident) {
				$pos++;
				return ['ns' => '|', 'name' => $target[7] ? CSS::escape($target[3]) : $target[3], 'isUniversal' => false, 'hasExplicitNs' => true];
			}
			if ($allowUniversal && $target[0] === CSSTokenType::Delim && $target[3] === '*') {
				$pos++;
				return ['ns' => '|', 'name' => '*', 'isUniversal' => true, 'hasExplicitNs' => true];
			}
			return null;
		}

		if ($type === CSSTokenType::Delim && $tok[3] === '*') {
			if ($pos + 1 < $end
				&& $tokens[$pos + 1][0] === CSSTokenType::Delim
				&& $tokens[$pos + 1][3] === '|'
				&& !($pos + 2 < $end && $tokens[$pos + 2][0] === CSSTokenType::Delim && $tokens[$pos + 2][3] === '|')
			) {
				if ($pos + 2 >= $end) {
					return null;
				}
				$pos += 2;
				$target = $tokens[$pos];
				if ($target[0] === CSSTokenType::Ident) {
					$pos++;
					return ['ns' => '*|', 'name' => $target[7] ? CSS::escape($target[3]) : $target[3], 'isUniversal' => false, 'hasExplicitNs' => false];
				}
				if ($allowUniversal && $target[0] === CSSTokenType::Delim && $target[3] === '*') {
					$pos++;
					return ['ns' => '*|', 'name' => '*', 'isUniversal' => true, 'hasExplicitNs' => false];
				}
				return null;
			}
			if (!$allowUniversal) {
				return null;
			}
			$pos++;
			return ['ns' => '', 'name' => '*', 'isUniversal' => true, 'hasExplicitNs' => false];
		}

		if ($type === CSSTokenType::Ident) {
			if ($pos + 1 < $end
				&& $tokens[$pos + 1][0] === CSSTokenType::Delim
				&& $tokens[$pos + 1][3] === '|'
				&& !($pos + 2 < $end && $tokens[$pos + 2][0] === CSSTokenType::Delim && $tokens[$pos + 2][3] === '|')
			) {
				if ($pos + 2 >= $end) {
					return null;
				}
				$nsPrefix = ($tok[7] ? CSS::escape($tok[3]) : $tok[3]) . '|';
				$pos += 2;
				$target = $tokens[$pos];
				if ($target[0] === CSSTokenType::Ident) {
					$pos++;
					return ['ns' => $nsPrefix, 'name' => $target[7] ? CSS::escape($target[3]) : $target[3], 'isUniversal' => false, 'hasExplicitNs' => true];
				}
				if ($allowUniversal && $target[0] === CSSTokenType::Delim && $target[3] === '*') {
					$pos++;
					return ['ns' => $nsPrefix, 'name' => '*', 'isUniversal' => true, 'hasExplicitNs' => true];
				}
				return null;
			}
			$pos++;
			return ['ns' => '', 'name' => $tok[7] ? CSS::escape($tok[3]) : $tok[3], 'isUniversal' => false, 'hasExplicitNs' => false];
		}

		return null;
	}

	/**
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
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
		bool $canonicalOnly = false,
	): ?array {
		$typeSelector = self::parseQualifiedName($tokens, $pos, $end, true);
		$hasAny = false;
		if ($typeSelector !== null) {
			self::incrementNodes($nodeCount);
			$hasAny = true;
		}

		$subclasses = [];
		$pseudoElements = [];
		$canonicalParts = [];
		$textBuf = '';
		$hasNonType = false;
		$afterPseudoElement = false;
		$hasNesting = false;

		while ($pos < $end) {
			$cur = $tokens[$pos];
			$type = $cur[0];

			if ($type === CSSTokenType::Hash) {
				if ($afterPseudoElement || $cur[4] !== CSSToken::HASH_ID) {
					return null;
				}
				self::incrementNodes($nodeCount);
				$text = '#' . ($cur[7] ? CSS::escape($cur[3]) : $cur[3]);
				if ($canonicalOnly) {
					$textBuf .= $text;
				} else {
					$subclasses[] = ['kind' => 'id', 'text' => $text, 'spec' => [1, 0, 0]];
				}
				$hasNonType = true;
				$pos++;
				$hasAny = true;
				continue;
			}

			if ($type === CSSTokenType::Delim && $cur[3] === '.') {
				if ($afterPseudoElement || $pos + 1 >= $end || $tokens[$pos + 1][0] !== CSSTokenType::Ident) {
					return null;
				}
				self::incrementNodes($nodeCount);
				$clsTok = $tokens[$pos + 1];
				$text = '.' . ($clsTok[7] ? CSS::escape($clsTok[3]) : $clsTok[3]);
				if ($canonicalOnly) {
					$textBuf .= $text;
				} else {
					$subclasses[] = ['kind' => 'class', 'text' => $text, 'spec' => [0, 1, 0]];
				}
				$hasNonType = true;
				$pos += 2;
				$hasAny = true;
				continue;
			}

			if ($type === CSSTokenType::Delim && $cur[3] === '&') {
				if ($afterPseudoElement) {
					return null;
				}
				self::incrementNodes($nodeCount);
				if ($canonicalOnly) {
					$textBuf .= '&';
				} else {
					$subclasses[] = ['kind' => 'nesting', 'text' => '&'];
				}
				$hasNonType = true;
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
				if ($canonicalOnly) {
					$textBuf .= $attrText;
				} else {
					$subclasses[] = ['kind' => 'attr', 'text' => $attrText, 'spec' => [0, 1, 0]];
				}
				$hasNonType = true;
				$pos = $closeIdx + 1;
				$hasAny = true;
				continue;
			}

			if ($type === CSSTokenType::Colon) {
				if ($pos + 1 >= $end) {
					return null;
				}
				$next = $tokens[$pos + 1];
				if ($next[0] === CSSTokenType::Colon) {
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
					$node = $pe['node'];
					if ($canonicalOnly) {
						if ($node['kind'] === 'pseudo-elem-func') {
							if ($textBuf !== '') {
								$canonicalParts[] = $textBuf;
								$textBuf = '';
							}
							$canonicalParts[] = ['::' . $node['name'] . '(', $node['listId'], ')'];
						} else {
							$textBuf .= $node['text'];
						}
					} else {
						$pseudoElements[] = $node;
					}
					$hasNonType = true;
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
				$node = $pc['node'];
				if ($canonicalOnly) {
					$kind = $node['kind'];
					if ($kind === 'pseudo-list' || $kind === 'pseudo-host') {
						if ($textBuf !== '') {
							$canonicalParts[] = $textBuf;
							$textBuf = '';
						}
						$canonicalParts[] = [':' . $node['name'] . '(', $node['listId'], ')'];
					} elseif ($kind === 'pseudo-nth') {
						if ($textBuf !== '') {
							$canonicalParts[] = $textBuf;
							$textBuf = '';
						}
						$canonicalParts[] = [':' . $node['name'] . '(' . $node['anb'] . ' of ', $node['listId'], ')'];
					} else {
						$textBuf .= $node['text'];
					}
				} else {
					if ($pc['isPseudoElement'] || $afterPseudoElement) {
						$pseudoElements[] = $node;
					} else {
						$subclasses[] = $node;
					}
				}
				if ($pc['isPseudoElement']) {
					$afterPseudoElement = true;
				}
				$hasNonType = true;
				$pos = $pc['nextPos'];
				$hasAny = true;
				continue;
			}

			break;
		}

		if (!$hasAny) {
			return null;
		}

		if ($canonicalOnly) {
			$typeText = '';
			if ($typeSelector !== null && !($typeSelector['isUniversal'] && !$typeSelector['hasExplicitNs'] && $hasNonType)) {
				$typeText = $typeSelector['ns'] . $typeSelector['name'];
			}
			if ($canonicalParts === []) {
				return ['text' => $typeText . $textBuf];
			}
			if ($typeText !== '') {
				if (is_string($canonicalParts[0])) {
					$canonicalParts[0] = $typeText . $canonicalParts[0];
				} else {
					array_unshift($canonicalParts, $typeText);
				}
			}
			if ($textBuf !== '') {
				$canonicalParts[] = $textBuf;
			}
			return ['parts' => $canonicalParts];
		}

		return [
			'type' => $typeSelector,
			'subclasses' => $subclasses,
			'pseudoElements' => $pseudoElements,
			'hasNesting' => $hasNesting,
		];
	}

	/**
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 */
	private static function parseAttributeSelector(array $tokens, int $start, int $end): ?string
	{
		$pos = $start;
		CSSSyntaxScanner::trimTokenRange($tokens, $pos, $end);
		if ($pos >= $end) {
			return null;
		}

		$qName = self::parseQualifiedName($tokens, $pos, $end, false);
		if ($qName === null) {
			return null;
		}

		$wqName = $qName['ns'] . $qName['name'];
		while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
			$pos++;
		}
		if ($pos >= $end) {
			return '[' . $wqName . ']';
		}

		$matcher = null;
		if ($tokens[$pos][0] === CSSTokenType::Delim) {
			$c0 = $tokens[$pos][3];
			if ($c0 === '=') {
				$matcher = '=';
				$pos++;
			} elseif ($pos + 1 < $end
				&& $tokens[$pos + 1][0] === CSSTokenType::Delim
				&& $tokens[$pos + 1][3] === '='
				&& isset(self::ATTR_MATCHERS[$c0 . '='])
			) {
				$matcher = $c0 . '=';
				$pos += 2;
			}
		}
		if ($matcher === null) {
			return null;
		}

		while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
			$pos++;
		}
		if ($pos >= $end) {
			return null;
		}

		$valTok = $tokens[$pos];
		if ($valTok[0] !== CSSTokenType::String && $valTok[0] !== CSSTokenType::Ident) {
			return null;
		}
		$serializedValue = self::serializeDoubleQuotedString($valTok[3]);
		$pos++;

		while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
			$pos++;
		}

		$modifier = '';
		if ($pos < $end) {
			if ($tokens[$pos][0] !== CSSTokenType::Ident) {
				return null;
			}
			$modLower = strtolower($tokens[$pos][3]);
			if ($modLower !== 'i' && $modLower !== 's') {
				return null;
			}
			$modifier = ' ' . $modLower;
			$pos++;
			while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos < $end) {
				return null;
			}
		}

		return '[' . $wqName . $matcher . $serializedValue . $modifier . ']';
	}

	/**
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
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
		if ($tok[0] === CSSTokenType::Ident) {
			$lower = strtolower($tok[3]);
			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-elem', 'text' => '::' . ($tok[7] ? CSS::escape($lower) : $lower), 'spec' => [0, 0, 1]],
				'nextPos' => $pos + 1,
			];
		}

		if ($tok[0] === CSSTokenType::Function) {
			$closeIdx = $pairEnd[$pos] ?? null;
			if ($closeIdx === null || $closeIdx >= $end) {
				return null;
			}
			$fnLower = strtolower($tok[3]);
			$innerStart = $pos + 1;
			$innerEnd = $closeIdx;
			CSSSyntaxScanner::trimTokenRange($tokens, $innerStart, $innerEnd);
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
					if ($tokens[$i][0] === CSSTokenType::Whitespace) {
						continue;
					}
					if ($tokens[$i][0] !== CSSTokenType::Ident) {
						return null;
					}
					$parts[] = $tokens[$i][7] ? CSS::escape($tokens[$i][3]) : $tokens[$i][3];
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

			$rawInner = substr($source, $tokens[$innerStart][1], $tokens[$innerEnd - 1][2] - $tokens[$innerStart][1]);
			$cleanInner = str_contains($rawInner, '/*')
				? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($rawInner))
				: CSSSyntaxScanner::trimCssWhitespace($rawInner);
			if ($cleanInner === '') {
				return null;
			}
			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-elem', 'text' => '::' . ($tok[7] ? CSS::escape($fnLower) : $fnLower) . '(' . $cleanInner . ')', 'spec' => [0, 0, 1]],
				'nextPos' => $closeIdx + 1,
			];
		}

		return null;
	}

	/**
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
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
		if ($tok[0] === CSSTokenType::Ident) {
			$lower = strtolower($tok[3]);
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
				'node' => ['kind' => 'pseudo-class', 'text' => ':' . ($tok[7] ? CSS::escape($lower) : $lower), 'spec' => [0, 1, 0]],
				'nextPos' => $pos + 1,
				'isPseudoElement' => false,
			];
		}

		if ($tok[0] !== CSSTokenType::Function) {
			return null;
		}
		$closeIdx = $pairEnd[$pos] ?? null;
		if ($closeIdx === null || $closeIdx >= $end) {
			return null;
		}

		$fnLower = strtolower($tok[3]);
		$innerStart = $pos + 1;
		$innerEnd = $closeIdx;
		CSSSyntaxScanner::trimTokenRange($tokens, $innerStart, $innerEnd);

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
			$nth = self::parseNthArgument($source, $tokens, $innerStart, $innerEnd, $allowOf);
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
			$langs = self::parseCommaArguments($tokens, $innerStart, $innerEnd, true);
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
			if ($innerEnd - $innerStart !== 1 || $tokens[$innerStart][0] !== CSSTokenType::Ident) {
				return null;
			}
			$argTok = $tokens[$innerStart];
			$argRaw = $fnLower === 'dir' ? strtolower($argTok[3]) : $argTok[3];
			$arg = $argTok[7] ? CSS::escape($argRaw) : $argRaw;
			self::incrementNodes($nodeCount);
			return [
				'node' => ['kind' => 'pseudo-class', 'text' => ':' . $fnLower . '(' . $arg . ')', 'spec' => [0, 1, 0]],
				'nextPos' => $closeIdx + 1,
				'isPseudoElement' => false,
			];
		}

		if ($fnLower === 'active-view-transition-type') {
			$types = self::parseCommaArguments($tokens, $innerStart, $innerEnd, false);
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

		$rawInner = substr($source, $tokens[$innerStart][1], $tokens[$innerEnd - 1][2] - $tokens[$innerStart][1]);
		$cleanInner = str_contains($rawInner, '/*')
			? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($rawInner))
			: CSSSyntaxScanner::trimCssWhitespace($rawInner);
		if ($cleanInner === '') {
			return null;
		}
		self::incrementNodes($nodeCount);
		return [
			'node' => ['kind' => 'pseudo-class', 'text' => ':' . ($tok[7] ? CSS::escape($fnLower) : $fnLower) . '(' . $cleanInner . ')', 'spec' => [0, 1, 0]],
			'nextPos' => $closeIdx + 1,
			'isPseudoElement' => false,
		];
	}

	/**
	 * Parses CSS Syntax L3 §6 `<an+b>` optionally followed by `of <selector-list>`.
	 *
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 * @return ?array{anb: string, ofStart: ?int}
	 */
	private static function parseNthArgument(string $source, array $tokens, int $start, int $end, bool $allowOf): ?array
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

		if ($tok[0] === CSSTokenType::Ident) {
			$lower = strtolower($tok[3]);
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
			} elseif (str_starts_with($lower, 'n-') && strlen($lower) > 2 && ctype_digit(substr($lower, 2))) {
				$a = 1;
				$b = -(int) substr($lower, 2);
				$pos++;
			} elseif (str_starts_with($lower, '-n-') && strlen($lower) > 3 && ctype_digit(substr($lower, 3))) {
				$a = -1;
				$b = -(int) substr($lower, 3);
				$pos++;
			} elseif ($lower === 'n-' || $lower === '-n-') {
				$a = $lower === '-n-' ? -1 : 1;
				$pos++;
				$needUnsignedAfterMinus = true;
			} else {
				return null;
			}
		} elseif ($tok[0] === CSSTokenType::Delim && $tok[3] === '+') {
			$pos++;
			if ($pos >= $end || $tokens[$pos][0] !== CSSTokenType::Ident) {
				return null;
			}
			$lower = strtolower($tokens[$pos][3]);
			if ($lower === 'n') {
				$a = 1;
				$pos++;
				$needSignOrEnd = true;
			} elseif (str_starts_with($lower, 'n-') && strlen($lower) > 2 && ctype_digit(substr($lower, 2))) {
				$a = 1;
				$b = -(int) substr($lower, 2);
				$pos++;
			} elseif ($lower === 'n-') {
				$a = 1;
				$pos++;
				$needUnsignedAfterMinus = true;
			} else {
				return null;
			}
		} elseif ($tok[0] === CSSTokenType::Number && $tok[4] === CSSToken::NUMERIC_INTEGER) {
			if (!is_finite((float) $tok[5])) {
				return null;
			}
			$a = 0;
			$b = (int) $tok[5];
			$pos++;
		} elseif ($tok[0] === CSSTokenType::Dimension && $tok[4] === CSSToken::NUMERIC_INTEGER) {
			if (!is_finite((float) $tok[5])) {
				return null;
			}
			$a = (int) $tok[5];
			$unitLower = strtolower($tok[6] ?? '');
			if ($unitLower === 'n') {
				$pos++;
				$needSignOrEnd = true;
			} elseif (str_starts_with($unitLower, 'n-') && strlen($unitLower) > 2 && ctype_digit(substr($unitLower, 2))) {
				$b = -(int) substr($unitLower, 2);
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
			while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos >= $end || $tokens[$pos][0] !== CSSTokenType::Number || $tokens[$pos][4] !== CSSToken::NUMERIC_INTEGER) {
				return null;
			}
			$c0 = $source[$tokens[$pos][1]] ?? '';
			if ($c0 === '' || $c0 === '+' || $c0 === '-' || !is_finite((float) $tokens[$pos][5])) {
				return null;
			}
			$b = -(int) $tokens[$pos][5];
			$pos++;
		} elseif ($needSignOrEnd) {
			$wsBefore = $pos;
			while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos < $end) {
				$next = $tokens[$pos];
				if ($next[0] === CSSTokenType::Number && $next[4] === CSSToken::NUMERIC_INTEGER) {
					$c0 = $source[$next[1]] ?? '';
					if ($c0 === '' || ($c0 !== '+' && $c0 !== '-') || !is_finite((float) $next[5])) {
						return null;
					}
					$b = (int) $next[5];
					$pos++;
				} elseif ($next[0] === CSSTokenType::Delim && ($next[3] === '+' || $next[3] === '-')) {
					$sign = $next[3] === '-' ? -1 : 1;
					$pos++;
					while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
						$pos++;
					}
					if ($pos >= $end || $tokens[$pos][0] !== CSSTokenType::Number || $tokens[$pos][4] !== CSSToken::NUMERIC_INTEGER) {
						return null;
					}
					$c0 = $source[$tokens[$pos][1]] ?? '';
					if ($c0 === '' || $c0 === '+' || $c0 === '-' || !is_finite((float) $tokens[$pos][5])) {
						return null;
					}
					$b = $sign * (int) $tokens[$pos][5];
					$pos++;
				} else {
					$b = 0;
					$pos = $wsBefore;
				}
			} else {
				$b = 0;
			}
		}

		while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
			$pos++;
		}

		$ofStart = null;
		if ($pos < $end) {
			if (!$allowOf || $tokens[$pos][0] !== CSSTokenType::Ident || strtolower($tokens[$pos][3]) !== 'of') {
				return null;
			}
			$pos++;
			while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
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
	 * @param list<array{0: CSSTokenType, 1: int, 2: int, 3: string, 4: ?string, 5: ?float, 6: ?string, 7: bool}> $tokens
	 * @return ?list<string>
	 */
	private static function parseCommaArguments(array $tokens, int $start, int $end, bool $allowStrings): ?array
	{
		$args = [];
		$pos = $start;
		while ($pos < $end) {
			while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos >= $end) {
				return null;
			}
			$tok = $tokens[$pos];
			if ($allowStrings && $tok[0] === CSSTokenType::String) {
				$args[] = self::serializeDoubleQuotedString($tok[3]);
			} elseif ($tok[0] === CSSTokenType::Ident) {
				$args[] = $tok[7] ? CSS::escape($tok[3]) : $tok[3];
			} else {
				return null;
			}
			$pos++;
			while ($pos < $end && $tokens[$pos][0] === CSSTokenType::Whitespace) {
				$pos++;
			}
			if ($pos >= $end) {
				break;
			}
			if ($tokens[$pos][0] !== CSSTokenType::Comma) {
				return null;
			}
			$pos++;
		}

		return $args !== [] ? $args : null;
	}

	/**
	 * @param array<string, mixed> $complex
	 * @param array<int, list<array<string, mixed>>> $listSlots
	 * @return array{leadingCombinator: string, text: string}
	 */
	private static function finalizeCanonicalComplex(array $complex, array $listSlots): array
	{
		$out = '';
		foreach ($complex['parts'] as $part) {
			if (is_string($part)) {
				$out .= $part;
			} else {
				$texts = [];
				foreach ($listSlots[$part[1]] ?? [] as $child) {
					$texts[] = $child['text'];
				}
				$out .= $part[0] . implode(', ', $texts) . $part[2];
			}
		}

		return [
			'leadingCombinator' => $complex['leadingCombinator'],
			'text' => $out,
		];
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
				/** @var list<int> $childSlots */
				$childSlots = $frame[1];
				$destSlot = $frame[2];
				$max = [0, 0, 0];
				foreach ($childSlots as $cSlot) {
					$cSpec = $slots[$cSlot];
					if (self::compareSpecificity($cSpec, $max) > 0) {
						$max = $cSpec;
					}
				}
				$slots[$destSlot] = $max;
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
				[$childrenList, $workSlot] = $childWork[$i];
				$listChildSlots = [];
				$listSubWork = [];
				foreach ($childrenList as $childComplex) {
					$s = $nextSlot++;
					$listChildSlots[] = $s;
					$listSubWork[] = [$childComplex, $s];
				}
				$stack[] = [2, $listChildSlots, $workSlot];
				for ($j = count($listSubWork) - 1; $j >= 0; $j--) {
					$stack[] = [0, $listSubWork[$j][0], $listSubWork[$j][1]];
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
	 * @param list<array{0: list<array<string, mixed>>, 1: int}> $childWork
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
			/** @var list<array<string, mixed>> $children */
			$children = $node['children'] ?? [];
			if ($children === []) {
				return;
			}
			$slot = $nextSlot++;
			$childSlots[] = $slot;
			$childWork[] = [$children, $slot];
			return;
		}
		if ($kind === 'pseudo-nth' || $kind === 'pseudo-host' || $kind === 'pseudo-elem-func') {
			$base[$kind === 'pseudo-elem-func' ? 2 : 1]++;
			/** @var list<array<string, mixed>> $children */
			$children = $node['children'] ?? [];
			if ($children !== []) {
				$slot = $nextSlot++;
				$childSlots[] = $slot;
				$childWork[] = [$children, $slot];
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
		if (strcspn($value, "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0a\x0b\x0c\x0d\x0e\x0f\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1a\x1b\x1c\x1d\x1e\x1f\x7f\"\\") === $len) {
			return '"' . $value . '"';
		}

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
