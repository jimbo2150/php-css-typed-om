# `CSSNamespaceRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSNamespaceRule`
- **Source:** [`src/CSSOM/CSSNamespaceRule.php`](../../../src/CSSOM/CSSNamespaceRule.php)
- **Purpose:** Represents a CSS `@namespace` statement with optional prefix and namespace URI accessors.
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited public APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic read-only `prefix` and `namespaceURI` expose parsed prelude components. Other at-rule and rule virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $prelude)` | Requires a non-empty, structurally valid namespace URI prelude (rejecting comment-only preludes); throws `InvalidArgumentException` if empty or invalid. | `$rule = new CSSNamespaceRule('svg "http://www.w3.org/2000/svg"');` |
| `getPrefix(): string` | Returns the optional leading identifier prefix (strip-commented and escape-aware when recognizing unprefixed `url(...)` or quoted URIs); otherwise `''`. | `$rule->getPrefix();` |
| `getNamespaceURI(): string` | Returns second token for prefixed form or first token for default namespace; unwraps `url(...)` (including escaped `url`) and quotes. | `$rule->getNamespaceURI();` |
| `getType(): int` | Returns `CSSRule::NAMESPACE_RULE`. | `$rule->getType();` |
| `__get(string $name): mixed` | Exposes `prefix` and `namespaceURI`, otherwise delegates to `CSSAtRule::__get()`. | `$rule->namespaceURI;` |

Other inherited public methods are documented on [`CSSAtRule`](CSSAtRule.md) and [`CSSRule`](CSSRule.md).
