# `CSSFontPaletteValuesRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontPaletteValuesRule`
- **Source:** [`src/CSSOM/CSSFontPaletteValuesRule.php`](../../../src/CSSOM/CSSFontPaletteValuesRule.php)
- **Purpose:** Represents an `@font-palette-values` rule with a custom dashed name and descriptor accessors.
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited public APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic read-only `name`, `fontFamily`, `basePalette`, and `overrideColors` expose prelude/descriptors. Other at-rule and rule properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $name, CSSStyleDeclaration $descriptors, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Checks `$maxBytes` before scanning, normalizes and requires a name beginning `--`, and associates the descriptors as a block. Throws `InvalidArgumentException` / `CSSParserLimitException` if invalid or oversized. | `$rule = new CSSFontPaletteValuesRule('--brand', new CSSStyleDeclaration('font-family: Demo'));` |
| `getNameText(): string` | Returns the custom name from the prelude. | `$rule->getNameText();` |
| `getFontFamily(): string` | Returns the `font-family` descriptor or `''`. | `$rule->getFontFamily();` |
| `getBasePalette(): string` | Returns the `base-palette` descriptor or `''`. | `$rule->getBasePalette();` |
| `getOverrideColors(): string` | Returns the `override-colors` descriptor or `''`. | `$rule->getOverrideColors();` |
| `__get(string $name): mixed` | Exposes the four virtual properties above and delegates other names to `CSSAtRule::__get()`. | `$rule->basePalette;` |

Inherited public methods are detailed on [`CSSAtRule`](CSSAtRule.md) and [`CSSRule`](CSSRule.md).
