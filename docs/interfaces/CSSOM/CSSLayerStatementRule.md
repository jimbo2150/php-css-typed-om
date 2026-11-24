# `CSSLayerStatementRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSLayerStatementRule`
- **Source:** [`src/CSSOM/CSSLayerStatementRule.php`](../../../src/CSSOM/CSSLayerStatementRule.php)
- **Purpose:** Represents a statement-form `@layer` declaration containing a comma-separated layer-name list.
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited public APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `nameList` returns parsed layer names. Other at-rule and rule virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $layerNames)` | Stores non-empty, comma-separated layer names as the at-rule prelude after validating non-empty segments and syntactic constraints (`CSSAtRule`, `DEFAULT_MAX_BYTES`); throws `InvalidArgumentException` / `CSSParserLimitException` when empty or invalid. | `$rule = new CSSLayerStatementRule('reset, components');` |
| `getType(): int` | Returns `CSSRule::LAYER_STATEMENT_RULE`. | `$rule->getType();` |
| `getLayerNames(): array` | Splits on commas, trims entries, and omits empty entries while preserving order. | `$rule->getLayerNames();` |
| `__get(string $name): mixed` | Exposes `nameList`; other names delegate to `CSSAtRule::__get()`. | `$rule->nameList;` |

All inherited public methods are documented on [`CSSAtRule`](CSSAtRule.md) and [`CSSRule`](CSSRule.md).
