# `CSSLayerBlockRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSLayerBlockRule`
- **Source:** [`src/CSSOM/CSSLayerBlockRule.php`](../../../src/CSSOM/CSSLayerBlockRule.php)
- **Purpose:** Represents a block-form `@layer` rule containing nested rules.
- **Inheritance:** `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic read-only `name` exposes the prelude; `cssRules` and standard CSSRule virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $layerName = '', ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Creates a block rule with an optional layer-name prelude under `$maxBytes`; validation follows `CSSGroupingRule`. | `$rule = new CSSLayerBlockRule('components');` |
| `getType(): int` | Returns `CSSRule::LAYER_BLOCK_RULE`. | `$rule->getType();` |
| `getNameText(): string` | Returns the layer name/prelude. | `$rule->getNameText();` |
| `__get(string $name): mixed` | Exposes `name`; all other names delegate to `CSSGroupingRule::__get()`. | `$rule->name;` |

All inherited public methods are described on the linked parent pages.
