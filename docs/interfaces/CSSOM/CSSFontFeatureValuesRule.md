# `CSSFontFeatureValuesRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFeatureValuesRule`
- **Source:** [`src/CSSOM/CSSFontFeatureValuesRule.php`](../../../src/CSSOM/CSSFontFeatureValuesRule.php)
- **Purpose:** Models `@font-feature-values`, including category maps and nested rules.
- **Inheritance:** `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `fontFamily` reads/writes the family list. `annotation`, `ornaments`, `stylistic`, `styleset`, `characterVariant`, `swash`, and `historicalForms` lazily return corresponding `CSSFontFeatureValuesMap` objects. Inherited `cssRules` and CSSRule virtual properties also apply. `FEATURE_ACCESSORS` is private.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $fontFamily)` | Requires a non-empty family list. Throws `InvalidArgumentException` if blank. | `$rule = new CSSFontFeatureValuesRule('Demo');` |
| `getType(): int` | Returns `CSSRule::FONT_FEATURE_VALUES_RULE`. | `$rule->getType();` |
| `getFontFamily(): string` | Returns the stored prelude. | `$rule->getFontFamily();` |
| `setFontFamily(string $fontFamily): void` | Trims, rejects blank input, enforces `DEFAULT_MAX_BYTES` and validates structural syntax before storing. Throws `InvalidArgumentException` / `CSSParserLimitException` for invalid or oversized input. | `$rule->setFontFamily('Demo, sans-serif');` |
| `getFeatureMap(string $featureType): CSSFontFeatureValuesMap` | Lowercases and trims a valid category identifier; lazily creates, links to this rule, and returns its map. Throws `InvalidArgumentException` for malformed category names. | `$rule->getFeatureMap('stylistic')->set('salt', [1]);` |
| `getFeatureMaps(): array` | Returns only maps already created, keyed by normalized category name. | `$rule->getFeatureMaps();` |
| `getCssText(): string` | Serializes populated feature maps as category blocks, followed by nested rules. | `$rule->getCssText();` |
| `__clone(): void` | Deep-clones all feature maps cleanly without retaining old owner references. | `$copy = clone $rule;` |
| `__get(string $name): mixed` | Supports `fontFamily` and the seven feature-map accessors; otherwise delegates to `CSSGroupingRule`. | `$rule->stylistic;` |
| `__set(string $name, mixed $value): void` | String `fontFamily` assignments call its setter; other writes delegate to the parent and are rejected when unsupported. | `$rule->fontFamily = 'Demo';` |

Inherited grouping methods and CSSRule methods are documented on the linked parent pages.
