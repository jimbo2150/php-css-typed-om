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
		return is_int($offset) ? $this->item($offset) : null;
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
	 */
	public function insert(int $index, CSSRule $rule): void
	{
		if ($index < 0 || $index > count($this->rules)) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}
		if ($this->sheet === null) {
			throw new LogicException('A detached CSSRuleList cannot be modified.');
		}

		$rule->attach($this->sheet, $this->owner);
		if ($index === count($this->rules)) {
			$this->rules[] = $rule;
		} else {
			array_splice($this->rules, $index, 0, [$rule]);
		}
	}

	public function append(CSSRule $rule): void
	{
		if ($this->sheet === null) {
			throw new LogicException('A detached CSSRuleList cannot be modified.');
		}

		$rule->attach($this->sheet, $this->owner);
		$this->rules[] = $rule;
	}

	/**
	 * @throws OutOfBoundsException When $index is invalid
	 */
	public function remove(int $index): CSSRule
	{
		if (!isset($this->rules[$index])) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}

		[$rule] = array_splice($this->rules, $index, 1);
		$rule->attach(null, null);

		return $rule;
	}

	/**
	 * Replaces all rules in O(N) time while updating parent links.
	 */
	public function replaceAll(self $replacement): void
	{
		if ($this->sheet === null) {
			throw new LogicException('A detached CSSRuleList cannot be modified.');
		}

		foreach ($this->rules as $existing) {
			$existing->attach(null, null);
		}

		$next = [];
		foreach ($replacement->rules as $rule) {
			$rule->attach($this->sheet, $this->owner);
			$next[] = $rule;
		}
		$this->rules = $next;
	}

	public function reparent(?CSSStyleSheet $sheet): void
	{
		$this->sheet = $sheet;
		foreach ($this->rules as $rule) {
			$rule->attach($sheet, $this->owner);
		}
	}

	public function __get(string $name): mixed
	{
		if ($name === 'length') {
			return $this->count();
		}
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}
}
