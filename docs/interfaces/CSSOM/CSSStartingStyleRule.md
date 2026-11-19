# `CSSStartingStyleRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSStartingStyleRule`
- **Source:** [`src/CSSOM/CSSStartingStyleRule.php`](../../../src/CSSOM/CSSStartingStyleRule.php)
- **Purpose:** Represents block-form `@starting-style` containing nested rules.
- **Inheritance:** `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

No additional public properties; `cssRules`, `type`, `cssText`, and parent associations are available through inherited APIs.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct()` | Creates a grouping rule with name `starting-style` and empty prelude. | `$rule = new CSSStartingStyleRule();` |
| `getType(): int` | Returns `CSSRule::STARTING_STYLE_RULE`. | `$rule->getType();` |

All inherited public methods are documented on [`CSSGroupingRule`](CSSGroupingRule.md) and [`CSSRule`](CSSRule.md).
