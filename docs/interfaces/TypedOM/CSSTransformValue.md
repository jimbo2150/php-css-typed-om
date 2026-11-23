# CSSTransformValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue`  
**Source:** [`src/TypedOM/Values/CSSTransformValue.php`](../../../src/TypedOM/Values/CSSTransformValue.php)  
**Purpose:** Ordered mutable non-empty list of transform components; implements `ArrayAccess`, `Countable`, and `IteratorAggregate`.

## Public API

No public constants or enum cases. Inherits `parse` and `parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api).

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(array $components)`; requires a non-empty list containing only `CSSTransformComponent`, else `InvalidArgumentException`. Example: `$transforms = new CSSTransformValue([new CSSTranslate('2px')]);`. |
| `fromCssText` | `public static function fromCssText(string $cssText, ?CSSParserOptions $options = null): self`; enforces `$options->maxBytes`, validates fragment structure and strips CSS comments, then parses adjacent supported CSS transform functions into components. Empty text/`none`, malformed syntax, unsupported function, and invalid component arguments throw `InvalidArgumentException`. Example: `$transforms = CSSTransformValue::fromCssText('translate(2px) rotate(10deg)');`. |
| `count` / `getLength` | `public function count(): int`; `public function getLength(): int`; return number of components. Example: `$count = count($transforms);`. |
| `offsetExists` | `public function offsetExists(mixed $offset): bool`; true for existing integer indexes. Example: `$exists = isset($transforms[0]);`. |
| `offsetGet` | `public function offsetGet(mixed $offset): ?CSSTransformComponent`; gets a component at an integer index or returns null for missing/non-integer indexes. Example: `$first = $transforms[0];`. |
| `offsetSet` | `public function offsetSet(mixed $offset, mixed $value): void`; requires a transform component; null offset appends, existing integer replaces; invalid type/index throws `InvalidArgumentException`. Example: `$transforms[] = new CSSRotate('20deg');`. |
| `offsetUnset` | `public function offsetUnset(mixed $offset): void`; removes/reindexes an existing element but rejects removal of the final component and invalid indexes with `InvalidArgumentException`. Example: `unset($transforms[0]);`. |
| `getIterator` | `public function getIterator(): Traversable`; returns an `ArrayIterator` over the components. Example: `foreach ($transforms as $component) { /* inspect */ }`. |
| `getComponents` | `public function getComponents(): array`; returns the component list (`list<CSSTransformComponent>`). Example: `$items = $transforms->getComponents();`. |
| `keys` / `values` / `entries` / `forEach` | WebIDL iterable helpers returning integer keys, components, `[index, component]` pairs, or invoking `$callback($component, $index, $this)`. |
| `is2D` | `public function is2D(): bool`; true only if every component reports 2D. Example: `$twoDimensional = $transforms->is2D();`. |
| `toMatrix` | `public function toMatrix(): DOMMatrix`; starts with identity and multiplies each component `DOMMatrix` in list order, preserving `is2D = false` if any component is 3D. Example: `$matrix = $transforms->toMatrix();`. |
| `clone` | `public function clone(): static`; deep-clones each component. Example: `$copy = $transforms->clone();`. |
| `__toString` | `public function __toString(): string`; joins component serialization with spaces. Example: `$css = (string) $transforms;`. |
| `__get` | `public function __get(string $name): mixed`; exposes virtual read-only `length` and `is2D`; other names throw `Error`. Example: `$length = $transforms->length;`. |
