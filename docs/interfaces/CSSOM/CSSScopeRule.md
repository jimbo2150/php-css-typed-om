# `CSSScopeRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSScopeRule`
- **Source:** [`src/CSSOM/CSSScopeRule.php`](../../../src/CSSOM/CSSScopeRule.php)
- **Purpose:** Represents `@scope` with optional start and end selectors and a nested rule list.
- **Inheritance:** `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic read-only `start` and `end` return parsed bounds (each nullable). Inherited `cssRules` and CSSRule virtual properties apply.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $prelude = '', ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Creates `@scope` under `$maxBytes`, parses optional bounds; malformed, over-limit, or unclosed bounds throw `InvalidArgumentException` / `CSSParserLimitException`. | `$rule = new CSSScopeRule('(.article) to (.footer)');` |
| `getType(): int` | Returns `CSSRule::SCOPE_RULE`. | `$rule->getType();` |
| `getStart(): ?string` | Returns the selector in the initial parenthesized bound, or `null` when absent. | `$rule->getStart();` |
| `getEnd(): ?string` | Returns selector following `to (...)`, or `null` when absent. | `$rule->getEnd();` |
| `__get(string $name): mixed` | Exposes `start` and `end`; other names delegate to `CSSGroupingRule::__get()`. | `$rule->start;` |

Inherited grouping and CSSRule methods are documented in the linked parent pages.
