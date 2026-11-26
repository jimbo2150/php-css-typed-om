<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Grammar;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;

/**
 * Iterative bottom-up reachable-position-set evaluator for CSS VDS ASTs.
 *
 * Computes the deduplicated set of reachable end indices `match(node, pos) -> list<int>`
 * with per-`(ctxId, nodeId, pos)` memoization and `(usedMask, curPos)` DP memoization
 * for `&&` (`AllOrder`) and `||` (`AnyOrder`), bounded strictly by `MAX_MATCH_STEPS`
 * and `MAX_MATCH_DEPTH` to eliminate ReDoS and PHP call recursion.
 *
 * @phpstan-import-type ParsedComponent from CSSValueComponentParser
 * @internal
 */
final class CSSGrammarMatcher
{
	public const int MAX_MATCH_STEPS = 65_536;
	public const int MAX_MATCH_DEPTH = 128;

	private const int PHASE_EVAL = 0;
	private const int PHASE_SEQ = 1;
	private const int PHASE_ALT = 2;
	private const int PHASE_REQ = 3;
	private const int PHASE_REPEAT = 4;
	private const int PHASE_ORDER = 5;

	/**
	 * Evaluates `$rootNode` over the entire component list starting at position 0.
	 *
	 * @param list<ParsedComponent> $components
	 * @param array<string, mixed> $evalCacheOut
	 * @param-out int $stepsOut
	 * @param-out array<string, mixed> $evalCacheOut
	 */
	public static function matches(
		CSSGrammarNode $rootNode,
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
		?int &$stepsOut = null,
		array &$evalCacheOut = [],
		int &$mathBudgetOut = 0,
	): bool {
		$rootCount = count($components);
		if ($rootCount === 0) {
			$stepsOut = 0;
			return false;
		}

		// Single-node root fast path: skip context/memo/stack allocations on flat leaf grammars.
		$rootEnds = self::evalLeafEnds($rootNode, $source, $hasComments, $components, $rootCount, 0, $options, $evalCacheOut, $mathBudgetOut);
		if ($rootEnds !== null) {
			$stepsOut = 1;
			return in_array($rootCount, $rootEnds, true);
		}

		$nextCtxId = 1;
		$contexts = [
			0 => [$components, $rootCount],
		];

		/** @var array<int, array<int, array<int, list<int>>>> $memo [$ctxId][$nodeId][$pos] = list<int> */
		$memo = [];
		$steps = 0;

		// Packed frame tuples:
		// [0=EVAL, ctxId, node, pos]
		// [1=SEQ,  ctxId, node, pos, children, childIdx, frontier, fIdx, nextFrontier]
		// [2=ALT,  ctxId, node, pos, targets, idx, acc]
		// [3=REQ,  ctxId, node, pos, innerCtxId, innerChild, innerCount, isInnerBlock]
		// [4=REPEAT, ctxId, node, pos, child, repCount, frontier, fIdx, nextFrontier, accepted, awaitingStart]
		// [5=ORDER, ctxId, node, pos, children, n, requireAll, queue, visited, qIdx, accepted, awaiting]
		/** @var list<non-empty-array<int, mixed>> $stack */
		$stack = [
			[self::PHASE_EVAL, 0, $rootNode, 0],
		];

		while ($stack !== []) {
			if (++$steps > self::MAX_MATCH_STEPS || count($stack) > self::MAX_MATCH_DEPTH) {
				$stepsOut = $steps;
				return false;
			}

			$frame = array_pop($stack);
			$phase = $frame[0];

			if ($phase === self::PHASE_EVAL) {
				/** @var array{0: 0, 1: int, 2: CSSGrammarNode, 3: int} $frame */
				[, $ctxId, $node, $pos] = $frame;
				if (isset($memo[$ctxId][$node->id][$pos])) {
					continue;
				}

				[$ctxComps, $ctxCount] = $contexts[$ctxId];
				$inlineEnds = self::evalLeafEnds($node, $source, $hasComments, $ctxComps, $ctxCount, $pos, $options, $evalCacheOut, $mathBudgetOut);
				if ($inlineEnds !== null) {
					$memo[$ctxId][$node->id][$pos] = $inlineEnds;
					continue;
				}

				switch ($node->type) {
					case CSSGrammarNodeType::NonTerminal:
						$target = CSSPropertyGrammarRegistry::resolveNonTerminal((string) $node->value);
						if ($target === null) {
							$memo[$ctxId][$node->id][$pos] = [];
							break;
						}
						$targetEnds = $memo[$ctxId][$target->id][$pos] ?? null;
						if ($targetEnds !== null) {
							$memo[$ctxId][$node->id][$pos] = $targetEnds;
							break;
						}
						$inlineTarget = self::evalLeafEnds($target, $source, $hasComments, $ctxComps, $ctxCount, $pos, $options, $evalCacheOut, $mathBudgetOut);
						if ($inlineTarget !== null) {
							$memo[$ctxId][$target->id][$pos] = $inlineTarget;
							$memo[$ctxId][$node->id][$pos] = $inlineTarget;
							break;
						}
						$stack[] = [self::PHASE_ALT, $ctxId, $node, $pos, [$target], 0, []];
						$stack[] = [self::PHASE_EVAL, $ctxId, $target, $pos];
						break;

					case CSSGrammarNodeType::FunctionCall:
					case CSSGrammarNodeType::SquareBracketBlock:
						$wantKind = $node->type === CSSGrammarNodeType::FunctionCall ? 'function' : 'bracket';
						if ($pos >= $ctxCount || $ctxComps[$pos]['kind'] !== $wantKind) {
							$memo[$ctxId][$node->id][$pos] = [];
							break;
						}
						if ($wantKind === 'function' && ($ctxComps[$pos]['name'] ?? '') !== $node->value) {
							$memo[$ctxId][$node->id][$pos] = [];
							break;
						}
						/** @var list<ParsedComponent> $innerComps */
						$innerComps = $ctxComps[$pos]['components'] ?? [];
						$innerChild = $node->children[0] ?? null;
						if ($innerChild === null) {
							$memo[$ctxId][$node->id][$pos] = $innerComps === [] ? [$pos + 1] : [];
							break;
						}
						$innerCount = count($innerComps);
						$innerInline = self::evalLeafEnds($innerChild, $source, $hasComments, $innerComps, $innerCount, 0, $options, $evalCacheOut, $mathBudgetOut);
						if ($innerInline !== null) {
							$memo[$ctxId][$node->id][$pos] = in_array($innerCount, $innerInline, true) ? [$pos + 1] : [];
							break;
						}
						$innerCtxId = $nextCtxId++;
						$contexts[$innerCtxId] = [$innerComps, $innerCount];
						$stack[] = [self::PHASE_REQ, $ctxId, $node, $pos, $innerCtxId, $innerChild, $innerCount, true];
						$stack[] = [self::PHASE_EVAL, $innerCtxId, $innerChild, 0];
						break;

					case CSSGrammarNodeType::Alternative:
						$acc = [];
						if ($node->keywordMap !== []
							&& $pos < $ctxCount
							&& $ctxComps[$pos]['kind'] === 'token'
							&& ($ctxComps[$pos]['tokenType'] ?? null) === CSSTokenType::Ident
						) {
							$kwKey = $node->keywordMapCaseSensitive
								? (string) ($ctxComps[$pos]['name'] ?? '')
								: (string) ($ctxComps[$pos]['lower'] ?? '');
							if (isset($node->keywordMap[$kwKey])) {
								$acc[$pos + 1] = true;
							}
						}
						$targets = [];
						if ($node->functionMap !== [] && $pos < $ctxCount && $ctxComps[$pos]['kind'] === 'function') {
							$fnName = (string) ($ctxComps[$pos]['name'] ?? '');
							foreach ($node->functionMap[$fnName] ?? [] as $fnChild) {
								$targets[] = $fnChild;
							}
						}
						foreach ($node->fallbackChildren as $fbChild) {
							$targets[] = $fbChild;
						}

						$tCount = count($targets);
						$tIdx = 0;
						$pushed = false;
						while ($tIdx < $tCount) {
							$childNode = $targets[$tIdx];
							$cEnds = $memo[$ctxId][$childNode->id][$pos] ?? null;
							if ($cEnds === null) {
								$inline = self::evalLeafEnds($childNode, $source, $hasComments, $ctxComps, $ctxCount, $pos, $options, $evalCacheOut, $mathBudgetOut);
								if ($inline === null) {
									$stack[] = [self::PHASE_ALT, $ctxId, $node, $pos, $targets, $tIdx, $acc];
									$stack[] = [self::PHASE_EVAL, $ctxId, $childNode, $pos];
									$pushed = true;
									break;
								}
								$cEnds = $memo[$ctxId][$childNode->id][$pos] = $inline;
							}
							if ($steps >= self::MAX_MATCH_STEPS) {
								$stepsOut = $steps + 1;
								return false;
							}
							$steps++;
							foreach ($cEnds as $end) {
								$acc[$end] = true;
							}
							$tIdx++;
						}
						if (!$pushed) {
							$ends = array_keys($acc);
							if (count($ends) > 1) {
								sort($ends);
							}
							$memo[$ctxId][$node->id][$pos] = $ends;
						}
						break;

					case CSSGrammarNodeType::Sequence:
						$children = $node->children;
						if ($children === []) {
							$memo[$ctxId][$node->id][$pos] = [$pos];
							break;
						}
						$childIdx = 0;
						$frontier = [$pos];
						$fIdx = 0;
						$nextFrontier = [];
						$cCount = count($children);
						while (true) {
							$childNode = $children[$childIdx];
							$curP = $frontier[$fIdx];
							$cEnds = $memo[$ctxId][$childNode->id][$curP] ?? null;
							if ($cEnds === null) {
								$inline = self::evalLeafEnds($childNode, $source, $hasComments, $ctxComps, $ctxCount, $curP, $options, $evalCacheOut, $mathBudgetOut);
								if ($inline === null) {
									$stack[] = [self::PHASE_SEQ, $ctxId, $node, $pos, $children, $childIdx, $frontier, $fIdx, $nextFrontier];
									$stack[] = [self::PHASE_EVAL, $ctxId, $childNode, $curP];
									break;
								}
								$cEnds = $memo[$ctxId][$childNode->id][$curP] = $inline;
							}
							if ($steps >= self::MAX_MATCH_STEPS) {
								$stepsOut = $steps + 1;
								return false;
							}
							$steps++;
							foreach ($cEnds as $end) {
								$nextFrontier[$end] = true;
							}
							$fIdx++;
							if ($fIdx < count($frontier)) {
								continue;
							}
							$newFrontier = array_keys($nextFrontier);
							if (count($newFrontier) > 1) {
								sort($newFrontier);
							}
							$childIdx++;
							if ($newFrontier === [] || $childIdx >= $cCount) {
								$memo[$ctxId][$node->id][$pos] = $childIdx >= $cCount ? $newFrontier : [];
								break;
							}
							$frontier = $newFrontier;
							$fIdx = 0;
							$nextFrontier = [];
						}
						break;

					case CSSGrammarNodeType::RequiredGroup:
						$child = $node->children[0];
						$stack[] = [self::PHASE_REQ, $ctxId, $node, $pos, $ctxId, $child, 0, false];
						$stack[] = [self::PHASE_EVAL, $ctxId, $child, $pos];
						break;

					case CSSGrammarNodeType::Repeat:
						$child = $node->children[0];
						$accepted = $node->min === 0 ? [$pos => true] : [];
						if ($node->max === 0) {
							$memo[$ctxId][$node->id][$pos] = [$pos];
							break;
						}
						$stack[] = [self::PHASE_REPEAT, $ctxId, $node, $pos, $child, 0, [$pos], 0, [], $accepted, $pos];
						$stack[] = [self::PHASE_EVAL, $ctxId, $child, $pos];
						break;

					case CSSGrammarNodeType::AllOrder:
					case CSSGrammarNodeType::AnyOrder:
						$children = $node->children;
						$n = count($children);
						if ($n > 30) {
							$memo[$ctxId][$node->id][$pos] = [];
							break;
						}
						$stack[] = [
							self::PHASE_ORDER,
							$ctxId,
							$node,
							$pos,
							$children,
							$n,
							$node->type === CSSGrammarNodeType::AllOrder,
							[[0, $pos]],
							[($pos << 31) => true],
							0,
							[],
							[0, $pos, 0],
						];
						$stack[] = [self::PHASE_EVAL, $ctxId, $children[0], $pos];
						break;
				}
				continue;
			}

			if ($phase === self::PHASE_ALT) {
				/** @var array{0: 2, 1: int, 2: CSSGrammarNode, 3: int, 4: list<CSSGrammarNode>, 5: int, 6: array<int, true>} $frame */
				[, $ctxId, $node, $pos, $targets, $idx, $acc] = $frame;
				[$ctxComps, $ctxCount] = $contexts[$ctxId];

				$res = $memo[$ctxId][$targets[$idx]->id][$pos] ?? [];
				foreach ($res as $end) {
					$acc[$end] = true;
				}
				$idx++;
				$tCount = count($targets);
				$pushed = false;
				while ($idx < $tCount) {
					$childNode = $targets[$idx];
					$cEnds = $memo[$ctxId][$childNode->id][$pos] ?? null;
					if ($cEnds === null) {
						$inline = self::evalLeafEnds($childNode, $source, $hasComments, $ctxComps, $ctxCount, $pos, $options, $evalCacheOut, $mathBudgetOut);
						if ($inline === null) {
							$stack[] = [self::PHASE_ALT, $ctxId, $node, $pos, $targets, $idx, $acc];
							$stack[] = [self::PHASE_EVAL, $ctxId, $childNode, $pos];
							$pushed = true;
							break;
						}
						$cEnds = $memo[$ctxId][$childNode->id][$pos] = $inline;
					}
					if ($steps >= self::MAX_MATCH_STEPS) {
						$stepsOut = $steps + 1;
						return false;
					}
					$steps++;
					foreach ($cEnds as $end) {
						$acc[$end] = true;
					}
					$idx++;
				}
				if (!$pushed) {
					$ends = array_keys($acc);
					if (count($ends) > 1) {
						sort($ends);
					}
					$memo[$ctxId][$node->id][$pos] = $ends;
				}
				continue;
			}

			if ($phase === self::PHASE_REQ) {
				/** @var array{0: 3, 1: int, 2: CSSGrammarNode, 3: int, 4: int, 5: CSSGrammarNode, 6: int, 7: bool} $frame */
				[, $ctxId, $node, $pos, $innerCtxId, $innerChild, $innerCount, $isInnerBlock] = $frame;
				$lookupPos = $isInnerBlock ? 0 : $pos;
				$res = $memo[$innerCtxId][$innerChild->id][$lookupPos] ?? [];

				if ($isInnerBlock) {
					$matched = in_array($innerCount, $res, true);
					$memo[$ctxId][$node->id][$pos] = $matched ? [$pos + 1] : [];
				} else {
					$filtered = [];
					foreach ($res as $end) {
						if ($end > $pos) {
							$filtered[] = $end;
						}
					}
					$memo[$ctxId][$node->id][$pos] = $filtered;
				}
				continue;
			}

			if ($phase === self::PHASE_SEQ) {
				/** @var array{0: 1, 1: int, 2: CSSGrammarNode, 3: int, 4: list<CSSGrammarNode>, 5: int, 6: list<int>, 7: int, 8: array<int, true>} $frame */
				[, $ctxId, $node, $pos, $children, $childIdx, $frontier, $fIdx, $nextFrontier] = $frame;
				[$ctxComps, $ctxCount] = $contexts[$ctxId];
				$cCount = count($children);

				foreach ($memo[$ctxId][$children[$childIdx]->id][$frontier[$fIdx]] ?? [] as $end) {
					$nextFrontier[$end] = true;
				}
				$fIdx++;

				while (true) {
					if ($fIdx < count($frontier)) {
						$childNode = $children[$childIdx];
						$curP = $frontier[$fIdx];
						$cEnds = $memo[$ctxId][$childNode->id][$curP] ?? null;
						if ($cEnds === null) {
							$inline = self::evalLeafEnds($childNode, $source, $hasComments, $ctxComps, $ctxCount, $curP, $options, $evalCacheOut, $mathBudgetOut);
							if ($inline === null) {
								$stack[] = [self::PHASE_SEQ, $ctxId, $node, $pos, $children, $childIdx, $frontier, $fIdx, $nextFrontier];
								$stack[] = [self::PHASE_EVAL, $ctxId, $childNode, $curP];
								break;
							}
							$cEnds = $memo[$ctxId][$childNode->id][$curP] = $inline;
						}
						if ($steps >= self::MAX_MATCH_STEPS) {
							$stepsOut = $steps + 1;
							return false;
						}
						$steps++;
						foreach ($cEnds as $end) {
							$nextFrontier[$end] = true;
						}
						$fIdx++;
						continue;
					}

					$newFrontier = array_keys($nextFrontier);
					if (count($newFrontier) > 1) {
						sort($newFrontier);
					}
					$childIdx++;
					if ($newFrontier === [] || $childIdx >= $cCount) {
						$memo[$ctxId][$node->id][$pos] = $childIdx >= $cCount ? $newFrontier : [];
						break;
					}

					$frontier = $newFrontier;
					$fIdx = 0;
					$nextFrontier = [];
				}
				continue;
			}

			if ($phase === self::PHASE_REPEAT) {
				[, $ctxId, $node, $pos, $child, $repCount, $frontier, $fIdx, $nextFrontier, $accepted, $awaitingStart] = $frame;
				[$ctxComps, $ctxCount] = $contexts[$ctxId];

				foreach ($memo[$ctxId][$child->id][$awaitingStart] ?? [] as $end) {
					// Zero-width repetition guard: an iteration must make positive progress
					if ($end > $awaitingStart) {
						$nextFrontier[$end] = true;
					}
				}
				$fIdx++;

				while (true) {
					$scheduledNext = false;
					while ($fIdx < count($frontier)) {
						$nextStart = $frontier[$fIdx];
						if ($repCount > 0 && $node->commaSeparated) {
							if ($nextStart >= $ctxCount
								|| $ctxComps[$nextStart]['kind'] !== 'token'
								|| ($ctxComps[$nextStart]['tokenType'] ?? null) !== CSSTokenType::Comma
							) {
								$fIdx++;
								continue;
							}
							$nextStart++;
						}
						$cEnds = $memo[$ctxId][$child->id][$nextStart] ?? null;
						if ($cEnds === null) {
							$inline = self::evalLeafEnds($child, $source, $hasComments, $ctxComps, $ctxCount, $nextStart, $options, $evalCacheOut, $mathBudgetOut);
							if ($inline === null) {
								$stack[] = [self::PHASE_REPEAT, $ctxId, $node, $pos, $child, $repCount, $frontier, $fIdx, $nextFrontier, $accepted, $nextStart];
								$stack[] = [self::PHASE_EVAL, $ctxId, $child, $nextStart];
								$scheduledNext = true;
								break;
							}
							$cEnds = $memo[$ctxId][$child->id][$nextStart] = $inline;
						}
						if ($steps >= self::MAX_MATCH_STEPS) {
							$stepsOut = $steps + 1;
							return false;
						}
						$steps++;
						foreach ($cEnds as $end) {
							if ($end > $nextStart) {
								$nextFrontier[$end] = true;
							}
						}
						$fIdx++;
					}
					if ($scheduledNext) {
						break;
					}

					// Completed repetition round $repCount + 1
					$repCount++;
					$newFrontier = array_keys($nextFrontier);
					if (count($newFrontier) > 1) {
						sort($newFrontier);
					}

					$madeNewAccepted = false;
					if ($repCount >= $node->min) {
						foreach ($newFrontier as $endPos) {
							if (!isset($accepted[$endPos])) {
								$accepted[$endPos] = true;
								$madeNewAccepted = true;
							}
						}
					}

					$reachedMax = $node->max !== null && $repCount >= $node->max;
					if ($newFrontier === [] || $reachedMax || ($node->max === null && $repCount >= $node->min && !$madeNewAccepted && !$node->commaSeparated)) {
						$ends = array_keys($accepted);
						if (count($ends) > 1) {
							sort($ends);
						}
						$memo[$ctxId][$node->id][$pos] = $ends;
						break;
					}

					$frontier = $newFrontier;
					$fIdx = 0;
					$nextFrontier = [];
				}
				continue;
			}

			if ($phase === self::PHASE_ORDER) {
				/** @var array{0: 5, 1: int, 2: CSSGrammarNode, 3: int, 4: list<CSSGrammarNode>, 5: int, 6: bool, 7: list<array{int, int}>, 8: array<int, true>, 9: int, 10: array<int, true>, 11: array{int, int, int}} $frame */
				[, $ctxId, $node, $pos, $children, $n, $requireAll, $queue, $visited, $qIdx, $accepted, $awaiting] = $frame;
				[$ctxComps, $ctxCount] = $contexts[$ctxId];
				$fullMask = (1 << $n) - 1;
				[$mask, $curPos, $b] = $awaiting;

				$res = $memo[$ctxId][$children[$b]->id][$curPos] ?? [];
				$nextMaskBase = $mask | (1 << $b);
				foreach ($res as $nextPos) {
					if ($requireAll ? $nextMaskBase === $fullMask : $nextMaskBase !== 0) {
						$accepted[$nextPos] = true;
					}
					if ($nextMaskBase !== $fullMask) {
						$key = ($nextPos << 31) | $nextMaskBase;
						if (!isset($visited[$key])) {
							$visited[$key] = true;
							$queue[] = [$nextMaskBase, $nextPos];
						}
					}
				}

				$branchIdx = $b + 1;
				$scheduled = false;
				while ($qIdx < count($queue)) {
					[$qMask, $qPos] = $queue[$qIdx];
					while ($branchIdx < $n) {
						if (($qMask & (1 << $branchIdx)) === 0) {
							$childNode = $children[$branchIdx];
							$cEnds = $memo[$ctxId][$childNode->id][$qPos] ?? null;
							if ($cEnds === null) {
								$inline = self::evalLeafEnds($childNode, $source, $hasComments, $ctxComps, $ctxCount, $qPos, $options, $evalCacheOut, $mathBudgetOut);
								if ($inline === null) {
									$stack[] = [self::PHASE_ORDER, $ctxId, $node, $pos, $children, $n, $requireAll, $queue, $visited, $qIdx, $accepted, [$qMask, $qPos, $branchIdx]];
									$stack[] = [self::PHASE_EVAL, $ctxId, $childNode, $qPos];
									$scheduled = true;
									break 2;
								}
								$cEnds = $memo[$ctxId][$childNode->id][$qPos] = $inline;
							}
							if ($steps >= self::MAX_MATCH_STEPS) {
								$stepsOut = $steps + 1;
								return false;
							}
							$steps++;
							$newMask = $qMask | (1 << $branchIdx);
							foreach ($cEnds as $nextPos) {
								if ($requireAll ? $newMask === $fullMask : $newMask !== 0) {
									$accepted[$nextPos] = true;
								}
								if ($newMask !== $fullMask) {
									$key = ($nextPos << 31) | $newMask;
									if (!isset($visited[$key])) {
										$visited[$key] = true;
										$queue[] = [$newMask, $nextPos];
									}
								}
							}
						}
						$branchIdx++;
					}
					$qIdx++;
					$branchIdx = 0;
				}

				if (!$scheduled) {
					$ends = array_keys($accepted);
					if (count($ends) > 1) {
						sort($ends);
					}
					$memo[$ctxId][$node->id][$pos] = $ends;
				}
			}
		}

		$stepsOut = $steps;
		$rootEnds = $memo[0][$rootNode->id][0] ?? [];

		return in_array($rootCount, $rootEnds, true);
	}

