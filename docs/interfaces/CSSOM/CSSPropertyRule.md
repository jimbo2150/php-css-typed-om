# `CSSPropertyRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSPropertyRule`
- **Source:** [`src/CSSOM/CSSPropertyRule.php`](../../../src/CSSOM/CSSPropertyRule.php)
- **Purpose:** Represents a custom-property registration through `@property`.
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited public APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic read-only `name`, `syntax`, `inherits`, and `initialValue` expose the prelude and corresponding descriptors. Other at-rule and rule properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $name, CSSStyleDeclaration $descriptors, bool $hasEmptyInitialValue = false)` | Normalizes the name, requires `--` prefix, then stores it as prelude with block descriptors. Throws `InvalidArgumentException` if not a custom property. `$hasEmptyInitialValue` preserves explicit empty `initial-value: ;` serialization. | `$rule = new CSSPropertyRule('--accent', new CSSStyleDeclaration('syntax: "<color>"; inherits: true'));` |
| `getNameText(): string` | Returns the prelude/name. | `$rule->getNameText();` |
| `getSyntax(): string` | Returns `syntax` with surrounding whitespace/quotes trimmed, or `''`. | `$rule->getSyntax();` |
| `getInherits(): bool` | Returns true only when the trimmed descriptor value lowercases to `true`. | `$rule->getInherits();` |
| `getInitialValue(): ?string` | Returns `initial-value`, `''` when `$hasEmptyInitialValue` is `true`, or `null` when absent. | `$rule->getInitialValue();` |
| `__get(string $name): mixed` | Exposes the four named properties and delegates other names to `CSSAtRule::__get()`. | `$rule->initialValue;` |

All inherited public methods are documented on [`CSSAtRule`](CSSAtRule.md) and [`CSSRule`](CSSRule.md).
