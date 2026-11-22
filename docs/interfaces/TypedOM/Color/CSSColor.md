# `CSSColor`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColor`
- **Source:** [`src/TypedOM/Values/Color/CSSColor.php`](../../../../src/TypedOM/Values/Color/CSSColor.php)
- **Inheritance:** [`CSSColorValue`](CSSColorValue.md)
- **Purpose:** Represents a CSS `color(<colorspace> c1 c2 c3 [/ alpha])` value for CSS Color 4 predefined color spaces (`srgb`, `srgb-linear`, `display-p3`, `a98-rgb`, `prophoto-rgb`, `rec2020`, `xyz`, `xyz-d50`, `xyz-d65`) or custom `--*` dashed color spaces.

## Properties & Methods

- `PREDEFINED_SPACES`: constant map of supported predefined CSS Color 4 color spaces.
- Properties: `colorSpace` (`CSSKeywordValue`), `channels` (`list<CSSNumericValue|CSSKeywordValue>`), `alpha` (`CSSNumericValue|CSSKeywordValue`).
- `__construct($colorSpace, array $channels, $alpha = 1.0)`, `getColorSpace()`, `setColorSpace()`, `getChannels()`, `setChannels()`, `clone(): static`, `__toString(): string`.
