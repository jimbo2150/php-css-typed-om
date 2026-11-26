# CSSMathSum

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum`  
**Source:** [`src/TypedOM/Values/Numeric/Math/CSSMathSum.php`](../../../src/TypedOM/Values/Numeric/Math/CSSMathSum.php)  
**Purpose:** Represents additive numeric operands (`+` and negated terms for subtraction) serialized as a CSS `calc()` expression.

## Public API

Constants: `operator = 'sum'`, `sign = '+'`. Properties `values`, `inner_values`, and `length` are described in [CSSMathValue](CSSMathValue.md#public-properties-and-constants). This type inherits all public APIs from CSSMathValue and CSSNumericValue: `__construct(CSSNumericValue|CSSNumericArray|array $values)`, `getOperator(): ?string`, `__get(string $name): mixed`, `__toString(): string`, `clone(): static`, `parse`, `parseAll`, `from`, `type`, `getType`, `getNumericType`, `getNumericCategory`, `to`, `toSum`, `add`, `sub`, `mul`, `div`, `min`, `max`, `equals`, `mergeAdditiveTypes`, and `__set`. Signatures/parameter descriptions and examples for the inherited numeric methods are in [CSSNumericValue](CSSNumericValue.md#public-api); math-base details are in [CSSMathValue](CSSMathValue.md#public-api).

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|CSSNumericArray|array $values)`; requires at least one operand and validates additive dimensional compatibility; invalid/empty/incompatible operands throw `InvalidArgumentException`. Example: `$sum = new CSSMathSum([$a, $b]);`. |
