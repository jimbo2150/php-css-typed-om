# `CSSLab`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSLab`
- **Source:** [`src/TypedOM/Values/Color/CSSLab.php`](../../../../src/TypedOM/Values/Color/CSSLab.php)
- **Inheritance:** [`CSSColorValue`](CSSColorValue.md)
- **Purpose:** Represents a CSS CIE `lab(...)` color value (`l` percentage, `a` and `b` numbers/percentages, `alpha` percentage).

## Properties & Methods

- Channels (`CSSNumericValue|CSSKeywordValue`): property hooks `$l`, `$a`, `$b`, `$alpha` (cloning returned `CSSKeywordValue` instances) and methods `getL()`/`setL()`, `getA()`/`setA()`, `getB()`/`setB()`, `getAlpha()`/`setAlpha()`.
- `__construct($l, $a, $b, $alpha = 1.0)`, `toLab(): CSSLab`, `clone(): static`, `__toString(): string`.
