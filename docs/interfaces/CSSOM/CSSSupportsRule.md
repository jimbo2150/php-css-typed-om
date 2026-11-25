# `CSSSupportsRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSSupportsRule`
- **Source:** [`src/CSSOM/CSSSupportsRule.php`](../../../src/CSSOM/CSSSupportsRule.php)
- **Purpose:** Represents a conditional `@supports` grouping rule.
- **Inheritance:** `CSSConditionRule` → `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSConditionRule`](CSSConditionRule.md), [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `conditionText` is inherited and writable through its setter; nested `cssRules` and CSSRule properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $conditionText, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Creates a `supports` grouping rule with supplied condition under `$maxBytes`. | `$rule = new CSSSupportsRule('(display: grid)');` |
| `getType(): int` | Returns `CSSRule::SUPPORTS_RULE`. | `$rule->getType();` |

All inherited public methods are documented on [`CSSConditionRule`](CSSConditionRule.md), [`CSSGroupingRule`](CSSGroupingRule.md), and [`CSSRule`](CSSRule.md).
