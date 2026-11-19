# `CSSStyleRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule`
- **Source:** [`src/CSSOM/CSSStyleRule.php`](../../../src/CSSOM/CSSStyleRule.php)
- **Purpose:** Represents a selector, declarations, and optional nested CSS rules.
- **Inheritance:** `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `selectorText` reads/writes the selector; `style` returns `CSSStyleProperties`; `styleMap` returns a new `StylePropertyMap` over the style each time it is read. Grouping and CSSRule virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $selectorText, ?CSSStyleDeclaration $style = null)` | Requires a non-empty, structurally valid selector. Copies a general declaration into `CSSStyleProperties` unless already that subtype. Throws `InvalidArgumentException` for invalid selector. | `$rule = new CSSStyleRule('.card', new CSSStyleDeclaration('color: red'));` |
| `getType(): int` | Returns `CSSRule::STYLE_RULE`. | `$rule->getType();` |
| `getSelectorText(): string` | Returns the selector. | `$rule->getSelectorText();` |
| `setSelectorText(string $selectorText): void` | Trims and validates non-empty selector text, then updates selector and prelude. Throws `InvalidArgumentException` if invalid. | `$rule->setSelectorText('.panel');` |
| `getStyle(): CSSStyleProperties` | Returns the live typed declaration block. | `$rule->getStyle()->setProperty('color', 'blue');` |
| `getStyleMap(): StylePropertyMap` | Creates a new `StylePropertyMap` backed by the same style declarations. | `$map = $rule->getStyleMap();` |
| `insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int` | Parses and inserts one nested rule; requires attached stylesheet. Returns index. Throws `LogicException` if detached, `OutOfBoundsException` for invalid index, `InvalidArgumentException` for parse/limit failures. | `$rule->insertRule('&:hover { color: blue }');` |
| `getCssText(): string` | Serializes selector and declaration/nested-rule content in one block. | `$rule->getCssText();` |
| `__get(string $name): mixed` | Exposes `selectorText`, `style`, and `styleMap`; other names delegate to `CSSGroupingRule::__get()`. | `$rule->styleMap;` |
| `__set(string $name, mixed $value): void` | String selector assignment uses `setSelectorText()`; other writes delegate to parent magic setter. | `$rule->selectorText = '.panel';` |

Inherited grouping-rule and CSSRule methods are documented on the linked parent pages.
