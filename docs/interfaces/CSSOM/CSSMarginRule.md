# `CSSMarginRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSMarginRule`
- **Source:** [`src/CSSOM/CSSMarginRule.php`](../../../src/CSSOM/CSSMarginRule.php)
- **Purpose:** Represents a page-margin box at-rule (for example, `@top-left-corner`) and its declarations.
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited public APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).

## Properties

No additional virtual properties are declared; inherited `name`, `prelude`, `style`, `type`, `cssText`, and parent properties apply. The rule name is normalized to lowercase.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $name, CSSStyleDeclaration $style)` | Requires a recognized top/bottom/left/right margin-box name, optionally with `-corner`; throws `InvalidArgumentException` otherwise. | `$rule = new CSSMarginRule('top-left', new CSSStyleDeclaration('content: "Page"'));` |
| `getType(): int` | Returns `CSSRule::MARGIN_RULE`. | `$rule->getType();` |

All inherited public methods are documented on [`CSSAtRule`](CSSAtRule.md) and [`CSSRule`](CSSRule.md).
