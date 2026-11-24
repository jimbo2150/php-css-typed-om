# `CSSColor`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColor`
- **Source:** [`src/TypedOM/Values/Color/CSSColor.php`](../../../../src/TypedOM/Values/Color/CSSColor.php)
- **Inheritance:** [`CSSColorValue`](CSSColorValue.md)
- **Purpose:** Represents a CSS `color(<colorspace> c1 c2 c3 [/ alpha])` value for CSS Color 4 predefined color spaces (`srgb`, `srgb-linear`, `display-p3`, `a98-rgb`, `prophoto-rgb`, `rec2020`, `xyz`, `xyz-d50`, `xyz-d65`) or custom `--*` dashed color spaces.

## Properties & Methods

- `PREDEFINED_SPACES`: constant map of supported predefined CSS Color 4 color spaces.
- `MAX_CUSTOM_CHANNELS`: `64`, maximum channel count allowed for custom `--*` color spaces.
- Properties: property hooks `$colorSpace` (`CSSKeywordValue` returned as a clone from getter/property), `$channels` (`list<CSSNumericValue|CSSKeywordValue>`, cloning returned `CSSKeywordValue` items), `$alpha` (`CSSNumericValue|CSSKeywordValue`, cloning returned `CSSKeywordValue`).
- `__construct($colorSpace, array $channels, $alpha = 1.0)`, `getColorSpace()`, `setColorSpace()` (escape-decoded identifier matching for predefined spaces and `--*` dashed custom spaces, rejecting invalid escapes/bare `--`), `getChannels()`, `setChannels()`, `__clone(): void`, `clone(): static`, `__toString(): string`.
