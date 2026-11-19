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
| `setConditionText(string $conditionText): void` | Trims and stores text after structural validation; the empty string is allowed. Throws `InvalidArgumentException` for disallowed structure. | `$condition->setConditionText('(width > 40rem)');` |
| `__get(string $name): mixed` | Adds virtual `conditionText`, otherwise delegates through `CSSGroupingRule` to `CSSRule`. | `$condition->conditionText;` |
| `__set(string $name, mixed $value): void` | A string `conditionText` assignment calls `setConditionText()`; other assignments delegate and fail with `Error` unless supported by a parent. | `$condition->conditionText = '(width > 40rem)';` |

The inherited constructor is `CSSGroupingRule::__construct(string $name, string $prelude = '')`. Other inherited methods are documented in the linked parent pages.
