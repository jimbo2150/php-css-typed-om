# `CSSPositionTryRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSPositionTryRule`
- **Source:** [`src/CSSOM/CSSPositionTryRule.php`](../../../src/CSSOM/CSSPositionTryRule.php)
- **Purpose:** Represents an `@position-try` anchor-positioning fallback rule.
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited public APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic read-only `name` returns the custom dashed rule name and `style` returns `CSSPositionTryDescriptors`. Other at-rule and rule properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $name, ?CSSStyleDeclaration $style = null, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Checks `$maxBytes` before scanning, normalizes and requires a name beginning `--`, and copies pre-validated declarations into `CSSPositionTryDescriptors` (or creates empty descriptors when `null`). Throws `InvalidArgumentException` / `CSSParserLimitException` if invalid or oversized. | `$rule = new CSSPositionTryRule('--flip', new CSSStyleDeclaration('top: anchor(bottom)'));` |
| `getNameText(): string` | Returns the custom name from the prelude. | `$rule->getNameText();` |
| `getStyle(): CSSPositionTryDescriptors` | Returns the typed descriptor block. | `$rule->getStyle();` |
| `getType(): int` | Returns `CSSRule::POSITION_TRY_RULE`. | `$rule->getType();` |
| `__get(string $name): mixed` | Exposes `name`; `style` and inherited virtual properties are handled by `CSSAtRule::__get()`. | `$rule->name;` |

Other inherited public methods are documented on [`CSSAtRule`](CSSAtRule.md) and [`CSSRule`](CSSRule.md).
