# CSSMathClamp

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathClamp`  
**Source:** [`src/TypedOM/Values/Numeric/Math/CSSMathClamp.php`](../../../src/TypedOM/Values/Numeric/Math/CSSMathClamp.php)  
**Purpose:** Represents CSS `clamp(lower, value, upper)` with three additive-compatible operands.

## Public API

Constant: `operator = 'clamp'`. Declares `public protected(set) ?CSSNumericValue $value = null` (storing the middle operand), read-only property hooks `public CSSNumericValue $lower { get; }` and `public CSSNumericValue $upper { get; }`, and inherits `values`, `inner_values`, and `length` from [CSSMathValue](CSSMathValue.md#public-properties-and-constants). Numeric methods `parse`, `parseAll`, `from`, `type`, `getType`, `getNumericType`, `getNumericCategory`, `to`, `toSum`, `add`, `sub`, `mul`, `div`, `min`, `max`, `equals`, `mergeAdditiveTypes`, and `__set` are inherited with their full signatures/examples in [CSSNumericValue](CSSNumericValue.md#public-api). Also inherits `getOperator`, `__toString`, `clone`, `__get`, and `__isset` behavior from [CSSMathValue](CSSMathValue.md#public-api).

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|CSSNumericArray|array $lowerOrValues, ?CSSNumericValue $value = null, ?CSSNumericValue $upper = null)`; accepts three positional numeric values or an array/numeric array containing exactly three; validates additive types and sets the value slot to the middle operand. Throws `InvalidArgumentException` for bad count/incompatible types. Example: `$clamp = new CSSMathClamp($lower, $preferred, $upper);`. |
| `getLower` | `public function getLower(): CSSNumericValue`; returns operand zero. Example: `$minimum = $clamp->getLower();`. |
| `getValue` | `public function getValue(): CSSNumericValue`; returns the middle operand. Example: `$preferred = $clamp->getValue();`. |
| `getUpper` | `public function getUpper(): CSSNumericValue`; returns operand two. Example: `$maximum = $clamp->getUpper();`. |
| `$lower` / `$upper` (hooks) | `public CSSNumericValue $lower { get; }` and `$upper { get; }` returning `getLower()` / `getUpper()`. `$value` is stored cleanly on the inherited `$value` property. Example: `$minimum = $clamp->lower;`. |
