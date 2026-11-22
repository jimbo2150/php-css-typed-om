# `CSSOKLab`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSOKLab`
- **Source:** [`src/TypedOM/Values/Color/CSSOKLab.php`](../../../../src/TypedOM/Values/Color/CSSOKLab.php)
- **Inheritance:** [`CSSColorValue`](CSSColorValue.md)
- **Purpose:** Represents a CSS `oklab(...)` color value (`l` percentage where bare `0..1` maps to `0%..100%`, `a` and `b` numbers/percentages, `alpha` percentage).

## Properties & Methods

- Channels (`CSSNumericValue|CSSKeywordValue`): `l`, `a`, `b`, `alpha` (`getL()`/`setL()`, `getA()`/`setA()`, `getB()`/`setB()`, `getAlpha()`/`setAlpha()`, plus magic `__get`/`__set`).
- `__construct($l, $a, $b, $alpha = 1.0)`, `toOKLab(): CSSOKLab`, `clone(): static`, `__toString(): string`.
