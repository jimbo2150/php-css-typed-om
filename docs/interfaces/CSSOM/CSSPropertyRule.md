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
| `__construct(string $name, CSSStyleDeclaration $descriptors, bool $hasEmptyInitialValue = false, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Checks `$maxBytes` before scanning, normalizes the name, requires `--` prefix, then stores it as prelude with block descriptors. Throws `InvalidArgumentException` / `CSSParserLimitException` if invalid or oversized. `$hasEmptyInitialValue` preserves explicit empty `initial-value: ;` serialization. | `$rule = new CSSPropertyRule('--accent', new CSSStyleDeclaration('syntax: "<color>"; inherits: true'));` |
| `getNameText(): string` | Returns the prelude/name. | `$rule->getNameText();` |
| `getSyntax(): string` | Strips CSS comments and trims CSS whitespace, unwrapping at most one valid outer matching quote pair (`scanQuotedStringEnd()`), or returns `''`. | `$rule->getSyntax();` |
| `getInherits(): bool` | Strips CSS comments, trims CSS whitespace, and decodes CSS escapes; returns `true` only when the value lowercases to `true`. | `$rule->getInherits();` |
| `getInitialValue(): ?string` | Returns `initial-value`, `''` when `$hasEmptyInitialValue` is `true`, or `null` when absent. | `$rule->getInitialValue();` |
| `getCssText(): string` | Serializes `@property` including `initial-value: ;` while empty `initial-value` preservation remains active (cleared automatically if `initial-value` is modified/removed/cleared via `$rule->style`). | `$rule->getCssText();` |
| `__get(string $name): mixed` | Exposes the four named properties and delegates other names to `CSSAtRule::__get()`. | `$rule->initialValue;` |
| `__isset(string $name): bool` | True for `name`, `syntax`, `inherits`, or non-null `initialValue`, otherwise delegates to `CSSAtRule::__isset()`. | `isset($rule->syntax);` |

All inherited public methods are documented on [`CSSAtRule`](CSSAtRule.md) and [`CSSRule`](CSSRule.md).
