<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use LogicException;
use OutOfBoundsException;
use Traversable;

/**
 * Ordered collection of CSSRule objects owned by a stylesheet or grouping rule.
 *
 * @property-read int $length
 *
 * @implements ArrayAccess<int, CSSRule>
 * @implements IteratorAggregate<int, CSSRule>
 */
final class CSSRuleList implements ArrayAccess, Countable, IteratorAggregate
{
	/** @var list<CSSRule> */
	private array $rules = [];

	public function __construct(
		private ?CSSStyleSheet $sheet,
		private readonly ?CSSGroupingRule $owner = null,
	) {
	}

	public function count(): int
	{
		return count($this->rules);
	}

	public function item(int $index): ?CSSRule
	{
		return $this->rules[$index] ?? null;
	}

	/**
	 * @internal Direct array access for hot internal loops without ArrayIterator overhead.
	 * @return list<CSSRule>
	 */
	public function getRulesRaw(): array
	{
		return $this->rules;
	}

	/**
	 * @internal Inspects a rule subtree in a single iterative pass, counting rules, declarations, max block depth, and optional content bytes.
	 * @return array{rules: int, height: int, declarations: int, bytes: int}
	 */
	public static function inspectSubtree(CSSRule $rule, ?int $maxBytes = null): array
	{
		$stack = [[$rule, 0]];
		$visited = [];
		$rules = 0;
		$height = 0;
		$declarations = 0;
		$bytes = 0;

		while ($stack !== []) {
			[$current, $relDepth] = array_pop($stack);
			$id = spl_object_id($current);
			if (isset($visited[$id])) {
				throw new LogicException('Cannot attach or serialize a cyclic or DAG-shared CSS rule tree.');
			}
			$visited[$id] = true;
			$rules++;

			if ($maxBytes !== null) {
				if ($current instanceof CSSOpaqueRule) {
					$bytes += strlen($current->getCssText());
				} elseif ($current instanceof CSSStyleRule) {
					$bytes += strlen($current->getSelectorText());
				} elseif ($current instanceof CSSFunctionRule) {
					$bytes += max(strlen($current->getPrelude()), $current->getComponentBytesInternal());
				} elseif ($current instanceof CSSGroupingRule) {
					$bytes += strlen($current->getPrelude());
				} elseif ($current instanceof CSSAtRule) {
					$bytes += strlen($current->getPrelude());
				} elseif ($current instanceof CSSKeyframeRule) {
					$bytes += strlen($current->getKeyText());
				}
				if ($bytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('CSS rule exceeds maximum size of %d bytes.', $maxBytes));
				}
			}

			$ownedStyle = $current->getOwnedStyleInternal();
			if ($ownedStyle !== null) {
				$declarations += count($ownedStyle->getDeclarationsRaw());
				if ($maxBytes !== null) {
					$bytes += $ownedStyle->getContentBytesInternal();
					if ($bytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('CSS rule exceeds maximum size of %d bytes.', $maxBytes));
					}
				}
			}

