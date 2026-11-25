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
| `__construct(string $name, CSSStyleDeclaration $descriptors, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Validates name within `$maxBytes` before storing and associates the descriptor block. Throws `InvalidArgumentException` / `CSSParserLimitException` when invalid or oversized. | `$rule = new CSSCounterStyleRule('custom', new CSSStyleDeclaration('system: cyclic; symbols: "*"'));` |
| `getNameText(): string` | Returns the current prelude/name. | `$rule->getNameText();` |
| `setNameText(string $name): void` | Trims and validates a case-preserving `<custom-ident>` within the owning sheet's `maxBytes` (or constructor-configured `maxBytes` when detached), rejecting control bytes (raw or escape-decoded) and CSS Counter Styles Level 3 disallowed names (`none`, `initial`, `inherit`, `unset`, `revert`, `revert-layer`, `default`, `decimal`, `disc`, `square`, `circle`, `disclosure-open`, `disclosure-closed`, `inside`, `outside`, including CSS-escaped forms). | `$rule->setNameText('Custom');` |
| `getDescriptor(string $name): string` | Reads a descriptor value; absent descriptors return `''`. | `$rule->getDescriptor('system');` |
| `setDescriptor(string $name, string $value): void` | Sets the named declaration through the attached style object. | `$rule->setDescriptor('suffix', ' "."');` |
| `__get(string $name): mixed` | Reads `name` or a recognized descriptor accessor; other names delegate to `CSSAtRule::__get()`. | `$rule->symbols;` |
| `__isset(string $name): bool` | True for `name` or recognized descriptor properties, otherwise delegates to `CSSAtRule::__isset()`. | `isset($rule->name);` |
| `__set(string $name, mixed $value): void` | String writes to `name` or known descriptor properties are routed to their setters; other writes delegate and normally throw `Error`. | `$rule->suffix = ' "."';` |

Inherited `getType()`, `getName()`, `getPrelude()`, `getStyle()`, `hasBlock()`, `getCssText()`, and CSSRule methods are documented in the parent pages.
