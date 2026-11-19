# CSSMathClamp

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathClamp`  
**Source:** [`src/TypedOM/Values/Numeric/Math/CSSMathClamp.php`](../../../src/TypedOM/Values/Numeric/Math/CSSMathClamp.php)  
**Purpose:** Represents CSS `clamp(lower, value, upper)` with three additive-compatible operands.

## Public API

Constant: `operator = 'clamp'`. Inherits `values`, `inner_values`, and inherited `value`, `unit`, `length` properties from [CSSMathValue](CSSMathValue.md#public-properties-and-constants); in this class `value` is set to the middle operand. Numeric methods `parse`, `parseAll`, `from`, `type`, `getType`, `getNumericType`, `getNumericCategory`, `to`, `toSum`, `add`, `sub`, `mul`, `div`, `min`, `max`, `equals`, `mergeAdditiveTypes`, `unitType`, and `__set` are inherited with their full signatures/examples in [CSSNumericValue](CSSNumericValue.md#public-api). Also inherits `getOperator`, `__toString`, `clone`, and base `__get` behavior from [CSSMathValue](CSSMathValue.md#public-api).

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|CSSNumericArray|array $lowerOrValues, ?CSSNumericValue $value = null, ?CSSNumericValue $upper = null)`; accepts three positional numeric values or an array/numeric array containing exactly three; validates additive types and sets the value slot to the middle operand. Throws `InvalidArgumentException` for bad count/incompatible types. Example: `$clamp = new CSSMathClamp($lower, $preferred, $upper);`. |
| `getLower` | `public function getLower(): CSSNumericValue`; returns operand zero. Example: `$minimum = $clamp->getLower();`. |
| `getValue` | `public function getValue(): CSSNumericValue`; returns the middle operand. Example: `$preferred = $clamp->getValue();`. |
| `getUpper` | `public function getUpper(): CSSNumericValue`; returns operand two. Example: `$maximum = $clamp->getUpper();`. |
| `__get` | `public function __get(string $name): mixed`; exposes magic read-only `lower`, `value`, and `upper`, otherwise delegates to CSSMathValue's `operator`/numeric `type` properties. Example: `$preferred = $clamp->value;`. |
