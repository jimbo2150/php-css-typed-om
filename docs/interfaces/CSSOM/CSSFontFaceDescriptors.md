# `CSSFontFaceDescriptors`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceDescriptors`
- **Source:** [`src/CSSOM/CSSFontFaceDescriptors.php`](../../../src/CSSOM/CSSFontFaceDescriptors.php)
- **Purpose:** Strict descriptor declaration block for `@font-face` rules (CSS Fonts Module Level 4/5).
- **Inheritance:** `CSSStyleDeclaration`; inherited public API: [`CSSStyleDeclaration`](CSSStyleDeclaration.md).

## Validation Policy

- Enforces the CSS Fonts 4/5 `@font-face` descriptor whitelist (`ascent-override`, `descent-override`, `font-display`, `font-family`, `font-feature-settings`, `font-language-override`, `font-named-instance`, `font-stretch`, `font-style`, `font-variation-settings`, `font-weight`, `font-width`, `line-gap-override`, `size-adjust`, `src`, `unicode-range`).
- Disallows custom properties (`--*`) and `!important`.
- Direct mutation (`__construct()`, `setProperty()`, `setCssText()`) throws `InvalidArgumentException` on disallowed descriptors or `!important`; `CSSStyleSheet::parse()` records byte-offset diagnostics and keeps valid descriptors.
- Inspect the whitelist via `CSSFontFaceDescriptors::getAllowedDescriptors()`.
