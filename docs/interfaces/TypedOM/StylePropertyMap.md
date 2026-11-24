# StylePropertyMap

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap`  
**Source:** [`src/TypedOM/StylePropertyMap.php`](../../../src/TypedOM/StylePropertyMap.php)  
**Purpose:** Mutable Typed OM property map backed by a `CSSStyleDeclaration`; extends the read-only map API.

## Public API

No public constants or enum cases. This class inherits the constructor, `public int $size` hook, and all read-only methods from [StylePropertyMapReadOnly](StylePropertyMapReadOnly.md#public-api): `__construct(CSSStyleDeclaration $style)`, `get(string $property): ?CSSStyleValue`, `getAll(string $property): array`, `has(string $property): bool`, `count(): int`, `getSize(): int`, `getStyleDeclaration(): CSSStyleDeclaration`, `keys(): array`, `values(): array`, `entries(): array`, `forEach(callable $callback): void`, and `getIterator(): Traversable`. Their behavior and examples are documented on the parent page.

| Method | Signature and behavior |
|---|---|
| `set` | `public function set(string $property, CSSStyleValue|string|array ...$values): void`; enforces owning sheet `maxBytes` on `$property` and values (or `DEFAULT_MAX_BYTES`), flattens one level of variadic arrays (capped at `CSSStyleValue::MAX_LIST_ITEMS` for both flat items and top-level commas on non-custom properties), validates/serializes all supplied values, then sets the property once; empty input (except a single empty `''` or `CSSUnparsedValue([])` on custom `--*` properties), invalid items, or invalid property values throw `InvalidArgumentException` / `CSSParserLimitException`. String inputs are parsed against the property; `CSSStyleValue` inputs are serialized as given. Example: `$map->set('width', '12px');`. |
| `append` | `public function append(string $property, CSSStyleValue|string ...$values): void`; enforces owning sheet `maxBytes` on `$property` and values, requires at least one value, enforces `CSSStyleValue::MAX_LIST_ITEMS`, validates each value, serializes them comma-space separated, then appends to an existing nonempty property with `, `; invalid or over-limit input throws `InvalidArgumentException` / `CSSParserLimitException`. Example: `$map->append('box-shadow', '1px 2px', '3px 4px');`. |
| `delete` | `public function delete(string $property): void`; enforces owning sheet `maxBytes` on `$property` before normalization, then removes the named property through the backing declaration. Example: `$map->delete('color');`. |
| `clear` | `public function clear(): void`; clears all declarations through the backing declaration. Example: `$map->clear();`. |
