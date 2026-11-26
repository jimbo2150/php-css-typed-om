# `CSSHWB`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSHWB`
- **Source:** [`src/TypedOM/Values/Color/CSSHWB.php`](../../../../src/TypedOM/Values/Color/CSSHWB.php)
- **Inheritance:** [`CSSColorValue`](CSSColorValue.md)
- **Purpose:** Represents a CSS `hwb(...)` color value (`h` angle, `w` whiteness percentage, `b` blackness percentage, `alpha` percentage).

## Properties & Methods

- Channels (`CSSNumericValue|CSSKeywordValue`): property hooks `$h`, `$w`, `$b`, `$alpha` (cloning returned `CSSKeywordValue` instances) and methods `getH()`/`setH()`, `getW()`/`setW()`, `getB()`/`setB()`, `getAlpha()`/`setAlpha()`.
- `__construct($h, $w, $b, $alpha = 1.0)`, `toHWB(bool $gamutMap = false): CSSHWB`, `clone(): static`, `__toString(): string`.
