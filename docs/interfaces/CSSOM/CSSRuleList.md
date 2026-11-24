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
| `insert(int $index, CSSRule $rule): void` | Attaches and inserts at index. Requires attached sheet, enforces single-parent ownership (`InvalidArgumentException`), ancestor-descendant & DAG cycle prevention, and sheet limits (`maxRules`, `maxDepth`, `maxDeclarations`), and requires `$index` in `[0, count]`. | | `$list->insert(0, $rule);` |
| `append(CSSRule $rule): void` | Inspects subtree in a single iterative pass and appends; rejects detached lists (`LogicException`), already-parented rules (`InvalidArgumentException`), cycles/DAG sharing (`LogicException`), and exceeded sheet limits (`CSSParserLimitException`). | | `$list->append($rule);` |
| `appendDetached(CSSRule $rule): void` | `@internal` helper that appends into a detached rule list while rejecting attached lists (`LogicException`), already-parented rules (`InvalidArgumentException`), and cycles (`InvalidArgumentException`). | | `$group->getCssRules()->appendDetached($child);` |
| `remove(int $index): CSSRule` | Removes and detaches rule at index, adjusting sheet rule/declaration counts; throws `LogicException` if detached or `OutOfBoundsException` if index is invalid. | Returns removed rule. | `$removed = $list->remove(0);` |
| `replaceAll(self $replacement): void` | Atomically detaches old rules, validates replacement rules (ownership, cycles, limits), and moves them into this list (emptying `$replacement`). Throws `LogicException` if detached, `InvalidArgumentException` if invalid/owned, or `CSSParserLimitException` on limits. | | `$list->replaceAll($replacement);` |
| `reparent(?CSSStyleSheet $sheet): void` | Iteratively updates sheet association and reattaches nested rule subtrees without recursion. | | `$list->reparent($sheet);` |
| `__clone(): void` | Iteratively clones list rules as a detached copy (`sheet = null`, `owner = null`). | | `$copy = clone $list;` |
| `__get(string $name): mixed` | Supports `length`; unknown names throw `Error`. | | `$list->length;` |
