# `CSSRuleList`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSRuleList`
- **Source:** [`src/CSSOM/CSSRuleList.php`](../../../src/CSSOM/CSSRuleList.php)
- **Purpose:** Ordered, mutable collection of `CSSRule` objects associated with a stylesheet and optional grouping owner.
- **Implements:** `ArrayAccess<int, CSSRule>`, `Countable`, `IteratorAggregate<int, CSSRule>`.

## Properties

Magic read-only `length` returns `count()`. Array writes/unsets are rejected; mutation uses the explicit methods below.

## Public Methods

| Signature | Parameters / behavior | Throws / result | Example |
|---|---|---|---|
| `__construct(?CSSStyleSheet $sheet, ?CSSGroupingRule $owner = null)` | Creates an empty rule list with its sheet and owner context. | | `$list = new CSSRuleList($sheet);` |
| `count(): int` | Returns number of rules. | | `count($list);` |
| `item(int $index): ?CSSRule` | Returns the indexed rule or `null`. | | `$list->item(0);` |
| `getIterator(): Traversable` | Iterates indexed rules in order. | | `foreach ($list as $rule) {}` |
| `offsetExists(mixed $offset): bool` | True only for an integer offset containing a rule. | | `isset($list[0]);` |
| `offsetGet(mixed $offset): ?CSSRule` | Integer offsets use `item()`; other offsets yield `null`. | | `$rule = $list[0];` |
| `offsetSet(mixed $offset, mixed $value): void` | Always throws `InvalidArgumentException`; use insert/append APIs. | | `$list[] = $rule; // throws` |
| `offsetUnset(mixed $offset): void` | Always throws `InvalidArgumentException`; use `remove()`. | | `unset($list[0]); // throws` |
| `insert(int $index, CSSRule $rule): void` | Attaches and inserts at index. Requires attached sheet, enforces single-parent ownership & ancestor-descendant cycle prevention, and requires `$index` in `[0, count]`. Throws `OutOfBoundsException`, `LogicException`, or `InvalidArgumentException`. | | `$list->insert(0, $rule);` |
| `append(CSSRule $rule): void` | Attaches and appends in O(1) amortized time; enforces single-parent ownership and throws `LogicException` if detached or already parented. | | `$list->append($rule);` |
| `appendDetached(CSSRule $rule): void` | Appends into a detached or internal rule list while still rejecting already-parented rules (`LogicException`) and ancestor-descendant cycles (`InvalidArgumentException`). | | `$group->getCssRules()->appendDetached($child);` |
| `remove(int $index): CSSRule` | Removes and detaches rule at index; requires an attached sheet (throws `LogicException` if detached) or throws `OutOfBoundsException` if absent. | Returns removed rule. | `$removed = $list->remove(0);` |
| `replaceAll(self $replacement): void` | Detaches old rules, transfers and attaches all replacement rules into this list (emptying `$replacement`), and throws `LogicException` if detached. | | `$list->replaceAll($replacement);` |
| `reparent(?CSSStyleSheet $sheet): void` | Iteratively updates sheet association and reattaches nested rule subtrees without recursion. | | `$list->reparent($sheet);` |
| `__get(string $name): mixed` | Supports `length`; unknown names throw `Error`. | | `$list->length;` |
