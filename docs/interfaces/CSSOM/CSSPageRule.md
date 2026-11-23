# `CSSPageRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageRule`
- **Source:** [`src/CSSOM/CSSPageRule.php`](../../../src/CSSOM/CSSPageRule.php)
- **Purpose:** Represents an `@page` rule with typed descriptors and nested page-margin rules.
- **Inheritance:** `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic read-only `selectorText` and `style`; `selectorText` accepts string assignment through `__set()`. Inherited `cssRules` and CSSRule properties also apply.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $selector, ?CSSStyleDeclaration $style = null, array $marginRules = [])` | `$style` defaults to an empty `CSSPageDescriptors` (if an already-owned declaration is supplied, it is cloned rather than aliased); `$marginRules` is a list of `CSSMarginRule` copies initialized cleanly. | `$page = new CSSPageRule(':first', new CSSStyleDeclaration('size: A4'));` |
| `getType(): int` | Returns `CSSRule::PAGE_RULE`. | `$page->getType();` |
| `getStyle(): CSSPageDescriptors` | Returns page descriptor declarations. | `$page->getStyle()->getPropertyValue('size');` |
| `getMarginRules(): array` | Returns nested rules that are instances of `CSSMarginRule`, in list order. | `$page->getMarginRules();` |
| `getSelectorText(): string` | Returns the page selector from the prelude. | `$page->getSelectorText();` |
| `setSelectorText(string $selector): void` | Trims and validates structural syntax, allows empty text, then updates the prelude. Throws `InvalidArgumentException` on invalid syntax. | `$page->setSelectorText(':left');` |
| `getCssText(): string` | Serializes descriptors and nested rules in one `@page` block. | `$page->getCssText();` |
| `__get(string $name): mixed` | Exposes `selectorText` and `style`, otherwise delegates to `CSSGroupingRule::__get()`. | `$page->style;` |
| `__set(string $name, mixed $value): void` | String `selectorText` assignment invokes its setter; other assignments delegate to the parent. | `$page->selectorText = ':right';` |

Other inherited grouping and rule methods are documented in the linked parent pages.
