# `CSSLab`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSLab`
- **Source:** [`src/TypedOM/Values/Color/CSSLab.php`](../../../../src/TypedOM/Values/Color/CSSLab.php)
- **Inheritance:** [`CSSColorValue`](CSSColorValue.md)
- **Purpose:** Represents a CSS CIE `lab(...)` color value (`l` percentage, `a` and `b` numbers/percentages, `alpha` percentage).

## Properties & Methods

- Channels (`CSSNumericValue|CSSKeywordValue`): `l`, `a`, `b`, `alpha` (`getL()`/`setL()`, `getA()`/`setA()`, `getB()`/`setB()`, `getAlpha()`/`setAlpha()`, plus magic `__get`/`__set`).
- `__construct($l, $a, $b, $alpha = 1.0)`, `toLab(): CSSLab`, `clone(): static`, `__toString(): string`.
