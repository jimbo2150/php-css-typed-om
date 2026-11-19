# StylePropertyMapReadOnly

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMapReadOnly`  
**Source:** [`src/TypedOM/StylePropertyMapReadOnly.php`](../../../src/TypedOM/StylePropertyMapReadOnly.php)  
**Purpose:** Read-only Typed OM view over a `CSSStyleDeclaration`; implements `Countable` and `IteratorAggregate`.

## Public API

No public constants or enum cases. The backing declaration is held in a protected readonly constructor-promoted property.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSStyleDeclaration $style)`; stores the declaration view. Example: `$map = new StylePropertyMapReadOnly($declaration);`. |
| `get` | `public function get(string $property): ?CSSStyleValue`; returns the first parsed value or null if the property is empty. Example: `$value = $map->get('width');`. |
| `getAll` | `public function getAll(string $property): array`; parses and returns all comma-separated values as `list<CSSStyleValue>`, or an empty list for an empty property value. Example: `$values = $map->getAll('box-shadow');`. |
| `has` | `public function has(string $property): bool`; true when the declaration returns a nonempty value string. Example: `$present = $map->has('color');`. |
| `count` | `public function count(): int`; returns the declaration's property count. Example: `$count = count($map);`. |
| `getSize` | `public function getSize(): int`; alias for `count($this)`. Example: `$size = $map->getSize();`. |
| `getStyleDeclaration` | `public function getStyleDeclaration(): CSSStyleDeclaration`; returns the backing declaration object. Example: `$declaration = $map->getStyleDeclaration();`. |
| `keys` | `public function keys(): array`; returns property names in declaration iteration order (`list<string>`). Example: `$names = $map->keys();`. |
| `values` | `public function values(): array`; returns one `list<CSSStyleValue>` per property, in declaration order. Example: `$allValues = $map->values();`. |
| `entries` | `public function entries(): array`; returns pairs `[propertyName, list<CSSStyleValue>]` in declaration order. Example: `$entries = $map->entries();`. |
| `forEach` | `public function forEach(callable $callback): void`; invokes `$callback($values, $property, $this)` for each declaration entry. Callback exceptions propagate. Example: `$map->forEach(static fn ($values, $name, $map) => print($name));`. |
| `getIterator` | `public function getIterator(): Traversable`; returns an `ArrayIterator` keyed by property name with parsed value lists. Example: `foreach ($map as $property => $values) { /* inspect */ }`. |
| `__get` | `public function __get(string $name): mixed`; exposes magic read-only `size`; any other name throws `Error`. Example: `$size = $map->size;`. |
