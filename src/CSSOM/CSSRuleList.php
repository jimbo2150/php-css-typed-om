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
	 * @internal Counts a rule and all its nested descendant rules.
	 */
	public static function countSubtreeRules(CSSRule $rule): int
	{
		if (!$rule instanceof CSSGroupingRule) {
			return 1;
		}

		$total = 0;
		$stack = [$rule];
		$visited = [];
		while ($stack !== []) {
			$current = array_pop($stack);
			$id = spl_object_id($current);
			if (isset($visited[$id])) {
				continue;
			}
			$visited[$id] = true;
			$total++;
			if ($current instanceof CSSGroupingRule) {
				foreach ($current->getCssRules()->getRulesRaw() as $child) {
					$stack[] = $child;
				}
			}
		}

		return $total;
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
	 * @throws LogicException When the list is detached from a stylesheet
	 * @throws InvalidArgumentException When inserting $rule would create a rule-tree cycle
	 */
	public function insert(int $index, CSSRule $rule): void
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

		$delta = self::countSubtreeRules($rule);
		$rule->attach($this->sheet, $this->owner);
		if ($index === count($this->rules)) {
			$this->rules[] = $rule;
		} else {
			array_splice($this->rules, $index, 0, [$rule]);
		}
		$this->sheet->adjustTotalRuleCount($delta);
	}

	/**
	 * @throws LogicException When the list is detached from a stylesheet
	 * @throws InvalidArgumentException When appending $rule would create a rule-tree cycle or reparent an attached rule
	 */
	public function append(CSSRule $rule): void
	{
		if ($this->sheet === null) {
			throw new LogicException('A detached CSSRuleList cannot be modified.');
		}
		if ($rule->getParentStyleSheet() !== null || $rule->getParentRule() !== null) {
			throw new InvalidArgumentException('Cannot append a CSSRule that already belongs to a parent.');
		}
		$this->assertNoCycle($rule);

		$delta = self::countSubtreeRules($rule);
		$rule->attach($this->sheet, $this->owner);
		$this->rules[] = $rule;
		$this->sheet->adjustTotalRuleCount($delta);
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

		if ($index === count($this->rules) - 1) {
			$rule = array_pop($this->rules);
		} else {
			[$rule] = array_splice($this->rules, $index, 1);
		}
		$delta = self::countSubtreeRules($rule);
		$rule->attach(null, null);
		$this->sheet->adjustTotalRuleCount(-$delta);

		return $rule;
	}

	/**
	 * Replaces all rules in O(N) time while updating parent links and sheet rule counts.
	 *
	 * @throws InvalidArgumentException When any replacement rule would create a rule-tree cycle or is already attached to this sheet
	 */
	public function replaceAll(self $replacement): void
	{
		if ($this->sheet === null) {
			throw new LogicException('A detached CSSRuleList cannot be modified.');
		}

		foreach ($replacement->rules as $rule) {
			if ($rule->getParentStyleSheet() === $this->sheet || $rule->getParentRule() !== null) {
				throw new InvalidArgumentException('Cannot move already-owned CSSRule instances into replaceAll().');
			}
			$this->assertNoCycle($rule);
		}

		$removedCount = 0;
		foreach ($this->rules as $existing) {
			$removedCount += self::countSubtreeRules($existing);
			$existing->attach(null, null);
		}

		$next = [];
		$addedCount = 0;
		foreach ($replacement->rules as $rule) {
			$addedCount += self::countSubtreeRules($rule);
			$rule->attach($this->sheet, $this->owner);
			$next[] = $rule;
		}
		$replacement->rules = [];
		$replacement->sheet?->adjustTotalRuleCount(-$addedCount);
		$this->rules = $next;
		$this->sheet->adjustTotalRuleCount($addedCount - $removedCount);
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
}
