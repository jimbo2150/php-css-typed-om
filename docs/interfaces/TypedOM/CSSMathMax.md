# CSSMathMax

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax`  
**Source:** [`src/TypedOM/Values/Numeric/Math/CSSMathMax.php`](../../../src/TypedOM/Values/Numeric/Math/CSSMathMax.php)  
**Purpose:** Represents CSS `max()` across one or more additive-compatible numeric operands.

## Public API

Constant: `operator = 'max'`. Inherits `values`, `inner_values`, and `value`, `unit`, `length` properties as documented in [CSSMathValue](CSSMathValue.md#public-properties-and-constants). Inherits `__construct(CSSNumericValue|CSSNumericArray|array $values)`, `getOperator(): ?string`, `__get(string $name): mixed`, `__toString(): string`, `clone(): static`, `parse`, `parseAll`, `from`, `type`, `getType`, `getNumericType`, `getNumericCategory`, `to`, `toSum`, `add`, `sub`, `mul`, `div`, `min`, `max`, `equals`, `mergeAdditiveTypes`, `unitType`, and `__set`; see [CSSMathValue](CSSMathValue.md#public-api) and [CSSNumericValue](CSSNumericValue.md#public-api) for full inherited signatures/examples.

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|CSSNumericArray|array $values)`; requires at least one operand and validates additive compatibility; invalid/empty/incompatible input throws `InvalidArgumentException`. Example: `$maximum = new CSSMathMax([$a, $b]);`. |