	/**
	 * Evaluates leaf nodes and pure-keyword `Alternative` nodes directly without stack frames,
	 * returning `null` when composite AST traversal is required.
	 *
	 * @param list<ParsedComponent> $ctxComps
	 * @param array<string, mixed> $evalCache
	 * @return ?list<int>
	 */
	private static function evalLeafEnds(
		CSSGrammarNode $node,
		string $source,
		bool $hasComments,
		array $ctxComps,
		int $ctxCount,
		int $pos,
		CSSParserOptions $options,
		array &$evalCache,
		int &$mathBudget,
	): ?array {
		return match ($node->type) {
			CSSGrammarNodeType::Keyword => ($pos < $ctxCount
				&& $ctxComps[$pos]['kind'] === 'token'
				&& ($ctxComps[$pos]['tokenType'] ?? null) === CSSTokenType::Ident
				&& ($node->caseSensitive ? ($ctxComps[$pos]['name'] ?? '') === $node->value : ($ctxComps[$pos]['lower'] ?? '') === $node->value))
				? [$pos + 1]
				: [],
			CSSGrammarNodeType::Delim => ($pos < $ctxCount
				&& $ctxComps[$pos]['kind'] === 'token'
				&& (($node->value === ',' && ($ctxComps[$pos]['tokenType'] ?? null) === CSSTokenType::Comma)
					|| (($ctxComps[$pos]['tokenType'] ?? null) === CSSTokenType::Delim
						&& substr($source, $ctxComps[$pos]['start'], $ctxComps[$pos]['end'] - $ctxComps[$pos]['start']) === $node->value)))
				? [$pos + 1]
				: [],
			CSSGrammarNodeType::DataType => CSSDataTypeMatcher::matchEnds($node, $source, $hasComments, $ctxComps, $pos, $options, $evalCache, $mathBudget),
			CSSGrammarNodeType::Alternative => ($node->functionMap === [] && $node->fallbackChildren === [])
				? (($pos < $ctxCount
					&& $ctxComps[$pos]['kind'] === 'token'
					&& ($ctxComps[$pos]['tokenType'] ?? null) === CSSTokenType::Ident
					&& isset($node->keywordMap[$node->keywordMapCaseSensitive ? (string) ($ctxComps[$pos]['name'] ?? '') : (string) ($ctxComps[$pos]['lower'] ?? '')]))
					? [$pos + 1]
					: [])
				: null,
			default => null,
		};
	}
}
