# `CSSConditionRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSConditionRule`
- **Source:** [`src/CSSOM/CSSConditionRule.php`](../../../src/CSSOM/CSSConditionRule.php)
- **Purpose:** Base class for conditional grouping rules such as media, supports, and container rules.
- **Inheritance:** `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `conditionText` reads the prelude. It is writable through `__set()` only when assigned a string. Grouping-rule and CSS-rule virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `getConditionText(): string` | Returns the current condition/prelude text. | `$condition->getConditionText();` |
| `setConditionText(string $conditionText): void` | Enforces the owning stylesheet's `maxBytes` (or constructor-configured `maxBytes` when detached), trims CSS whitespace, and validates structural syntax (`@media` allows `''`, while `@supports` and `@container` require non-empty, non-comment-only conditions). Throws `InvalidArgumentException` / `CSSParserLimitException` for disallowed structure, empty condition, or oversized input. | `$condition->setConditionText('(width > 40rem)');` |
| `__get(string $name): mixed` | Adds virtual `conditionText`, otherwise delegates through `CSSGroupingRule` to `CSSRule`. | `$condition->conditionText;` |
| `__isset(string $name): bool` | Returns `true` for `conditionText` or parent virtual properties. | `isset($condition->conditionText);` |
| `__set(string $name, mixed $value): void` | A string `conditionText` assignment calls `setConditionText()`; other assignments delegate and fail with `Error` unless supported by a parent. | `$condition->conditionText = '(width > 40rem)';` |

The inherited constructor is `CSSGroupingRule::__construct(string $name, string $prelude = '', ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)`. Other inherited methods are documented in the linked parent pages.
