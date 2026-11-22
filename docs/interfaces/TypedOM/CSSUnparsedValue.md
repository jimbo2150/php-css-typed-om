# CSSUnparsedValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue`  
**Source:** [`src/TypedOM/Values/CSSUnparsedValue.php`](../../../src/TypedOM/Values/CSSUnparsedValue.php)  
**Purpose:** Preserves CSS text as an ordered list of string fragments and parsed custom-property references; implements `ArrayAccess`, `Countable`, and `IteratorAggregate`.

## Public API

No public constants or enum cases. It inherits `parse(string $propertyOrCssText, ?string $cssText = null, ?CSSParserOptions $options = null): CSSStyleValue` and `parseAll(string $property, string $cssText, ?CSSParserOptions $options = null): array` from [CSSStyleValue](CSSStyleValue.md#public-api); the base page documents parameters, behavior, exceptions, and examples.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(array|string $value)`; string input is split around recognized valid `var(--name[, fallback])` references; array input must contain only strings or `CSSVariableReferenceValue`, else `InvalidArgumentException`. Example: `$value = new CSSUnparsedValue('var(--theme, blue) solid');`. |
| `fromCssText` | `public static function fromCssText(string $cssText): self`; constructs by parsing fragments from CSS text. Example: `$value = CSSUnparsedValue::fromCssText('var(--x)');`. |
| `getValues` | `public function getValues(): array`; returns the ordered fragment list (`list<string|CSSVariableReferenceValue>`). Example: `$parts = $value->getValues();`. |
| `keys` / `values` / `entries` / `forEach` | WebIDL iterable helpers returning integer keys, fragments, `[index, fragment]` pairs, or invoking `$callback($fragment, $index, $this)`. |
| `count` | `public function count(): int`; returns fragment count. Example: `$count = count($value);`. |
| `offsetExists` | `public function offsetExists(mixed $offset): bool`; true only for an integer index present in the list. Example: `$present = isset($value[0]);`. |
| `offsetGet` | `public function offsetGet(mixed $offset): string|CSSVariableReferenceValue|null`; returns an integer-indexed fragment or `null` for another/missing index. Example: `$part = $value[0];`. |
| `offsetSet` | `public function offsetSet(mixed $offset, mixed $value): void`; value must be a string or variable-reference object; `null` offset appends, an existing integer replaces; invalid type/index throws `InvalidArgumentException`. Example: `$value[] = ' solid';`. |
| `offsetUnset` | `public function offsetUnset(mixed $offset): void`; removes an existing integer index and reindexes following fragments; invalid index throws `InvalidArgumentException`. Example: `unset($value[0]);`. |
| `getIterator` | `public function getIterator(): Traversable`; returns an `ArrayIterator` over fragments. Example: `foreach ($value as $fragment) { /* consume fragment */ }`. |
| `clone` | `public function clone(): static`; clones each variable-reference fragment and copies strings. Example: `$copy = $value->clone();`. |
| `__toString` | `public function __toString(): string`; concatenates fragments without separators. Example: `$css = (string) $value;`. |
| `__get` | `public function __get(string $name): mixed`; exposes virtual read-only `length` as fragment count; unknown properties throw `Error`. Example: `$length = $value->length;`. |

## Fragment parsing note

Text is preserved even when a `var()` expression is malformed or its variable name is not a custom property; only recognized valid references become `CSSVariableReferenceValue` objects. This scanner does not otherwise parse CSS grammar.
