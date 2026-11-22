# CSSNumericArray

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray`  
**Source:** [`src/TypedOM/Values/Numeric/CSSNumericArray.php`](../../../src/TypedOM/Values/Numeric/CSSNumericArray.php)  
**Purpose:** Standalone read-only ordered numeric-value collection; implements `ArrayAccess`, `Countable`, `IteratorAggregate`, and `Stringable`.

## Public API

### Properties

`values` is a public `list<CSSNumericValue>` property (protected-set through `MultiValueArrayTrait`); `length` is the public read-only `?int` count from `LengthTrait`.

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|CSSNumericArray|array $value)`; accepts a single numeric value, another array, or a list of `CSSNumericValue` objects. |
| `count` | `public function count(): int`; returns item count. |
| `item` | `public function item(int $index): ?CSSNumericValue`; returns item at index or null. |
| `keys` / `values` / `entries` | Return `list<int>`, `list<CSSNumericValue>`, and `list<array{int, CSSNumericValue}>`. |
| `forEach` | `public function forEach(callable $callback): void`; invokes `$callback($value, $index, $this)`. |
| `offsetExists` / `offsetGet` | Read-only integer index access; `offsetSet` and `offsetUnset` throw `InvalidArgumentException`. |
| `getIterator` | Returns an `ArrayIterator` over stored numeric values. |
| `__get` / `__set` | Throw `Error` on undefined or read-only properties. |
| `__toString` | Comma-separated string serialization of items. |
| `clone` | Deep-clones every contained numeric value into a new `CSSNumericArray`. |
