# CSSMathInvert

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert`  
**Source:** [`src/TypedOM/Values/Numeric/Math/CSSMathInvert.php`](../../../src/TypedOM/Values/Numeric/Math/CSSMathInvert.php)  
**Purpose:** Represents a reciprocal node (`1 / x`) in a CSS numeric expression.

## Public API

Constants: `operator = 'invert'`, `sign = '/'`. Inherits properties and methods from [CSSMathValue](CSSMathValue.md#public-properties-and-constants) and [CSSNumericValue](CSSNumericValue.md#public-api): `values`, `inner_values`, `value`, `unit`, `length`; `getOperator`, `__toString`, `clone`, `parse`, `parseAll`, `from`, `type`, `getType`, `getNumericType`, `getNumericCategory`, `to`, `toSum`, `add`, `sub`, `mul`, `div`, `min`, `max`, `equals`, `mergeAdditiveTypes`, `unitType`, and `__set`. Full signatures, meanings, errors, and examples are documented in those base pages.

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|CSSNumericArray|array $values)`; requires exactly one operand and stores it as the value slot; invalid count throws `InvalidArgumentException`. Example: `$reciprocal = new CSSMathInvert([$number]);`. |
| `__get` | `public function __get(string $name): mixed`; exposes read-only magic `value` as the sole operand; otherwise delegates to CSSMathValue's `operator` and numeric `type` properties. Example: `$operand = $reciprocal->value;`. |