			if ($current instanceof CSSGroupingRule) {
				$blockDepth = $relDepth + 1;
				if ($blockDepth > $height) {
					$height = $blockDepth;
				}
				if ($current instanceof CSSFontFeatureValuesRule) {
					foreach ($current->getFeatureMaps() as $map) {
						$declarations += count($map);
						if ($maxBytes !== null) {
							$bytes += $map->contentBytesInternal($maxBytes - $bytes);
						}
					}
				}
				foreach ($current->getCssRules()->getRulesRaw() as $child) {
					$stack[] = [$child, $relDepth + 1];
				}
			} elseif ($current instanceof CSSAtRule) {
				$blockDepth = $current->hasBlock() ? $relDepth + 1 : $relDepth;
				if ($blockDepth > $height) {
					$height = $blockDepth;
				}
			} elseif ($current instanceof CSSKeyframeRule) {
				$blockDepth = $relDepth + 1;
				if ($blockDepth > $height) {
					$height = $blockDepth;
				}
			} elseif ($relDepth > $height) {
				$height = $relDepth;
			}
		}

		return ['rules' => $rules, 'height' => $height, 'declarations' => $declarations, 'bytes' => $bytes];
	}

	/**
	 * @internal Counts a rule and all its nested descendant rules.
	 */
	public static function countSubtreeRules(CSSRule $rule): int
	{
		return self::inspectSubtree($rule)['rules'];
	}

	/** @return Traversable<int, CSSRule> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->rules);
	}

	public function offsetExists(mixed $offset): bool
	{
		return is_int($offset) && isset($this->rules[$offset]);
	}

	public function offsetGet(mixed $offset): ?CSSRule
	{
		return is_int($offset) ? ($this->rules[$offset] ?? null) : null;
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new InvalidArgumentException('Use insertRule() to modify a CSSRuleList.');
	}

	public function offsetUnset(mixed $offset): void
	{
		throw new InvalidArgumentException('Use deleteRule() to modify a CSSRuleList.');
	}

	/**
	 * @throws OutOfBoundsException When $index is outside [0, count]
	 * @throws LogicException When the list is detached from a stylesheet or $rule is cyclic/DAG-shared
	 * @throws InvalidArgumentException When inserting $rule would create a rule-tree cycle or exceed limits
	 */
	public function insert(int $index, CSSRule $rule): void
	{
		$this->insertWithOptionsInternal($index, $rule, null);
	}

	/**
	 * @internal Inserts a rule while optionally applying caller-provided limit overrides.
	 */
	public function insertWithOptionsInternal(int $index, CSSRule $rule, ?CSSParserOptions $overrideOptions): void
	{
		if ($index < 0 || $index > count($this->rules)) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}
		if ($this->sheet === null) {
			throw new LogicException('A detached CSSRuleList cannot be modified.');
		}
		if ($rule->getParentStyleSheet() !== null || $rule->getParentRule() !== null) {
			throw new InvalidArgumentException('Cannot insert a CSSRule that already belongs to a parent.');
		}
		$this->assertNoCycle($rule);
		$this->assertHierarchyForInsertion($rule, $index);

		$effectiveMaxBytes = $overrideOptions !== null ? $overrideOptions->maxBytes : $this->sheet->getMaxBytesInternal();
		$stats = self::inspectSubtree($rule, $effectiveMaxBytes);
		$this->assertLimitsForAddition($stats['rules'], $stats['height'], $stats['declarations'], $overrideOptions);

		$rule->attach($this->sheet, $this->owner);
		if ($index === count($this->rules)) {
			$this->rules[] = $rule;
		} else {
			array_splice($this->rules, $index, 0, [$rule]);
		}
		$this->sheet->adjustTotalRuleCount($stats['rules']);
		$this->sheet->adjustTotalDeclarationCount($stats['declarations']);
		if ($this->owner === null && $this->sheet->isStrictPreludeRuleInternal($rule)) {
			$this->sheet->adjustStrictPreludeCountInternal(1);
		}
	}

	/**
	 * @throws LogicException When the list is detached from a stylesheet or $rule is cyclic/DAG-shared
	 * @throws InvalidArgumentException When appending $rule would create a rule-tree cycle, reparent an attached rule, violate hierarchy, or exceed limits
	 */
	public function append(CSSRule $rule): void
	{
		$this->insertWithOptionsInternal(count($this->rules), $rule, null);
	}

	private function assertHierarchyForInsertion(CSSRule $rule, int $index): void
	{
		if ($this->owner === null) {
			assert($this->sheet !== null);
			$this->sheet->assertValidInsertHierarchyInternal($rule, $index);
			return;
		}
		CSSGroupingRule::assertValidChildRuleInternal($rule);
		if ($rule instanceof CSSStyleRule && $rule->hasLeadingCombinatorInternal() && !$this->hasScopeOrStyleAncestor()) {
			throw new InvalidArgumentException('Relative selectors starting with a combinator are only allowed inside style rules or @scope rules.');
		}
	}

	private function hasScopeOrStyleAncestor(): bool
	{
		$cursor = $this->owner;
		$visited = [];
		while ($cursor !== null) {
			$id = spl_object_id($cursor);
			if (isset($visited[$id])) {
				break;
			}
			$visited[$id] = true;
			if ($cursor instanceof CSSScopeRule || $cursor instanceof CSSStyleRule) {
				return true;
			}
			$cursor = $cursor->getParentRule();
		}

		return false;
	}

	/**
	 * @internal Appends a top-level rule whose parser limits were already enforced by CSSParser.
	 */
	public function appendParsedInternal(CSSRule $rule): void
	{
		if ($this->sheet === null) {
			throw new LogicException('A detached CSSRuleList cannot be modified.');
		}
		if ($rule->getParentStyleSheet() !== null || $rule->getParentRule() !== null) {
			throw new InvalidArgumentException('Cannot append a CSSRule that already belongs to a parent.');
		}
		$this->assertNoCycle($rule);

		$stats = self::inspectSubtree($rule);
		$rule->attach($this->sheet, $this->owner);
		$this->rules[] = $rule;
		$this->sheet->adjustTotalRuleCount($stats['rules']);
		$this->sheet->adjustTotalDeclarationCount($stats['declarations']);
		if ($this->owner === null && $this->sheet->isStrictPreludeRuleInternal($rule)) {
			$this->sheet->adjustStrictPreludeCountInternal(1);
		}
	}

	/** @internal */
	public function getSheetInternal(): ?CSSStyleSheet
	{
		return $this->sheet;
	}

	/** @internal */
	public function setSheetInternal(?CSSStyleSheet $sheet): void
	{
		$this->sheet = $sheet;
	}

	/** @internal */
	public function getOwnerInternal(): ?CSSGroupingRule
	{
		return $this->owner;
	}

	/** @internal Appends a freshly cloned rule directly during iterative tree cloning. */
	public function appendClonedInternal(CSSRule $rule): void
	{
		$this->rules[] = $rule;
	}

	/**
	 * @internal Builds detached grouping/style rule subtrees during parsing before a single top-level attach.
	 */
	public function appendDetached(CSSRule $rule): void
	{
		if ($this->sheet !== null) {
			throw new LogicException('appendDetached() requires a detached CSSRuleList.');
		}
		if ($rule->getParentStyleSheet() !== null || $rule->getParentRule() !== null) {
			throw new InvalidArgumentException('Cannot append a CSSRule that already belongs to a parent.');
		}
		$this->assertNoCycle($rule);

		$rule->attach(null, $this->owner);
		$this->rules[] = $rule;
	}

	/**
	 * @throws LogicException When called on a detached rule list
	 * @throws OutOfBoundsException When $index is invalid
	 */
	public function remove(int $index): CSSRule
	{
		if ($this->sheet === null) {
			throw new LogicException('A detached CSSRuleList cannot be modified.');
		}
		if (!isset($this->rules[$index])) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}
		if ($this->owner === null) {
			$this->sheet->assertValidDeleteHierarchyInternal($index);
		}

		if ($index === count($this->rules) - 1) {
			$rule = array_pop($this->rules);
		} else {
			[$rule] = array_splice($this->rules, $index, 1);
		}
		$stats = self::inspectSubtree($rule);
		$rule->attach(null, null);
		$this->sheet->adjustTotalRuleCount(-$stats['rules']);
		$this->sheet->adjustTotalDeclarationCount(-$stats['declarations']);
		if ($this->owner === null && $this->sheet->isStrictPreludeRuleInternal($rule)) {
			$this->sheet->adjustStrictPreludeCountInternal(-1);
		}

		return $rule;
	}

	/**
	 * Replaces all rules in O(N) time while updating parent links and sheet rule counts.
	 *
	 * @throws InvalidArgumentException When any replacement rule would create a rule-tree cycle, is already attached to this sheet, or exceeds limits
	 */
	public function replaceAll(self $replacement): void
	{
		$this->replaceAllWithOptionsInternal($replacement, null);
	}

	/**
	 * @internal Atomically replaces all rules while optionally enforcing caller-provided limit overrides.
	 */
	public function replaceAllWithOptionsInternal(
		self $replacement,
		?CSSParserOptions $overrideOptions,
		bool $adoptOverrideLimits = false,
	): void {
		if ($this->sheet === null) {
			throw new LogicException('A detached CSSRuleList cannot be modified.');
		}

		$effectiveMaxBytes = $overrideOptions !== null ? $overrideOptions->maxBytes : $this->sheet->getMaxBytesInternal();
		$addedCount = 0;
		$addedDecls = 0;
		$addedBytes = 0;
		$maxAddedHeight = 0;
		$nextStrictPreludeCount = 0;
		$seenAny = false;
		$seenNonImportPrelude = false;
		$seenRegular = false;
		$allowLeadingCombinator = $this->owner !== null && $this->hasScopeOrStyleAncestor();
		$next = [];
		foreach ($replacement->rules as $rule) {
			if ($rule->getParentStyleSheet() === $this->sheet || $rule->getParentRule() !== null) {
				throw new InvalidArgumentException('Cannot move already-owned CSSRule instances into replaceAll().');
			}
			$this->assertNoCycle($rule);
			if ($this->owner !== null) {
				CSSGroupingRule::assertValidChildRuleInternal($rule);
				if ($rule instanceof CSSStyleRule && $rule->hasLeadingCombinatorInternal() && !$allowLeadingCombinator) {
					throw new InvalidArgumentException('Relative selectors starting with a combinator are only allowed inside style rules or @scope rules.');
				}
			} else {
				if ($rule instanceof CSSStyleRule && $rule->hasLeadingCombinatorInternal()) {
					throw new InvalidArgumentException('Top-level style rules cannot start with a relative combinator.');
				}
				if ($rule instanceof CSSCharsetRule && $seenAny) {
					throw new InvalidArgumentException('@charset rule must be the first rule in the stylesheet.');
				}
				if ($rule instanceof CSSImportRule && $seenNonImportPrelude) {
					throw new InvalidArgumentException('@import rules must precede all non-prelude rules except @charset and statement @layer rules.');
				}
				if ($rule instanceof CSSNamespaceRule && $seenRegular) {
					throw new InvalidArgumentException('@namespace rules must precede all regular rules.');
				}
				if ($this->sheet->isStrictPreludeRuleInternal($rule)) {
					$nextStrictPreludeCount++;
				}
				$seenAny = true;
				if (!$rule instanceof CSSCharsetRule && !$rule instanceof CSSImportRule && !$rule instanceof CSSLayerStatementRule) {
					$seenNonImportPrelude = true;
				}
				if (!$rule instanceof CSSCharsetRule
					&& !$rule instanceof CSSImportRule
					&& !$rule instanceof CSSNamespaceRule
					&& !$rule instanceof CSSLayerStatementRule
				) {
					$seenRegular = true;
				}
			}
			$remainingBytes = $effectiveMaxBytes !== null ? max(0, $effectiveMaxBytes - $addedBytes) : null;
			$stats = self::inspectSubtree($rule, $remainingBytes);
			$addedCount += $stats['rules'];
			$addedDecls += $stats['declarations'];
			$addedBytes += $stats['bytes'];
			if ($stats['height'] > $maxAddedHeight) {
				$maxAddedHeight = $stats['height'];
			}
			$next[] = $rule;
		}

		if ($this->owner === null) {
			$removedCount = $this->sheet->getTotalRuleCount();
			$removedDecls = $this->sheet->getTotalDeclarationCount();
		} else {
			$removedCount = 0;
			$removedDecls = 0;
			foreach ($this->rules as $existing) {
				$stats = self::inspectSubtree($existing);
				$removedCount += $stats['rules'];
				$removedDecls += $stats['declarations'];
			}
		}

		$this->assertLimitsForAddition(
			$addedCount - $removedCount,
			$maxAddedHeight,
			$addedDecls - $removedDecls,
			$overrideOptions,
			$maxAddedHeight > 0,
		);

		foreach ($this->rules as $existing) {
			$existing->attach(null, null);
		}
		if ($adoptOverrideLimits && $overrideOptions !== null) {
			$this->sheet->configureLimitsInternal($overrideOptions);
		}
		foreach ($next as $rule) {
			$rule->attach($this->sheet, $this->owner);
		}
		$replacement->rules = [];
		$replacement->sheet?->adjustTotalRuleCount(-$addedCount);
		$replacement->sheet?->adjustTotalDeclarationCount(-$addedDecls);
		if ($replacement->owner === null) {
			$replacement->sheet?->setStrictPreludeCountInternal(0);
		}
		$this->rules = $next;
		$this->sheet->adjustTotalRuleCount($addedCount - $removedCount);
		$this->sheet->adjustTotalDeclarationCount($addedDecls - $removedDecls);
		if ($this->owner === null) {
			$this->sheet->setStrictPreludeCountInternal($nextStrictPreludeCount);
		}
	}

	public function reparent(?CSSStyleSheet $sheet): void
	{
		$stack = [$this];
		$visited = [];
		while ($stack !== []) {
			$list = array_pop($stack);
			$listId = spl_object_id($list);
			if (isset($visited[$listId])) {
				continue;
			}
			$visited[$listId] = true;
			$list->sheet = $sheet;
			$owner = $list->owner;
			foreach ($list->rules as $rule) {
				$rule->setParentLinks($sheet, $owner);
				if ($rule instanceof CSSGroupingRule) {
					$childList = $rule->getCssRules();
					if ($childList->sheet !== $sheet) {
						$stack[] = $childList;
					}
				}
			}
		}
	}

	public function __clone(): void
	{
		$origRules = $this->rules;
		$this->rules = [];
		$this->sheet = null;
		$this->owner = null;
		if ($origRules !== []) {
			CSSGroupingRule::cloneRulesIntoList($origRules, $this, null, null);
		}
	}

	public function __get(string $name): mixed
	{
		if ($name === 'length') {
			return $this->count();
		}
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'length';
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	private function assertNoCycle(CSSRule $candidate): void
	{
		$ancestor = $this->owner;
		$visited = [];
		while ($ancestor !== null) {
			if ($ancestor === $candidate) {
				throw new InvalidArgumentException('Cannot insert an ancestor rule into its own descendant CSSRuleList.');
			}
			$id = spl_object_id($ancestor);
			if (isset($visited[$id])) {
				throw new InvalidArgumentException('Detected a cycle in CSSGroupingRule ancestor chain.');
			}
			$visited[$id] = true;
			$ancestor = $ancestor->getParentRule();
		}
	}

	private function assertLimitsForAddition(
		int $deltaRules,
		int $subtreeHeight,
		int $deltaDeclarations,
		?CSSParserOptions $overrideOptions,
		bool $checkHeight = true,
	): void {
		if ($this->sheet === null) {
			return;
		}

		$maxRules = $overrideOptions !== null ? $overrideOptions->maxRules : $this->sheet->getMaxRulesInternal();
		if ($deltaRules > 0 && $maxRules !== null && ($this->sheet->getTotalRuleCount() + $deltaRules) > $maxRules) {
			throw new CSSParserLimitException(sprintf('Maximum CSS rule limit of %d exceeded.', $maxRules));
		}

		$maxDeclarations = $overrideOptions !== null ? $overrideOptions->maxDeclarations : $this->sheet->getMaxDeclarationsInternal();
		if ($deltaDeclarations > 0 && $maxDeclarations !== null && ($this->sheet->getTotalDeclarationCount() + $deltaDeclarations) > $maxDeclarations) {
			throw new CSSParserLimitException(sprintf('Maximum CSS declaration limit of %d exceeded.', $maxDeclarations));
		}

		if ($checkHeight) {
			$maxDepth = $overrideOptions !== null ? $overrideOptions->maxDepth : $this->sheet->getMaxDepthInternal();
			$effectiveMaxDepth = $maxDepth !== null ? min($maxDepth, CSSRenderer::MAX_RENDER_DEPTH) : null;
			if ($effectiveMaxDepth !== null) {
				$parentDepth = $this->owner !== null ? $this->owner->getNestingDepthInternal() : 0;
				if (($parentDepth + $subtreeHeight) > $effectiveMaxDepth) {
					throw new CSSParserLimitException(sprintf('Maximum CSS nesting depth of %d exceeded.', $effectiveMaxDepth));
				}
			}
		}
	}
}
