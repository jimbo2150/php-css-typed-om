# StylePropertyMapReadOnly

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMapReadOnly`  
**Source:** [`src/TypedOM/StylePropertyMapReadOnly.php`](../../../src/TypedOM/StylePropertyMapReadOnly.php)  
**Purpose:** Read-only Typed OM view over a `CSSStyleDeclaration`; implements `Countable` and `IteratorAggregate`.

## Public API

No public constants or enum cases. The backing declaration is held in a protected readonly constructor-promoted property.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSStyleDeclaration $style)`; stores the declaration view. Example: `$map = new StylePropertyMapReadOnly($declaration);`. |
| `get` | `public function get(string $property): ?CSSStyleValue`; enforces owning sheet `maxBytes` on `$property` and `CSSStyleValue::MAX_LIST_ITEMS` top-level comma segments, returning the first parsed value, `new CSSUnparsedValue([], $options)` if stored with an empty custom-property value, or `null` when absent. Example: `$value = $map->get('width');`. |
| `getAll` | `public function getAll(string $property): array`; parses and returns all comma-separated values as `list<CSSStyleValue>` (or `[new CSSUnparsedValue([], $options)]` when stored declaration value is empty, or `[]` when absent or when all comma-separated segments are empty/comments). Enforces owning stylesheet `maxBytes` on `$property`. Example: `$values = $map->getAll('box-shadow');`. |
| `has` | `public function has(string $property): bool`; enforces owning sheet `maxBytes` on `$property` and returns `true` when the declaration exists in the style map. Example: `$present = $map->has('color');`. |
| `count` | `public function count(): int`; returns the declaration's property count. Example: `$count = count($map);`. |
| `getSize` | `public function getSize(): int`; alias for `count($this)`. Example: `$size = $map->getSize();`. |
| `getStyleDeclaration` | `public function getStyleDeclaration(): CSSStyleDeclaration`; returns the backing declaration object. Example: `$declaration = $map->getStyleDeclaration();`. |
| `keys` | `public function keys(): array`; returns property names in declaration iteration order (`list<string>`). Example: `$names = $map->keys();`. |
| `values` | `public function values(): array`; returns one `list<CSSStyleValue>` per property, in declaration order. Example: `$allValues = $map->values();`. |
| `entries` | `public function entries(): array`; returns pairs `[propertyName, list<CSSStyleValue>]` in declaration order. Example: `$entries = $map->entries();`. |
| `forEach` | `public function forEach(callable $callback): void`; invokes `$callback($values, $property, $this)` for each declaration entry. Callback exceptions propagate. Example: `$map->forEach(static fn ($values, $name, $map) => print($name));`. |
| `getIterator` | `public function getIterator(): Traversable`; yields `property => list<CSSStyleValue>` entries lazily via a generator. Example: `foreach ($map as $property => $values) { /* inspect */ }`. |
| `__isset` / `$size` | `__isset(string $name): bool` always returns `false`; `public int $size { get; }` returns declaration count (`$this->style->getLength()`). Undeclared property accesses via `__get`/`__set` throw `Error`. Example: `$size = $map->size;`. |
