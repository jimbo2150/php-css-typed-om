# `CSSLCH`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSLCH`
- **Source:** [`src/TypedOM/Values/Color/CSSLCH.php`](../../../../src/TypedOM/Values/Color/CSSLCH.php)
- **Inheritance:** [`CSSColorValue`](CSSColorValue.md)
- **Purpose:** Represents a CSS CIE `lch(...)` color value (`l` percentage, `c` chroma number/percentage, `h` angle, `alpha` percentage).

## Properties & Methods

- Channels (`CSSNumericValue|CSSKeywordValue`): property hooks `$l`, `$c`, `$h`, `$alpha` (cloning returned `CSSKeywordValue` instances) and methods `getL()`/`setL()`, `getC()`/`setC()`, `getH()`/`setH()`, `getAlpha()`/`setAlpha()`.
- `__construct($l, $c, $h, $alpha = 1.0)`, `toLCH(): CSSLCH`, `toLab(): CSSLab`, `clone(): static`, `__toString(): string`.
