# `CSSViewTransitionRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSViewTransitionRule`
- **Source:** [`src/CSSOM/CSSViewTransitionRule.php`](../../../src/CSSOM/CSSViewTransitionRule.php)
- **Purpose:** Represents `@view-transition` descriptors with convenience accessors for navigation and transition types.
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited public APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic read-only `navigation` and `types` access descriptors. `types` is an array; missing or `none` yields an empty list. Other at-rule/rule virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(CSSStyleDeclaration $descriptors)` | Uses the supplied declarations as a block-form `@view-transition` rule. | `$rule = new CSSViewTransitionRule(new CSSStyleDeclaration('navigation: auto; types: slide, fade'));` |
| `getNavigation(): string` | Returns the `navigation` descriptor or `''`. | `$rule->getNavigation();` |
| `getTypes(): array` | Trims `types`, returns `[]` for empty or `none`, otherwise splits top-level whitespace into a list. | `$rule->getTypes();` |
| `getType(): int` | Returns `CSSRule::VIEW_TRANSITION_RULE`. | `$rule->getType();` |
| `__get(string $name): mixed` | Exposes `navigation` and `types`; other names delegate to `CSSAtRule::__get()`. | `$rule->types;` |

Other inherited methods are documented on [`CSSAtRule`](CSSAtRule.md) and [`CSSRule`](CSSRule.md).
