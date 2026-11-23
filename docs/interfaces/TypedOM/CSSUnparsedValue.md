# CSSUnparsedValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue`  
**Source:** [`src/TypedOM/Values/CSSUnparsedValue.php`](../../../src/TypedOM/Values/CSSUnparsedValue.php)  
**Purpose:** Preserves CSS text as an ordered list of string fragments and parsed custom-property references; implements `ArrayAccess`, `Countable`, and `IteratorAggregate`.

## Public API

No public constants or enum cases. It inherits `parse(string $propertyOrCssText, ?string $cssText = null, ?CSSParserOptions $options = null): CSSStyleValue` and `parseAll(string $property, string $cssText, ?CSSParserOptions $options = null): array` from [CSSStyleValue](CSSStyleValue.md#public-api); the base page documents parameters, behavior, exceptions, and examples.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(array|string $value, ?CSSParserOptions $options = null, int $depth = 0)`; string input is structurally validated (`assertValidFragment` + `maxBytes`/`maxDepth`) and split around recognized valid `var(--name[, fallback])` references; array input validates fragments via the shared pipeline, clones inserted `CSSVariableReferenceValue` objects (preventing shared-DAG aliasing/cycles), and rejects unbalanced delimiters/quote-or-URL-split references with `InvalidArgumentException`. Example: `$value = new CSSUnparsedValue('var(--theme, blue) solid');`. |
| `fromCssText` | `public static function fromCssText(string $cssText, ?CSSParserOptions $options = null, int $depth = 0): self`; constructs by parsing fragments from CSS text under `$options` limits. Example: `$value = CSSUnparsedValue::fromCssText('var(--x)');`. |
| `getValues` | `public function getValues(): array`; returns the ordered fragment list (`list<string|CSSVariableReferenceValue>`). Example: `$parts = $value->getValues();`. |
| `keys` / `values` / `entries` / `forEach` | WebIDL iterable helpers returning integer keys, fragments, `[index, fragment]` pairs, or invoking `$callback($fragment, $index, $this)`. |
| `count` | `public function count(): int`; returns fragment count. Example: `$count = count($value);`. |
| `offsetExists` | `public function offsetExists(mixed $offset): bool`; true only for an integer index present in the list. Example: `$present = isset($value[0]);`. |
| `offsetGet` | `public function offsetGet(mixed $offset): string|CSSVariableReferenceValue|null`; returns an integer-indexed fragment or `null` for another/missing index. Example: `$part = $value[0];`. |
| `offsetSet` | `public function offsetSet(mixed $offset, mixed $value): void`; value must be a string or variable-reference object (cloned on insert); `null` offset appends, an existing integer replaces; revalidates the resulting fragment list so quotes/comments/`url(`/delimiters cannot break out; invalid type/index throws `InvalidArgumentException`. Example: `$value[] = ' solid';`. |
| `offsetUnset` | `public function offsetUnset(mixed $offset): void`; removes an existing integer index, revalidates the remaining fragments identically, and rejects mutations that would expose unbalanced syntax or top-level `;`/`{`/`}` with `InvalidArgumentException`. Example: `unset($value[0]);`. |
| `getIterator` | `public function getIterator(): Traversable`; returns an `ArrayIterator` over fragments. Example: `foreach ($value as $fragment) { /* consume fragment */ }`. |
| `clone` | `public function clone(): static`; clones each variable-reference fragment and copies strings. Example: `$copy = $value->clone();`. |
| `__toString` | `public function __toString(): string`; iteratively concatenates fragments (handling arbitrary nesting without PHP call-stack recursion limits) and enforces the instance's byte limit (`maxBytes ?? CSSParserOptions::DEFAULT_MAX_BYTES`; unlimited when constructed with `CSSParserOptions::unlimited()`), throwing `CSSParserLimitException` if exceeded. Example: `$css = (string) $value;`. |
| `$length` (property hook) | `public int $length { get; }` returning fragment count (`count($this->values)`). Undeclared property accesses via `__get`/`__set` throw `Error`. Example: `$length = $value->length;`. |

## Fragment parsing note

Text is preserved even when a `var()` expression is malformed or its variable name is not a custom property; only recognized valid references become `CSSVariableReferenceValue` objects. This scanner does not otherwise parse CSS grammar.
