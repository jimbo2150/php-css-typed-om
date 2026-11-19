# `CSSContainerRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSContainerRule`
- **Source:** [`src/CSSOM/CSSContainerRule.php`](../../../src/CSSOM/CSSContainerRule.php)
- **Purpose:** Represents an `@container` conditional grouping rule and exposes its container name and query portions.
- **Inheritance:** `CSSConditionRule` → `CSSGroupingRule` → `CSSRule`; inherited APIs: [`CSSConditionRule`](CSSConditionRule.md), [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic read-only properties: `containerName`, `containerQuery`, and inherited `conditionText`, `cssRules`, `type`, `cssText`, `parentStyleSheet`, and `parentRule`.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $conditionText)` | Uses the condition text as the `@container` prelude; parent validation applies. | `$rule = new CSSContainerRule('sidebar (width > 30rem)');` |
| `getType(): int` | Returns `CSSRule::CONTAINER_RULE`. | `$rule->getType();` |
| `getContainerName(): string` | Returns text before the first top-level `(`, or the entire trimmed prelude when none occurs. | `$rule->getContainerName();` |
| `getContainerQuery(): string` | Returns text from the first top-level `(` onward, or `''` when no parenthesized query occurs. | `$rule->getContainerQuery();` |
| `__get(string $name): mixed` | Adds `containerName` and `containerQuery`, then delegates to inherited magic access. | `$rule->containerQuery;` |

All inherited public methods and their behavior/examples are documented in the linked parent pages.
