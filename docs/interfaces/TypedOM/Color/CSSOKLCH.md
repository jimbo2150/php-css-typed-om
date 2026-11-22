# `CSSOKLCH`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSOKLCH`
- **Source:** [`src/TypedOM/Values/Color/CSSOKLCH.php`](../../../../src/TypedOM/Values/Color/CSSOKLCH.php)
- **Inheritance:** [`CSSColorValue`](CSSColorValue.md)
- **Purpose:** Represents a CSS `oklch(...)` color value (`l` percentage where bare `0..1` maps to `0%..100%`, `c` chroma number/percentage, `h` angle, `alpha` percentage).

## Properties & Methods

- Channels (`CSSNumericValue|CSSKeywordValue`): `l`, `c`, `h`, `alpha` (`getL()`/`setL()`, `getC()`/`setC()`, `getH()`/`setH()`, `getAlpha()`/`setAlpha()`, plus magic `__get`/`__set`).
- `__construct($l, $c, $h, $alpha = 1.0)`, `toOKLCH(): CSSOKLCH`, `toOKLab(): CSSOKLab`, `clone(): static`, `__toString(): string`.
