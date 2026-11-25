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
| `__construct(string $conditionText, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Uses the condition text as the `@container` prelude under `$maxBytes`; parent validation applies. | `$rule = new CSSContainerRule('sidebar (width > 30rem)');` |
| `getType(): int` | Returns `CSSRule::CONTAINER_RULE`. | `$rule->getType();` |
| `getContainerName(): string` | Parses leading validated `<container-name>` via `decodeIdentAt()` (supporting CSS escapes such as `card\(1` and rejecting `none`, `and`, `not`, `or`, and CSS-wide keywords), or `''` if the prelude starts directly with a query. | `$rule->getContainerName();` |
| `getContainerQuery(): string` | Returns the query portion following any valid `<container-name>`, or the entire trimmed prelude when unnamed. | `$rule->getContainerQuery();` |
| `__get(string $name): mixed` | Adds `containerName` and `containerQuery`, then delegates to inherited magic access. | `$rule->containerQuery;` |
| `__isset(string $name): bool` | True for `containerName` and `containerQuery`, otherwise delegates to parent `__isset()`. | `isset($rule->containerName);` |

All inherited public methods and their behavior/examples are documented in the linked parent pages.
