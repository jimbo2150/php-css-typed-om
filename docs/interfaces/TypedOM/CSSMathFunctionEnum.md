# CSSMathFunctionEnum

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathFunctionEnum`  
**Source:** [`src/TypedOM/Values/Numeric/Math/CSSMathFunctionEnum.php`](../../../src/TypedOM/Values/Numeric/Math/CSSMathFunctionEnum.php)  
**Purpose:** Backed string enum listing the 17 CSS Values Level 4 math functions represented by [`CSSMathFunction`](CSSMathFunction.md).

## Cases

`ROUND` (`'round'`), `MOD` (`'mod'`), `REM` (`'rem'`), `SIN` (`'sin'`), `COS` (`'cos'`), `TAN` (`'tan'`), `ASIN` (`'asin'`), `ACOS` (`'acos'`), `ATAN` (`'atan'`), `ATAN2` (`'atan2'`), `POW` (`'pow'`), `SQRT` (`'sqrt'`), `HYPOT` (`'hypot'`), `LOG` (`'log'`), `EXP` (`'exp'`), `ABS` (`'abs'`), `SIGN` (`'sign'`).

## Public methods

| Method | Signature and behavior |
|---|---|
| `minArgs` | `public function minArgs(): int`; minimum operand count (`2` for `mod`, `rem`, `atan2`, `pow`; `1` otherwise). |
| `maxArgs` | `public function maxArgs(): ?int`; maximum operand count (`null` for variadic `hypot`). |
| `isVariadic` | `public function isVariadic(): bool`; returns `true` for `HYPOT`. |
