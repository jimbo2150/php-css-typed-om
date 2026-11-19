# `CSSFontFaceRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceRule`
- **Source:** [`src/CSSOM/CSSFontFaceRule.php`](../../../src/CSSOM/CSSFontFaceRule.php)
- **Purpose:** Represents `@font-face`, copying declarations into a `CSSFontFaceDescriptors` block.
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited public APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).

## Properties

`style` is exposed as `CSSFontFaceDescriptors`; other virtual at-rule and rule properties are inherited. The constructor converts every declaration and its priority from the supplied style block.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(CSSStyleDeclaration $style)` | Copies each declaration into a new `CSSFontFaceDescriptors` object; produces a block-form `@font-face` rule. | `$rule = new CSSFontFaceRule(new CSSStyleDeclaration('font-family: Demo; src: url(demo.woff2)'));` |
| `getStyle(): CSSFontFaceDescriptors` | Returns the typed descriptor declaration object. | `$rule->getStyle()->getPropertyValue('font-family');` |

Other inherited public methods are documented on [`CSSAtRule`](CSSAtRule.md) and [`CSSRule`](CSSRule.md).
