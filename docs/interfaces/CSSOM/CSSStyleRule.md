# `CSSStyleRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule`
- **Source:** [`src/CSSOM/CSSStyleRule.php`](../../../src/CSSOM/CSSStyleRule.php)
- **Purpose:** Represents a selector, declarations, and optional nested CSS rules.
- **Inheritance:** `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `selectorText` reads/writes the selector; `style` returns `CSSStyleProperties`; `styleMap` caches and returns the same `StylePropertyMap` instance per rule. Grouping and CSSRule virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $selectorText, ?CSSStyleDeclaration $style = null, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Requires a non-empty, structurally valid selector (non-empty and not starting with `@` after stripping comments, allowing escaped `\@`) capped at `$maxBytes`. Copies/clones an owned declaration into `CSSStyleProperties`. Throws `InvalidArgumentException` / `CSSParserLimitException` for invalid/over-limit selector. | `$rule = new CSSStyleRule('.card', new CSSStyleDeclaration('color: red'));` |
| `getType(): int` | Returns `CSSRule::STYLE_RULE`. | `$rule->getType();` |
| `getSelectorText(): string` | Returns the selector. | `$rule->getSelectorText();` |
| `setSelectorText(string $selectorText): void` | Trims and validates non-empty, non-comment-only selector text not starting with `@` (capped at owning sheet `maxBytes` or `DEFAULT_MAX_BYTES`), then updates selector and prelude. Throws `InvalidArgumentException` / `CSSParserLimitException` if invalid. | `$rule->setSelectorText('.panel');` |
| `getStyle(): CSSStyleProperties` | Returns the live typed declaration block. | `$rule->getStyle()->setProperty('color', 'blue');` |
| `getStyleMap(): StylePropertyMap` | Returns the cached `StylePropertyMap` backed by `$rule->getStyle()`. | `$map = $rule->getStyleMap();` |
| `insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int` | Parses and inserts one nested rule, inheriting owning stylesheet `CSSParserOptions` limits when `$options` is `null`; requires attached stylesheet. Returns index. Throws `LogicException` if detached, `OutOfBoundsException` for invalid index, `InvalidArgumentException` for parse/limit failures. | `$rule->insertRule('&:hover { color: blue }');` |
| `getCssText(): string` | Serializes selector and declaration/nested-rule content iteratively via `CSSGroupingRule`, enforcing `CSSRenderer::MAX_RENDER_DEPTH`. | `$rule->getCssText();` |
| `__clone(): void` | Clones nested style declaration cleanly and resets parent links / cached `styleMap`. | `$copy = clone $rule;` |
| `__get(string $name): mixed` | Exposes `selectorText`, `style`, and `styleMap`; other names delegate to `CSSGroupingRule::__get()`. | `$rule->styleMap;` |
| `__isset(string $name): bool` | True for `selectorText`, `style`, and `styleMap`; other names delegate to `CSSGroupingRule::__isset()`. | `isset($rule->selectorText);` |
| `__set(string $name, mixed $value): void` | String selector assignment uses `setSelectorText()`; other writes delegate to parent magic setter. | `$rule->selectorText = '.panel';` |

Inherited grouping-rule and CSSRule methods are documented on the linked parent pages.
