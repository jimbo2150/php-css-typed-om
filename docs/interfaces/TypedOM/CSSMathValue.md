# CSSMathValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathValue`  
**Source:** [`src/TypedOM/Values/Numeric/Math/CSSMathValue.php`](../../../src/TypedOM/Values/Numeric/Math/CSSMathValue.php)  
**Purpose:** Abstract base for CSS math expression nodes (`calc`, `min`, `max`, `clamp`, product, sum, negate, and invert).

## Public properties and constants

`values` is a public `CSSNumericArray` property with protected write access; `inner_values` is a public `array<CSSNumericValue>` view with private write access from `MultiValueTrait`. `value`, `unit`, and `length` are inherited from `CSSNumericValue` and its traits (see [CSSNumericValue properties](CSSNumericValue.md#public-properties-and-constants)). No constants are declared on this abstract class; concrete math subclasses define `operator` and sometimes `sign`.

## Public API

The full inherited numeric API is available: `parse`, `from`, `type`, `getType`, `getNumericType`, `getNumericCategory`, `to`, `toSum`, `add`, `sub`, `mul`, `div`, `min`, `max`, `equals`, `mergeAdditiveTypes`, `unitType`, `__get`, and `__set`, with exact signatures, parameter meanings, errors, and examples in [CSSNumericValue](CSSNumericValue.md#public-api). It also inherits `parseAll` from `CSSStyleValue`. Its numeric `__toString` and `clone` implementations are overridden here.

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|CSSNumericArray|array $values)`; stores one numeric value, an existing numeric array, or constructs a numeric array from the input list. Example: `$sum = new CSSMathSum([$a, $b]);`. |
| `getOperator` | `public function getOperator(): ?string`; returns `static::operator`, the concrete subtype's operator constant. Example: `$operator = $expression->getOperator();`. |
| `__get` | `public function __get(string $name): mixed`; returns virtual `operator`; delegates other names to `CSSNumericValue` (which exposes `type`); unknown names throw `Error`. Example: `$operator = $expression->operator;`. |
| `__toString` | `public function __toString(): string`; serializes the math expression using CSS syntax and precedence (including `calc(...)` wrapping for sum/product roots). Example: `$css = (string) $expression;`. |
| `clone` | `public function clone(): static`; deep-clones numeric operands and reconstructs the same runtime math class. Example: `$copy = $expression->clone();`. |
