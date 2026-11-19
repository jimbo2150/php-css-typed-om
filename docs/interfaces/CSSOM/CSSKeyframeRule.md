# `CSSKeyframeRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSKeyframeRule`
- **Source:** [`src/CSSOM/CSSKeyframeRule.php`](../../../src/CSSOM/CSSKeyframeRule.php)
- **Purpose:** Style-rule specialization for one keyframe selector list within `@keyframes`.
- **Inheritance:** `CSSStyleRule` → `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSStyleRule`](CSSStyleRule.md), [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `keyText` reads/writes the selector via the keyframe validator. Inherited style-rule properties include `selectorText`, `style`, `styleMap`, and nested `cssRules`.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `getKeyText(): string` | Returns the selector text. | `$keyframe->getKeyText();` |
| `setKeyText(string $keyText): void` | Accepts comma-separated `from`, `to`, or numeric percentage selectors; throws `InvalidArgumentException` for invalid/empty text. | `$keyframe->setKeyText('from, 50%');` |
| `getType(): int` | Returns `CSSRule::KEYFRAME_RULE`. | `$keyframe->getType();` |
| `__get(string $name): mixed` | Exposes `keyText`, otherwise delegates to `CSSStyleRule::__get()`. | `$keyframe->keyText;` |
| `__set(string $name, mixed $value): void` | String `keyText` assignments call `setKeyText()`; other writes delegate to `CSSStyleRule::__set()`. | `$keyframe->keyText = 'to';` |

All inherited methods are described in the linked parent pages.
