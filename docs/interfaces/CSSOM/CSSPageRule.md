# `CSSPageRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageRule`
- **Source:** [`src/CSSOM/CSSPageRule.php`](../../../src/CSSOM/CSSPageRule.php)
- **Purpose:** Represents an `@page` rule with typed descriptors and nested page-margin rules.
- **Inheritance:** `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `selectorText` is readable and accepts string assignment through `__set()`, while `style` is read-only. Inherited `cssRules` and CSSRule properties also apply.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $selector, ?CSSStyleDeclaration $style = null, array $marginRules = [], ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Checks `$maxBytes` before scanning; `$style` defaults to an empty `CSSPageDescriptors` (if an already-owned declaration is supplied, it is cloned rather than aliased); `$marginRules` must contain only detached `CSSMarginRule` instances (validated upfront before mutation and appended without cloning). | `$page = new CSSPageRule(':first', new CSSStyleDeclaration('size: A4'));` |
| `getType(): int` | Returns `CSSRule::PAGE_RULE`. | `$page->getType();` |
| `getStyle(): CSSPageDescriptors` | Returns page descriptor declarations. | `$page->getStyle()->getPropertyValue('size');` |
| `getMarginRules(): array` | Returns nested rules that are instances of `CSSMarginRule`, in list order. | `$page->getMarginRules();` |
| `getSelectorText(): string` | Returns the page selector from the prelude. | `$page->getSelectorText();` |
| `setSelectorText(string $selector): void` | Enforces the owning stylesheet/rule `maxBytes`, trims CSS whitespace, and validates structural syntax (allows empty text), then updates the prelude. Throws `InvalidArgumentException` / `CSSParserLimitException` on invalid or oversized syntax. | `$page->setSelectorText(':left');` |
| `insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int` | Parses and inserts one `@margin` rule at `$index`, inheriting owning stylesheet `CSSParserOptions` limits when `$options` is `null`. Throws `LogicException` if detached, `OutOfBoundsException` for invalid index, `InvalidArgumentException` / `CSSParserLimitException` on non-margin or invalid rules. | `$page->insertRule('@top-left { content: "A"; }', 0);` |
| `getCssText(): string` | Serializes descriptors and nested rules in one `@page` block. | `$page->getCssText();` |
| `__clone(): void` | Deep-clones descriptors and nested margin rules cleanly while detaching parent links. | `$copy = clone $page;` |
| `__get(string $name): mixed` | Exposes `selectorText` and `style`, otherwise delegates to `CSSGroupingRule::__get()`. | `$page->style;` |
| `__isset(string $name): bool` | Returns `true` for `selectorText`, `style`, or parent virtual properties. | `isset($page->style);` |
| `__set(string $name, mixed $value): void` | String `selectorText` assignment invokes its setter; other assignments delegate to the parent. | `$page->selectorText = ':right';` |

Other inherited grouping and rule methods are documented in the linked parent pages.
