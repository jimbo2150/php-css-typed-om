# `CSSCounterStyleRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSCounterStyleRule`
- **Source:** [`src/CSSOM/CSSCounterStyleRule.php`](../../../src/CSSOM/CSSCounterStyleRule.php)
- **Purpose:** Represents a named `@counter-style` descriptor rule.
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited public APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `name` reads/writes the prelude; named descriptor properties are recognized for `system`, `symbols`, `additiveSymbols`, `negative`, `prefix`, `suffix`, `range`, `pad`, `speakAs`, and `fallback` (camel case maps to kebab case). Other inherited virtual properties are documented on the parent pages. The `DESCRIPTORS` constant is private.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $name, CSSStyleDeclaration $descriptors)` | Requires a non-empty name and associates the descriptor block. Throws `InvalidArgumentException` for an empty name. | `$rule = new CSSCounterStyleRule('custom', new CSSStyleDeclaration('system: cyclic; symbols: "*"'));` |
| `getNameText(): string` | Returns the current prelude/name. | `$rule->getNameText();` |
| `setNameText(string $name): void` | Trims, rejects empty values, normalizes using `CSSStyleDeclaration::normalizePropertyName()`, and stores the result. Throws `InvalidArgumentException` for empty/invalid names. | `$rule->setNameText('custom');` |
| `getDescriptor(string $name): string` | Reads a descriptor value; absent descriptors return `''`. | `$rule->getDescriptor('system');` |
| `setDescriptor(string $name, string $value): void` | Sets the named declaration through the attached style object. | `$rule->setDescriptor('suffix', ' "."');` |
| `__get(string $name): mixed` | Reads `name` or a recognized descriptor accessor; other names delegate to `CSSAtRule::__get()`. | `$rule->symbols;` |
| `__set(string $name, mixed $value): void` | String writes to `name` or known descriptor properties are routed to their setters; other writes delegate and normally throw `Error`. | `$rule->suffix = ' "."';` |

Inherited `getType()`, `getName()`, `getPrelude()`, `getStyle()`, `hasBlock()`, `getCssText()`, and CSSRule methods are documented in the parent pages.
