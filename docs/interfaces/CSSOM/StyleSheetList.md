# `StyleSheetList`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\StyleSheetList`
- **Source:** [`src/CSSOM/StyleSheetList.php`](../../../src/CSSOM/StyleSheetList.php)
- **Purpose:** Read-only ordered collection of standalone `StyleSheet` objects.
- **Implements:** `ArrayAccess<int, StyleSheet>`, `Countable`, `IteratorAggregate<int, StyleSheet>`.

## Properties

Magic read-only `length` returns item count. Array assignment/unset throws `InvalidArgumentException`.

## Public Methods

| Signature | Parameters / behavior | Throws / result | Example |
|---|---|---|---|
| `__construct(array $sheets = [])` | Accepts an ordered list of `StyleSheet`; validates every entry. | `InvalidArgumentException` for non-`StyleSheet` values. | `$list = new StyleSheetList([$sheet]);` |
| `item(int $index): ?StyleSheet` | Returns sheet or `null` if absent. | | `$list->item(0);` |
| `count(): int` | Returns number of sheets. | | `count($list);` |
| `offsetExists(mixed $offset): bool` | True for an integer offset with an entry. | | `isset($list[0]);` |
| `offsetGet(mixed $offset): ?StyleSheet` | Integer offset returns sheet or `null`; other values return `null`. | | `$list[0];` |
| `offsetSet(mixed $offset, mixed $value): void` | Always rejects mutation. | `InvalidArgumentException`. | `$list[] = $sheet; // throws` |
| `offsetUnset(mixed $offset): void` | Always rejects mutation. | `InvalidArgumentException`. | `unset($list[0]); // throws` |
| `getIterator(): Traversable` | Iterates sheets in order. | | `foreach ($list as $sheet) {}` |
| `__get(string $name): mixed` | Supports `length`; unknown names throw `Error`. | | `$list->length;` |
| `__isset(string $name): bool` | Returns `true` only for `length`. | | `isset($list->length);` |
| `__set(string $name, mixed $value): void` | Always rejects writes (`Error`), preventing dynamic properties. | `Error`. | `$list->length = 0; // throws` |
