# CSSUnitValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue`  
**Source:** [`src/TypedOM/Values/Numeric/CSSUnitValue.php`](../../../src/TypedOM/Values/Numeric/CSSUnitValue.php)  
**Purpose:** Numeric magnitude paired with a CSS unit; implements `Stringable` and overrides same-unit addition/subtraction for direct scalar results.

## Public API

### Properties

| Name | Type and access |
|---|---|
| `value` | Public property declared `mixed`, with a setter hook validating finite numeric values and coercing them to `float`; readable and writable. |
| `unit` | Inherited from `TypeableUnitTrait`, public read-only `string` unit symbol. |
| `length` | Inherited public read-only `?int`; normally null because a unit value has no `values` collection. |

No public constants or enum cases. Inherits the `CSSNumericValue` methods `parse`, `from`, `type`, `getType`, `getNumericType`, `getNumericCategory`, `to`, `toSum`, `mul`, `div`, `min`, `max`, `equals`, `mergeAdditiveTypes`, `unitType`, `__get`, `__set`, and inherited `parseAll`; their signatures, behavior, and examples are fully specified in [CSSNumericValue](CSSNumericValue.md#public-api). `add` and `sub` are overridden below. Also inherits `__toString` and `clone` contract but overrides both.

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(string|float|int $value, string|CSSUnitEnum $unit)`; requires a finite numeric magnitude and supported unit, coercing the magnitude to float; invalid magnitude/unit throws `InvalidArgumentException`. Example: `$length = new CSSUnitValue(12, 'px');`. |
| `add` | `public function add(CSSNumericValue|int|float ...$values): CSSNumericValue`; if at least one operand is supplied and all are `CSSUnitValue` with exactly the same unit symbol, returns a new summed `CSSUnitValue`; otherwise delegates to general numeric addition. No operands, nonfinite result, or incompatible general expression throws `InvalidArgumentException`. Example: `$sum = $length->add(new CSSUnitValue(3, 'px'));`. |
| `sub` | `public function sub(CSSNumericValue|int|float ...$values): CSSNumericValue`; if all supplied operands share this exact unit, returns a new difference; otherwise delegates to general numeric subtraction. Example: `$difference = $length->sub(2);`. |
| `__toString` | `public function __toString(): string`; serializes magnitude immediately followed by unit symbol. Example: `$css = (string) $length;`. |
| `clone` | `public function clone(): static`; creates a new unit value preserving magnitude and resolved unit enum. Example: `$copy = $length->clone();`. |
