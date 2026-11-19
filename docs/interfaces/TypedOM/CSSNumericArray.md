# CSSNumericArray

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray`  
**Source:** [`src/TypedOM/Values/Numeric/CSSNumericArray.php`](../../../src/TypedOM/Values/Numeric/CSSNumericArray.php)  
**Purpose:** Read-only ordered numeric-value collection; implements `ArrayAccess`, `Countable`, and `IteratorAggregate` and is also a `CSSNumericValue`.

## Public API

### Properties

`values` is a public `list<CSSNumericValue>` property (protected-set through its property hook); `length` is the inherited public read-only count. No public constants or enum cases.

All inherited numeric methods are available with the same signatures, parameter meanings, errors, and examples documented in [CSSNumericValue](CSSNumericValue.md#public-api): `parse`, `from`, `type`, `getType`, `getNumericType`, `getNumericCategory`, `to`, `toSum`, `add`, `sub`, `mul`, `div`, `min`, `max`, `equals`, `mergeAdditiveTypes`, `unitType`, `__get`, `__set`, `__toString`, and `clone`. The static `parseAll` method is also inherited from `CSSStyleValue` as described there. `clone` is overridden here for deep copying.

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|CSSNumericArray|array $value)`; from `MultiValueArrayTrait`, accepts a single numeric value, another array, or a numeric-value array; every array entry must be `CSSNumericValue` or `InvalidArgumentException` is thrown. Example: `$items = new CSSNumericArray([$a, $b]);`. |
| `count` | `public function count(): int`; returns item count. Example: `$count = count($items);`. |
| `item` | `public function item(int $index): ?CSSNumericValue`; returns item at index or null. Example: `$first = $items->item(0);`. |
| `offsetExists` | `public function offsetExists(mixed $offset): bool`; true only for an existing integer index. Example: `$exists = isset($items[0]);`. |
| `offsetGet` | `public function offsetGet(mixed $offset): ?CSSNumericValue`; gets an integer-indexed item or returns null for missing/non-integer keys. Example: `$first = $items[0];`. |
| `offsetSet` | `public function offsetSet(mixed $offset, mixed $value): void`; always throws `InvalidArgumentException` because the array is read-only. Example: `$items[0] = $a; // InvalidArgumentException`. |
| `offsetUnset` | `public function offsetUnset(mixed $offset): void`; always throws `InvalidArgumentException` because the array is read-only. Example: `unset($items[0]); // InvalidArgumentException`. |
| `getIterator` | `public function getIterator(): Traversable`; returns an `ArrayIterator` of the stored values. Example: `foreach ($items as $item) { /* use numeric item */ }`. |
| `clone` | `public function clone(): static`; deep-clones every contained numeric value into a new array. Example: `$copy = $items->clone();`. |
