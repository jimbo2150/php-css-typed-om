# `CSSNestedDeclarations`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSNestedDeclarations`
- **Source:** [`src/CSSOM/CSSNestedDeclarations.php`](../../../src/CSSOM/CSSNestedDeclarations.php)
- **Purpose:** Rule node for declarations interleaved among nested rules.
- **Inheritance:** `CSSRule`; inherited public API: [`CSSRule`](CSSRule.md).

## Properties

Magic read-only `style` returns the declaration block. Other CSSRule virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(CSSStyleDeclaration $style)` | Normalizes `$style` into a `CSSStyleProperties` instance and sets this rule as its parent. | `$rule = new CSSNestedDeclarations(new CSSStyleDeclaration('color: red'));` |
| `getType(): int` | Returns `CSSRule::NESTED_DECLARATIONS_RULE`. | `$rule->getType();` |
| `getStyle(): CSSStyleProperties` | Returns the associated `CSSStyleProperties` block. | `$rule->getStyle()->getPropertyValue('color');` |
| `getCssText(): string` | Returns the declaration block's serialized CSS text. | `$rule->getCssText();` |
| `__get(string $name): mixed` | Exposes `style`; other names delegate to `CSSRule::__get()`. | `$rule->style;` |

All additional inherited public methods are detailed in [`CSSRule`](CSSRule.md).
