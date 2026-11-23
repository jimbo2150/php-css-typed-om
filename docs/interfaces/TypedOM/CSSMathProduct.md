# CSSMathProduct

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct`  
**Source:** [`src/TypedOM/Values/Numeric/Math/CSSMathProduct.php`](../../../src/TypedOM/Values/Numeric/Math/CSSMathProduct.php)  
**Purpose:** Represents multiplication and division operands in a CSS math expression.

## Public API

Constants: `operator = 'product'`, `sign = '*'`. Properties `values`, `inner_values`, and inherited `value`, `unit`, `length` are described in [CSSMathValue](CSSMathValue.md#public-properties-and-constants). Inherits these public methods: `__construct(CSSNumericValue|CSSNumericArray|array $values)`, `getOperator(): ?string`, `__get(string $name): mixed`, `__toString(): string`, `clone(): static`, `parse`, `parseAll`, `from`, `type`, `getType`, `getNumericType`, `getNumericCategory`, `to`, `toSum`, `add`, `sub`, `mul`, `div`, `min`, `max`, `equals`, `mergeAdditiveTypes`, `unitType`, and `__set`. See [CSSMathValue](CSSMathValue.md#public-api) and [CSSNumericValue](CSSNumericValue.md#public-api) for inherited signatures, details, and examples.

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|CSSNumericArray|array $values)`; requires at least one operand and eagerly validates multiplicative type compatibility (rejecting conflicting non-null `percentHint` values with `InvalidArgumentException`). Example: `$product = new CSSMathProduct([$a, $b]);`. |
