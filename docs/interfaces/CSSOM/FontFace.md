# `FontFace`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\FontFace`
- **Source:** [`src/CSSOM/FontFace.php`](../../../src/CSSOM/FontFace.php)
- **Purpose:** Standalone metadata and descriptor model for a CSS `FontFace` (CSS Font Loading API) without browser network/binary font loading.

## Properties

- Mutable string properties (getters, setters, and magic `__get`/`__set`): `family`, `source`, `style` (`'normal'`), `weight` (`'normal'`), `stretch` (`'normal'`), `unicodeRange` (`'U+0-10FFFF'`), `variant` (`'normal'`), `featureSettings` (`'normal'`), `variationSettings` (`'normal'`), `display` (`'auto'`), `ascentOverride` (`'normal'`), `descentOverride` (`'normal'`), `lineGapOverride` (`'normal'`), `sizeAdjust` (`'100%'`).
- Read-only property: `status` (always `'unloaded'` outside a browser DOM).

## Public Methods

- `__construct(string $family, string $source, array $descriptors = [])` — validates `$family`, `$source`, descriptor keys, and values via `CSSStyleDeclaration::validatePropertyValue()`.
- `toCSSFontFaceRule(): CSSFontFaceRule` — builds a [`CSSFontFaceRule`](CSSFontFaceRule.md) backed by strict [`CSSFontFaceDescriptors`](CSSFontFaceDescriptors.md) (intentionally omitting `variant`, which is not a CSS Fonts 4 `@font-face` descriptor).
- `fromCSSFontFaceRule(CSSFontFaceRule $rule): self` — constructs a `FontFace` from a parsed `@font-face` rule; throws `InvalidArgumentException` if `font-family` or `src` is missing.
