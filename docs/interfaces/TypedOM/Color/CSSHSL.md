# `CSSHSL`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSHSL`
- **Source:** [`src/TypedOM/Values/Color/CSSHSL.php`](../../../../src/TypedOM/Values/Color/CSSHSL.php)
- **Inheritance:** [`CSSColorValue`](CSSColorValue.md)
- **Purpose:** Represents a CSS `hsl(...)` color value (`h` angle, `s` and `l` percentages, `alpha` percentage).

## Properties & Methods

- Channels (`CSSNumericValue|CSSKeywordValue`): `h`, `s`, `l`, `alpha` (`getH()`/`setH()`, `getS()`/`setS()`, `getL()`/`setL()`, `getAlpha()`/`setAlpha()`, plus magic `__get`/`__set`).
- `__construct($h, $s, $l, $alpha = 1.0)`, `toHSL(): CSSHSL`, `clone(): static`, `__toString(): string`.
