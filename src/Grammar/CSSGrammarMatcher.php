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
	 * @param-out int $stepsOut
	 */
	public static function matches(
		CSSGrammarNode $rootNode,
		string $source,
		bool $hasComments,
		array $components,
		CSSParserOptions $options,
		?int &$stepsOut = null,
	): bool {
		$rootCount = count($components);
		if ($rootCount === 0) {
			$stepsOut = 0;
			return false;
		}

		$nextCtxId = 1;
		$contexts = [
			0 => [
				'components' => $components,
				'count' => $rootCount,
			],
		];

		/** @var array<int, array<int, array<int, list<int>>>> $memo [$ctxId][$nodeId][$pos] = list<int> */
		$memo = [];
		/** @var array<string, mixed> $evalCache */
		$evalCache = [];
		$mathBudget = 0;
		$steps = 0;

		// Root frame awaits eval(ctxId=0, rootNode, pos=0)
		$stack = [
			['phase' => self::PHASE_EVAL, 'ctxId' => 0, 'node' => $rootNode, 'pos' => 0],
		];

		while ($stack !== []) {
			if (++$steps > self::MAX_MATCH_STEPS) {
				$stepsOut = $steps;
				return false;
			}
			if (count($stack) > self::MAX_MATCH_DEPTH) {
				$stepsOut = $steps;
				return false;
			}

			$frame = array_pop($stack);
			$phase = $frame['phase'];

			if ($phase === self::PHASE_EVAL) {
				/** @var int $ctxId */
				$ctxId = $frame['ctxId'];
				/** @var CSSGrammarNode $node */
				$node = $frame['node'];
				/** @var int $pos */
				$pos = $frame['pos'];

				if (isset($memo[$ctxId][$node->id][$pos])) {
					continue;
				}

				$ctxComps = $contexts[$ctxId]['components'];
				$ctxCount = $contexts[$ctxId]['count'];

				switch ($node->type) {
					case CSSGrammarNodeType::Keyword:
						$ends = [];
						if ($pos < $ctxCount
							&& $ctxComps[$pos]['kind'] === 'token'
							&& ($ctxComps[$pos]['tokenType'] ?? null) === CSSTokenType::Ident
						) {
							$tokIdent = (string) ($ctxComps[$pos]['name'] ?? '');
							$matched = $node->caseSensitive
								? ($tokIdent === $node->value)
								: (strtolower($tokIdent) === $node->value);
							if ($matched) {
								$ends = [$pos + 1];
							}
						}
						$memo[$ctxId][$node->id][$pos] = $ends;
						break;

					case CSSGrammarNodeType::Delim:
						$ends = [];
						if ($pos < $ctxCount && $ctxComps[$pos]['kind'] === 'token') {
							$tokType = $ctxComps[$pos]['tokenType'] ?? null;
							if (($node->value === ',' && $tokType === CSSTokenType::Comma)
								|| ($tokType === CSSTokenType::Delim && substr($source, $ctxComps[$pos]['start'], $ctxComps[$pos]['end'] - $ctxComps[$pos]['start']) === $node->value)
							) {
								$ends = [$pos + 1];
							}
						}
						$memo[$ctxId][$node->id][$pos] = $ends;
						break;

					case CSSGrammarNodeType::DataType:
						$memo[$ctxId][$node->id][$pos] = CSSDataTypeMatcher::matchEnds($node, $source, $hasComments, $ctxComps, $pos, $options, $evalCache, $mathBudget);
						break;

					case CSSGrammarNodeType::NonTerminal:
						$target = CSSPropertyGrammarRegistry::resolveNonTerminal((string) $node->value);
						if ($target === null) {
							$memo[$ctxId][$node->id][$pos] = [];
							break;
						}
						if (isset($memo[$ctxId][$target->id][$pos])) {
							$memo[$ctxId][$node->id][$pos] = $memo[$ctxId][$target->id][$pos];
							break;
						}
						// Resolve target lazily, then store result under $node->id via single-item sequence frame
						$stack[] = [
							'phase' => self::PHASE_ALT,
							'ctxId' => $ctxId,
							'node' => $node,
							'pos' => $pos,
							'targets' => [$target],
							'idx' => 0,
							'acc' => [],
						];
						$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $ctxId, 'node' => $target, 'pos' => $pos];
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
						$innerCtxId = $nextCtxId++;
						$contexts[$innerCtxId] = [
							'components' => $innerComps,
							'count' => $innerCount,
						];
						// Reuse PHASE_REQ check: inner match succeeds iff innerCount is reachable from inner pos 0
						$stack[] = [
							'phase' => self::PHASE_REQ,
							'ctxId' => $ctxId,
							'node' => $node,
							'pos' => $pos,
							'innerCtxId' => $innerCtxId,
							'innerChild' => $innerChild,
							'innerCount' => $innerCount,
							'isInnerBlock' => true,
						];
						$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $innerCtxId, 'node' => $innerChild, 'pos' => 0];
						break;

					case CSSGrammarNodeType::Alternative:
						$targets = $node->children;
						if ($targets === []) {
							$memo[$ctxId][$node->id][$pos] = [];
							break;
						}
						$stack[] = [
							'phase' => self::PHASE_ALT,
							'ctxId' => $ctxId,
							'node' => $node,
							'pos' => $pos,
							'targets' => $targets,
							'idx' => 0,
							'acc' => [],
						];
						$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $ctxId, 'node' => $targets[0], 'pos' => $pos];
						break;

					case CSSGrammarNodeType::Sequence:
						$children = $node->children;
						if ($children === []) {
							$memo[$ctxId][$node->id][$pos] = [$pos];
							break;
						}
						$stack[] = [
							'phase' => self::PHASE_SEQ,
							'ctxId' => $ctxId,
							'node' => $node,
							'pos' => $pos,
							'children' => $children,
							'childIdx' => 0,
							'frontier' => [$pos],
							'fIdx' => 0,
							'nextFrontier' => [],
						];
						$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $ctxId, 'node' => $children[0], 'pos' => $pos];
						break;

					case CSSGrammarNodeType::RequiredGroup:
						$child = $node->children[0];
						$stack[] = [
							'phase' => self::PHASE_REQ,
							'ctxId' => $ctxId,
							'node' => $node,
							'pos' => $pos,
							'innerCtxId' => $ctxId,
							'innerChild' => $child,
							'innerCount' => 0,
							'isInnerBlock' => false,
						];
						$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $ctxId, 'node' => $child, 'pos' => $pos];
						break;

					case CSSGrammarNodeType::Repeat:
						$child = $node->children[0];
						$accepted = $node->min === 0 ? [$pos => true] : [];
						if ($node->max === 0) {
							$memo[$ctxId][$node->id][$pos] = [$pos];
							break;
						}
						$stack[] = [
							'phase' => self::PHASE_REPEAT,
							'ctxId' => $ctxId,
							'node' => $node,
							'pos' => $pos,
							'child' => $child,
							'repCount' => 0,
							'frontier' => [$pos],
							'fIdx' => 0,
							'nextFrontier' => [],
							'accepted' => $accepted,
							'awaitingStart' => $pos,
						];
						$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $ctxId, 'node' => $child, 'pos' => $pos];
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
							'phase' => self::PHASE_ORDER,
							'ctxId' => $ctxId,
							'node' => $node,
							'pos' => $pos,
							'children' => $children,
							'n' => $n,
							'requireAll' => $node->type === CSSGrammarNodeType::AllOrder,
							'queue' => [[0, $pos]],
							'visited' => [($pos << 31) => true],
							'qIdx' => 0,
							'branchIdx' => 0,
							'accepted' => [],
							'awaiting' => null,
						];
						// Trigger child 0 from (mask=0, curPos=$pos)
						$stack[count($stack) - 1]['awaiting'] = [0, $pos, 0];
						$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $ctxId, 'node' => $children[0], 'pos' => $pos];
						break;
				}
				continue;
			}

			if ($phase === self::PHASE_ALT) {
				$ctxId = $frame['ctxId'];
				$node = $frame['node'];
				$pos = $frame['pos'];
				$targets = $frame['targets'];
				$idx = $frame['idx'];
				$acc = $frame['acc'];

				$res = $memo[$ctxId][$targets[$idx]->id][$pos] ?? [];
				foreach ($res as $end) {
					$acc[$end] = true;
				}

				$idx++;
				if ($idx < count($targets)) {
					$frame['idx'] = $idx;
					$frame['acc'] = $acc;
					$stack[] = $frame;
					$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $ctxId, 'node' => $targets[$idx], 'pos' => $pos];
				} else {
					$ends = array_keys($acc);
					if (count($ends) > 1) {
						sort($ends);
					}
					$memo[$ctxId][$node->id][$pos] = $ends;
				}
				continue;
			}

			if ($phase === self::PHASE_REQ) {
				$ctxId = $frame['ctxId'];
				$node = $frame['node'];
				$pos = $frame['pos'];
				$innerCtxId = $frame['innerCtxId'];
				$innerChild = $frame['innerChild'];
				$lookupPos = $frame['isInnerBlock'] ? 0 : $pos;
				$res = $memo[$innerCtxId][$innerChild->id][$lookupPos] ?? [];

				if ($frame['isInnerBlock']) {
					$targetEnd = $frame['innerCount'];
					$matched = in_array($targetEnd, $res, true);
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
				$ctxId = $frame['ctxId'];
				$node = $frame['node'];
				$pos = $frame['pos'];
				$children = $frame['children'];
				$childIdx = $frame['childIdx'];
				$frontier = $frame['frontier'];
				$fIdx = $frame['fIdx'];
				$nextFrontier = $frame['nextFrontier'];

				$curP = $frontier[$fIdx];
				$childId = $children[$childIdx]->id;
				foreach ($memo[$ctxId][$childId][$curP] ?? [] as $end) {
					$nextFrontier[$end] = true;
				}

				$fIdx++;
				if ($fIdx < count($frontier)) {
					$frame['fIdx'] = $fIdx;
					$frame['nextFrontier'] = $nextFrontier;
					$stack[] = $frame;
					$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $ctxId, 'node' => $children[$childIdx], 'pos' => $frontier[$fIdx]];
					continue;
				}

				// Advance to next child in sequence
				$newFrontier = array_keys($nextFrontier);
				sort($newFrontier);
				$childIdx++;
				if ($newFrontier === [] || $childIdx >= count($children)) {
					$memo[$ctxId][$node->id][$pos] = $childIdx >= count($children) ? $newFrontier : [];
					continue;
				}

				$frame['childIdx'] = $childIdx;
				$frame['frontier'] = $newFrontier;
				$frame['fIdx'] = 0;
				$frame['nextFrontier'] = [];
				$stack[] = $frame;
				$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $ctxId, 'node' => $children[$childIdx], 'pos' => $newFrontier[0]];
				continue;
			}

			if ($phase === self::PHASE_REPEAT) {
				$ctxId = $frame['ctxId'];
				$node = $frame['node'];
				$pos = $frame['pos'];
				$child = $frame['child'];
				$repCount = $frame['repCount'];
				$frontier = $frame['frontier'];
				$fIdx = $frame['fIdx'];
				$nextFrontier = $frame['nextFrontier'];
				$accepted = $frame['accepted'];
				$awaitingStart = $frame['awaitingStart'];
				$ctxComps = $contexts[$ctxId]['components'];
				$ctxCount = $contexts[$ctxId]['count'];

				$res = $memo[$ctxId][$child->id][$awaitingStart] ?? [];
				foreach ($res as $end) {
					// Zero-width repetition guard: an iteration must make positive progress
					if ($end > $awaitingStart) {
						$nextFrontier[$end] = true;
					}
				}

				$fIdx++;
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
					$frame['fIdx'] = $fIdx;
					$frame['nextFrontier'] = $nextFrontier;
					$frame['awaitingStart'] = $nextStart;
					$stack[] = $frame;
					$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $ctxId, 'node' => $child, 'pos' => $nextStart];
					$scheduledNext = true;
					break;
				}
				if ($scheduledNext) {
					continue;
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
				// If unbounded and we already covered >= min without discovering any new end positions, we can still continue only if positions remain strictly < ctxCount and we guard against stalled fixpoints
				if ($newFrontier === [] || $reachedMax || ($node->max === null && $repCount >= $node->min && !$madeNewAccepted && !$node->commaSeparated)) {
					$ends = array_keys($accepted);
					sort($ends);
					$memo[$ctxId][$node->id][$pos] = $ends;
					continue;
				}

				// Prepare next round starting from $newFrontier[0]
				$nextStart = null;
				$startIdx = 0;
				while ($startIdx < count($newFrontier)) {
					$cand = $newFrontier[$startIdx];
					if ($node->commaSeparated) {
						if ($cand >= $ctxCount
							|| $ctxComps[$cand]['kind'] !== 'token'
							|| ($ctxComps[$cand]['tokenType'] ?? null) !== CSSTokenType::Comma
						) {
							$startIdx++;
							continue;
						}
						$nextStart = $cand + 1;
						break;
					}
					$nextStart = $cand;
					break;
				}

				if ($nextStart === null) {
					$ends = array_keys($accepted);
					sort($ends);
					$memo[$ctxId][$node->id][$pos] = $ends;
					continue;
				}

				$frame['repCount'] = $repCount;
				$frame['frontier'] = $newFrontier;
				$frame['fIdx'] = $startIdx;
				$frame['nextFrontier'] = [];
				$frame['accepted'] = $accepted;
				$frame['awaitingStart'] = $nextStart;
				$stack[] = $frame;
				$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $ctxId, 'node' => $child, 'pos' => $nextStart];
				continue;
			}

			if ($phase === self::PHASE_ORDER) {
				$ctxId = $frame['ctxId'];
				$node = $frame['node'];
				$pos = $frame['pos'];
				$children = $frame['children'];
				$n = $frame['n'];
				$requireAll = $frame['requireAll'];
				$queue = $frame['queue'];
				$visited = $frame['visited'];
				$qIdx = $frame['qIdx'];
				$branchIdx = $frame['branchIdx'];
				$accepted = $frame['accepted'];
				[$mask, $curPos, $b] = $frame['awaiting'];

				$res = $memo[$ctxId][$children[$b]->id][$curPos] ?? [];
				$fullMask = (1 << $n) - 1;
				foreach ($res as $nextPos) {
					$nextMask = $mask | (1 << $b);
					if ($requireAll ? $nextMask === $fullMask : $nextMask !== 0) {
						$accepted[$nextPos] = true;
					}
					if ($nextMask !== $fullMask) {
						$key = ($nextPos << 31) | $nextMask;
						if (!isset($visited[$key])) {
							$visited[$key] = true;
							$queue[] = [$nextMask, $nextPos];
						}
					}
				}

				// Advance to next unused branch in current (qIdx) or next queue item
				$branchIdx = $b + 1;
				$scheduled = false;
				while ($qIdx < count($queue)) {
					[$qMask, $qPos] = $queue[$qIdx];
					while ($branchIdx < $n) {
						if (($qMask & (1 << $branchIdx)) === 0) {
							$frame['queue'] = $queue;
							$frame['visited'] = $visited;
							$frame['qIdx'] = $qIdx;
							$frame['branchIdx'] = $branchIdx;
							$frame['accepted'] = $accepted;
							$frame['awaiting'] = [$qMask, $qPos, $branchIdx];
							$stack[] = $frame;
							$stack[] = ['phase' => self::PHASE_EVAL, 'ctxId' => $ctxId, 'node' => $children[$branchIdx], 'pos' => $qPos];
							$scheduled = true;
							break 2;
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
}
