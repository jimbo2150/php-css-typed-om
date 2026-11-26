# CSSMathConstant

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstant`  
**Source:** [`src/TypedOM/Values/Numeric/Math/CSSMathConstant.php`](../../../src/TypedOM/Values/Numeric/Math/CSSMathConstant.php)  
**Purpose:** Represents CSS Values Level 4 `<calc-keyword>` constants (`e`, `pi`, `infinity`, `-infinity`, `NaN`) while keeping `CSSUnitValue::$value` strictly finite.

## Public API

| Method | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSMathConstantEnum\|string $constant)`; validates and wraps a CSS math constant. |
| `e` / `pi` / `infinity` / `negInfinity` / `nan` | Static factory methods returning the respective `CSSMathConstant` instance. |
| `getConstant` | `public function getConstant(): CSSMathConstantEnum`; returns the constant enum case. |
| `getFloatValue` | `public function getFloatValue(): float`; returns the IEEE-754 float (`M_E`, `M_PI`, `INF`, `-INF`, `NAN`). |
| `isFinite` / `isInfinite` / `isNaN` | `bool` classification helpers. |
