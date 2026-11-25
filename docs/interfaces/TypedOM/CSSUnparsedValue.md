# CSSUnparsedValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue`  
**Source:** [`src/TypedOM/Values/CSSUnparsedValue.php`](../../../src/TypedOM/Values/CSSUnparsedValue.php)  
**Purpose:** Preserves CSS text as an ordered list of string fragments and parsed custom-property references; implements `ArrayAccess`, `Countable`, and `IteratorAggregate`.

## Public API

Declares `public const int MAX_UNPARSED_DEPTH = 512;`. It inherits `parse(string $propertyOrCssText, ?string $cssText = null, ?CSSParserOptions $options = null): CSSStyleValue` and `parseAll(string $property, string $cssText, ?CSSParserOptions $options = null): array` from [CSSStyleValue](CSSStyleValue.md#public-api); the base page documents parameters, behavior, exceptions, and examples.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(array|string $value, ?CSSParserOptions $options = null, int $depth = 0)`; rejects negative or `PHP_INT_MAX` `$depth`; string input is structurally validated (`assertValidFragment` + `maxBytes`/`effectiveMaxDepth`) and split around recognized valid `var(--name[, fallback])` references (including CSS-escaped `--` prefixes such as `var(\2d\2d a)`, while preserving escaped trailing whitespace on fallbacks such as `var(--a, foo\ )`); array input pre-checks cumulative byte budget before scanning/cloning fragments (up to `CSSStyleValue::MAX_LIST_ITEMS`), clones inserted `CSSVariableReferenceValue` objects and clamps both `maxBytes` and `maxDepth` iteratively (preventing shared-DAG aliasing/cycles/budget widening) while linking internal GC-safe parent references, and rejects unbalanced delimiters/quote-or-URL-split references with `InvalidArgumentException` (or `CSSParserLimitException` on budget overflow). Example: `$value = new CSSUnparsedValue('var(--theme, blue) solid');`. |
| `fromCssText` | `public static function fromCssText(string $cssText, ?CSSParserOptions $options = null, int $depth = 0): self`; constructs by parsing fragments from CSS text under `$options` limits. Example: `$value = CSSUnparsedValue::fromCssText('var(--x)');`. |
| `getValues` | `public function getValues(): array`; returns the ordered fragment list (`list<string|CSSVariableReferenceValue>`). Example: `$parts = $value->getValues();`. |
| `keys` / `values` / `entries` / `forEach` | WebIDL iterable helpers returning integer keys, fragments, `[index, fragment]` pairs, or invoking `$callback($fragment, $index, $this)`. |
| `count` | `public function count(): int`; returns fragment count. Example: `$count = count($value);`. |
| `offsetExists` | `public function offsetExists(mixed $offset): bool`; true only for an integer index present in the list. Example: `$present = isset($value[0]);`. |
| `offsetGet` | `public function offsetGet(mixed $offset): string|CSSVariableReferenceValue|null`; returns an integer-indexed fragment or `null` for another/missing index. Example: `$part = $value[0];`. |
| `offsetSet` | `public function offsetSet(mixed $offset, mixed $value): void`; value must be a string or variable-reference object (pre-checked against `$this->maxBytes` and per-ancestor remaining `maxDepth`, cloned on insert, clamped to ancestor `maxBytes`/`maxDepth`, and re-linked to this container); `null` offset appends (capped at `CSSStyleValue::MAX_LIST_ITEMS`), an existing integer replaces; transactionally validates every ancestor's own `maxBytes` (in one O(N) pass) and resulting fragment structure so quotes/comments/`url(`/delimiters cannot break out; invalid type/index throws `InvalidArgumentException`, budget overflow throws `CSSParserLimitException`. Example: `$value[] = ' solid';`. |
| `offsetUnset` | `public function offsetUnset(mixed $offset): void`; removes an existing integer index, validates remaining fragments and every ancestor's own `maxBytes` (leaving state unchanged on failure), and clears parent linkage on removed items. Example: `unset($value[0]);`. |
| `getIterator` | `public function getIterator(): Traversable`; returns an `ArrayIterator` over fragments. Example: `foreach ($value as $fragment) { /* consume fragment */ }`. |
| `__clone` / `clone` | `public function __clone(): void`; `public function clone(): static`; iteratively deep-clones each variable-reference fragment, propagates `maxBytes` and `maxDepth`, re-links internal parent references cleanly, and copies strings. Example: `$copy = $value->clone();`. |
| `__toString` | `public function __toString(): string`; iteratively concatenates fragments (handling arbitrary nesting without PHP call-stack recursion limits) and enforces the instance's `maxBytes` (`CSSParserOptions::DEFAULT_MAX_BYTES` by default, `null`/unlimited when constructed with `CSSParserOptions::unlimited()`), throwing `CSSParserLimitException` if exceeded. Example: `$css = (string) $value;`. |
| `__isset` / `$length` | `__isset(string $name): bool` always returns `false`; `public int $length { get; }` returns fragment count (`count($this->values)`). Undeclared property accesses via `__get`/`__set` throw `Error`. Example: `$length = $value->length;`. |

## Fragment parsing note

Text is preserved even when a `var()` expression is malformed or its variable name is not a custom property; only recognized valid references become `CSSVariableReferenceValue` objects. This scanner does not otherwise parse CSS grammar.
