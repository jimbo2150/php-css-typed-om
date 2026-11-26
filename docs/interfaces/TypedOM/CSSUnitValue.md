# CSSUnitValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue`  
**Source:** [`src/TypedOM/Values/Numeric/CSSUnitValue.php`](../../../src/TypedOM/Values/Numeric/CSSUnitValue.php)  
**Purpose:** Numeric magnitude paired with a CSS unit; implements `Stringable` and overrides same-unit addition/subtraction for direct scalar results.

## Public API

### Properties

| Name | Type and access |
|---|---|
| `value` | Public property declared `mixed`, with a setter hook validating finite numeric values and coercing them to `float`; readable and writable. |
| `unit` | Public read-only `string` canonical Typed OM unit name (`'number'`, `'percent'`, `'px'`, `'x'`, etc.). |

No public constants or enum cases. Inherits the `CSSNumericValue` methods `parse`, `from`, `type`, `getType`, `getNumericType`, `getNumericCategory`, `to`, `toSum`, `mul`, `div`, `min`, `max`, `equals`, `mergeAdditiveTypes`, `__get`, `__set`, and `parseAll`; their signatures, behavior, and examples are fully specified in [CSSNumericValue](CSSNumericValue.md#public-api). `add`, `sub`, `__toString`, and `clone` are overridden below, and `unitType()` returns the unit category name.

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(string|float|int $value, string|CSSUnitEnum $unit)`; requires a finite numeric magnitude and supported unit, coercing the magnitude to float; invalid magnitude/unit throws `InvalidArgumentException`. Example: `$length = new CSSUnitValue(12, 'px');`. |
| `unitType` | `public function unitType(): ?string`; returns the unit's category name (`'length'`, `'angle'`, etc.) or `null` for unitless `'number'`; throws `LogicException` if no unit enum is set. Example: `$dimension = $length->unitType();`. |
| `add` | `public function add(CSSNumericValue|int|float ...$values): CSSNumericValue`; if at least one operand is supplied (and operand count is within `CSSNumericValue::MAX_MATH_NODES`) and all are `CSSUnitValue` with exactly the same unit symbol, returns a new summed `CSSUnitValue`; otherwise delegates to general numeric addition. No operands, nonfinite result, or incompatible general expression throws `InvalidArgumentException` (or `CSSParserLimitException`). Example: `$sum = $length->add(new CSSUnitValue(3, 'px'));`. |
| `sub` | `public function sub(CSSNumericValue|int|float ...$values): CSSNumericValue`; if all supplied operands share this exact unit, returns a new difference; otherwise delegates to general numeric subtraction. Example: `$difference = $length->sub(2);`. |
| `__toString` | `public function __toString(): string`; serializes magnitude immediately followed by unit symbol. Example: `$css = (string) $length;`. |
| `clone` | `public function clone(): static`; creates a new unit value preserving magnitude and resolved unit enum. Example: `$copy = $length->clone();`. |
