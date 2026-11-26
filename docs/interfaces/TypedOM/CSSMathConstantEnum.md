# CSSMathConstantEnum

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstantEnum`  
**Source:** [`src/TypedOM/Values/Numeric/Math/CSSMathConstantEnum.php`](../../../src/TypedOM/Values/Numeric/Math/CSSMathConstantEnum.php)  
**Purpose:** Backed string enum for CSS Values Level 4 `<calc-keyword>` math constants.

## Cases

`E` (`'e'`), `PI` (`'pi'`), `INFINITY` (`'infinity'`), `NEG_INFINITY` (`'-infinity'`), `NAN` (`'NaN'`).

## Public methods

| Method | Signature and behavior |
|---|---|
| `tryFromKeyword` | `public static function tryFromKeyword(string $keyword): ?self`; resolves a `<calc-keyword>` ASCII case-insensitively. |
| `toFloat` | `public function toFloat(): float`; returns `M_E`, `M_PI`, `INF`, `-INF`, or `NAN`. |
