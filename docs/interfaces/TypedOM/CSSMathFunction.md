# CSSMathFunction

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathFunction`  
**Source:** [`src/TypedOM/Values/Numeric/Math/CSSMathFunction.php`](../../../src/TypedOM/Values/Numeric/Math/CSSMathFunction.php)  
**Purpose:** Represents CSS Values Level 4 math functions (`round`, `mod`, `rem`, `sin`, `cos`, `tan`, `asin`, `acos`, `atan`, `atan2`, `pow`, `sqrt`, `hypot`, `log`, `exp`, `abs`, `sign`) per CSS Typed OM Level 2 / `cssomnom`.

## Public properties

| Name | Type and access |
|---|---|
| `function` | `public protected(set) CSSMathFunctionEnum` |
| `strategy` | `public protected(set) ?CSSRoundingStrategy` (non-null only for `round()`) |
| `operator` | Virtual read-only `string` returning `$function->value` |

## Public API

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(string\|CSSMathFunctionEnum $operator, CSSNumericValue\|CSSNumericArray\|array $values, CSSRoundingStrategy\|string\|null $strategy = null)`; validates function name, optional `round()` strategy, operand count, and CSS Values 4 §10.9 argument types. |
| `getOperator` | `public function getOperator(): string`; returns the CSS function name (`'sin'`, `'round'`, etc.). |
| `getFunction` | `public function getFunction(): CSSMathFunctionEnum`; returns the function enum case. |
| `getStrategy` | `public function getStrategy(): ?CSSRoundingStrategy`; returns the rounding strategy for `round()`, or `null` for other functions. |
