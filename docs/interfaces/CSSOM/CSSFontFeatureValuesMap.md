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
| `set(string $name, int|array $values): void` | `$name` must be non-empty and structurally valid within the owning stylesheet/rule `maxBytes` (`DEFAULT_MAX_BYTES` when detached); `$values` accepts a non-negative integer or a non-empty non-negative integer list capped at `CSSStyleValue::MAX_LIST_ITEMS`. Enforces owning stylesheet `maxDeclarations` (or `DEFAULT_MAX_DECLARATIONS` when detached) and synchronizes sheet declaration counts. Throws `InvalidArgumentException` / `CSSParserLimitException` on invalid input, negative values, or limit overflow. | `$map->set('alt', [1, 2]);` |
| `has(string $name): bool` | Tests key presence without interpreting the values. | `$map->has('alt');` |
| `delete(string $name): void` | Removes the entry if present and decrements the owning sheet declaration count; missing keys are ignored. | `$map->delete('alt');` |
| `clear(): void` | Removes all entries and decrements the owning sheet declaration count accordingly. | `$map->clear();` |
| `__clone(): void` | Detaches the cloned map from any owning `CSSFontFeatureValuesRule` and stylesheet quota tracking. | `$copy = clone $map;` |
| `count(): int` | Returns entry count. | `count($map);` |
| `getSize(): int` | Returns the same entry count as `count()`. | `$map->getSize();` |
| `keys(): array` | Returns keys in insertion order. | `$map->keys();` |
| `values(): array` | Returns value lists in insertion order. | `$map->values();` |
| `entries(): array` | Returns ordered `[name, values]` pairs. | `$map->entries();` |
| `forEach(callable $callback): void` | Invokes `$callback($values, $name, $this)` for each entry in order. | `$map->forEach(fn ($v, $k) => ...);` |
| `getIterator(): Traversable` | Iterates key => integer-list pairs in insertion order. | `foreach ($map as $name => $values) {}` |
| `__get(string $name): mixed` | Supports `size`; unknown names throw `Error`. | `$map->size;` |
| `__isset(string $name): bool` | Returns `true` only for `size`. | `isset($map->size);` |
| `__set(string $name, mixed $value): void` | Always rejects writes (`Error`), preventing dynamic properties. | `$map->size = 0; // throws` |

No constructor is declared; the default object starts with an empty map.
