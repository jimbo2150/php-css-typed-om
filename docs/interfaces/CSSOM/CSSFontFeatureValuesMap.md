# `CSSFontFeatureValuesMap`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesMap`
- **Source:** [`src/CSSOM/CSSFontFeatureValuesMap.php`](../../../src/CSSOM/CSSFontFeatureValuesMap.php)
- **Purpose:** Mutable map from font feature names to non-empty integer value lists.
- **Implements:** `Countable`, `IteratorAggregate<string, list<int>>`.

## Properties

Magic read-only `size` returns the entry count. No public constants or other public properties are declared.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `get(string $name): ?array` | Returns the value list or `null` if absent. | `$map->get('alt');` |
| `set(string $name, array $values): void` | `$name` must be non-empty and structurally valid; `$values` must contain only integers and at least one value. Reindexes values. Throws `InvalidArgumentException` on invalid input. | `$map->set('alt', [1, 2]);` |
| `has(string $name): bool` | Tests key presence without interpreting the values. | `$map->has('alt');` |
| `delete(string $name): void` | Removes the entry if present; missing keys are ignored. | `$map->delete('alt');` |
| `clear(): void` | Removes all entries. | `$map->clear();` |
| `count(): int` | Returns entry count. | `count($map);` |
| `getSize(): int` | Returns the same entry count as `count()`. | `$map->getSize();` |
| `keys(): array` | Returns keys in insertion order. | `$map->keys();` |
| `values(): array` | Returns value lists in insertion order. | `$map->values();` |
| `entries(): array` | Returns ordered `[name, values]` pairs. | `$map->entries();` |
| `getIterator(): Traversable` | Iterates key => integer-list pairs in insertion order. | `foreach ($map as $name => $values) {}` |
| `__get(string $name): mixed` | Supports `size`; unknown names throw `Error`. | `$map->size;` |

No constructor is declared; the default object starts with an empty map.
