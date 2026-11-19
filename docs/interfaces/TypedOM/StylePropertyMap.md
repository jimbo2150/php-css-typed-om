# StylePropertyMap

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap`  
**Source:** [`src/TypedOM/StylePropertyMap.php`](../../../src/TypedOM/StylePropertyMap.php)  
**Purpose:** Mutable Typed OM property map backed by a `CSSStyleDeclaration`; extends the read-only map API.

## Public API

No public constants or enum cases. This class inherits the constructor and all read-only methods from [StylePropertyMapReadOnly](StylePropertyMapReadOnly.md#public-api): `__construct(CSSStyleDeclaration $style)`, `get(string $property): ?CSSStyleValue`, `getAll(string $property): array`, `has(string $property): bool`, `count(): int`, `getSize(): int`, `getStyleDeclaration(): CSSStyleDeclaration`, `keys(): array`, `values(): array`, `entries(): array`, `forEach(callable $callback): void`, `getIterator(): Traversable`, and `__get(string $name): mixed` (magic `size`). Their behavior and examples are documented on the parent page.

| Method | Signature and behavior |
|---|---|
| `set` | `public function set(string $property, CSSStyleValue|string|array ...$values): void`; flattens one level of variadic arrays, validates/serializes all supplied values, then sets the property once; empty input, invalid items, or invalid property values throw `InvalidArgumentException`. String inputs are parsed against the property; `CSSStyleValue` inputs are serialized as given. Example: `$map->set('width', '12px');`. |
| `append` | `public function append(string $property, CSSStyleValue|string ...$values): void`; requires at least one value, validates each, serializes them comma-space separated, then appends to an existing nonempty property with `, `; invalid input throws `InvalidArgumentException`. Example: `$map->append('box-shadow', '1px 2px', '3px 4px');`. |
| `delete` | `public function delete(string $property): void`; removes the named property through the backing declaration. Example: `$map->delete('color');`. |
| `clear` | `public function clear(): void`; clears all declarations through the backing declaration. Example: `$map->clear();`. |
