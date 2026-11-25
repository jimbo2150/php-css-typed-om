# `CSSColorValue`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue`
- **Source:** [`src/TypedOM/Values/Color/CSSColorValue.php`](../../../../src/TypedOM/Values/Color/CSSColorValue.php)
- **Inheritance:** [`CSSStyleValue`](../CSSStyleValue.md)
- **Purpose:** Abstract base class for CSS Typed OM color values, providing CSS Color 4 parsing and color-space conversions.

## Public API

- `public static function parse(string $propertyOrCssText, ?string $cssText = null, ?CSSParserOptions $options = null): self` — when `$cssText !== null`, validates `$propertyOrCssText` (`$options->maxBytes` and `CSSStyleDeclaration::normalizePropertyName()`); trims CSS whitespace (`CSSSyntaxScanner::trimCssWhitespace()`), strips top-level comments inside color function bodies with escape awareness (preserving inner tokens/errors), rejects space before `(`, and parses hex (`#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa`) and functional colors (`rgb()`, `rgba()`, `hsl()`, `hsla()`, `hwb()`, `lab()`, `lch()`, `oklab()`, `oklch()`, `color()` — with CSS-escaped `none` and predefined color-space decoding plus arity cap at `CSSColor::MAX_CUSTOM_CHANNELS + 4`). Enforces `percentHint === null` on math channels, scales `'number'` math channels where percentage scaling applies, and rejects relative color syntax (`from ...`) and `var()` so `CSSStyleValue::parse()` falls back to `CSSUnparsedValue`.
- `getAlpha(): CSSNumericValue|CSSKeywordValue`, `setAlpha(CSSNumericValue|CSSKeywordValue|int|float $alpha): void`, and hooked property `public CSSNumericValue|CSSKeywordValue $alpha { get; set; }`. Keyword components (`'none'`, color spaces) are cloned on store and return so callers cannot mutate canonical keywords into invalid tokens. All subclasses implement `__clone(): void` to deep-clone channel values.
- Serialization (`__toString()` / `toBoundedCssTextInternal()`): serializes canonical CSS color syntax cleanly within `CSSParserOptions::DEFAULT_MAX_BYTES` (or exact remaining `StylePropertyMap` byte budgets, including unbounded `null` when `CSSParserOptions::unlimited()` is active).
- Conversion methods (using W3C CSS Color 4 transfer functions & rational Bradford D65↔D50 matrices; `to()` trims CSS whitespace and short-circuits exact `CSSColor` space matches): `toRGB(): CSSRGB`, `toHSL(): CSSHSL` (note CSS Color 4 §7.1 coordinate singularity at `l === 0.0` or `l === 1.0`), `toHWB(): CSSHWB`, `toLab(): CSSLab`, `toLCH(): CSSLCH`, `toOKLab(): CSSOKLab`, `toOKLCH(): CSSOKLCH`, `to(string $colorSpace): self`.
