# `CSSColorValue`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue`
- **Source:** [`src/TypedOM/Values/Color/CSSColorValue.php`](../../../../src/TypedOM/Values/Color/CSSColorValue.php)
- **Inheritance:** [`CSSStyleValue`](../CSSStyleValue.md)
- **Purpose:** Abstract base class for CSS Typed OM color values, providing CSS Color 4 parsing and color-space conversions.

## Public API

- `public static function parse(string $propertyOrCssText, ?string $cssText = null, ?CSSParserOptions $options = null): self` — parses hex (`#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`) and functional colors (`rgb()`, `rgba()`, `hsl()`, `hsla()`, `hwb()`, `lab()`, `lch()`, `oklab()`, `oklch()`, `color()`). Rejects relative color syntax (`from ...`) and `var()` so `CSSStyleValue::parse()` falls back to `CSSUnparsedValue`.
- `getAlpha(): CSSNumericValue|CSSKeywordValue`, `setAlpha(CSSNumericValue|CSSKeywordValue|int|float $alpha): void`, magic `$color->alpha`.
- Conversion methods (using W3C CSS Color 4 transfer functions & matrices): `toRGB(): CSSRGB`, `toHSL(): CSSHSL`, `toHWB(): CSSHWB`, `toLab(): CSSLab`, `toLCH(): CSSLCH`, `toOKLab(): CSSOKLab`, `toOKLCH(): CSSOKLCH`, `to(string $colorSpace): self`.
