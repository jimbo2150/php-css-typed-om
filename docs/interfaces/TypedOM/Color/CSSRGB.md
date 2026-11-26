# `CSSRGB`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSRGB`
- **Source:** [`src/TypedOM/Values/Color/CSSRGB.php`](../../../../src/TypedOM/Values/Color/CSSRGB.php)
- **Inheritance:** [`CSSColorValue`](CSSColorValue.md)
- **Purpose:** Represents a CSS `rgb(...)` sRGB color using the W3C CSS Color 5 Typed OM percentage scale (`0%..100%`; bare constructor/setter numbers `0..1` map to `0%..100%`).

## Properties & Methods

- Channels (`CSSNumericValue|CSSKeywordValue`): property hooks `$r`, `$g`, `$b`, `$alpha` (cloning returned `CSSKeywordValue` instances) and methods `getR()`/`setR()`, `getG()`/`setG()`, `getB()`/`setB()`, `getAlpha()`/`setAlpha()`.
- `__construct($r, $g, $b, $alpha = 1.0)`, `toRGB(bool $gamutMap = false): CSSRGB`, `clone(): static`, `__toString(): string` (serializes `rgb(r g b)` or `rgb(r g b / alpha)`).
