# `CSSAtRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSAtRule`
- **Source:** [`src/CSSOM/CSSAtRule.php`](../../../src/CSSOM/CSSAtRule.php)
- **Purpose:** General representation for CSS at-rules, with optional prelude and descriptor declaration block.
- **Inheritance:** `CSSRule`; inherited public API: [`CSSRule`](CSSRule.md).

## Properties

Internal protected backing fields `$atRuleName`, `$prelude`, `$styleDeclaration`, and `$hasBlock` are not public properties. Magic `__get()` exposes read-only `name`, `prelude`, and `style` in addition to inherited `type`, `cssText`, `parentStyleSheet`, and `parentRule`.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $name, string $prelude = '', ?CSSStyleDeclaration $style = null, bool $hasBlock = false, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Validates the at-rule identifier and prelude (within `$maxBytes`); clones an already-owned `$style` if needed and associates it with this rule. Throws `InvalidArgumentException` / `CSSParserLimitException` for invalid or oversized name/prelude. | `$rule = new CSSAtRule('custom', 'flag', null, false);` |
| `getName(): string` | Returns the original at-rule name. | `$rule->getName();` |
| `getPrelude(): string` | Returns trimmed prelude text. | `$rule->getPrelude();` |
| `getStyle(): ?CSSStyleDeclaration` | Returns the descriptor block, or `null`. | `$rule->getStyle();` |
| `hasBlock(): bool` | Indicates whether serialization uses a declaration block rather than a semicolon. | `$rule->hasBlock();` |
| `getType(): int` | Maps recognized at-rule names to `CSSRule` constants; unknown names return `UNKNOWN_RULE`. | `$rule->getType();` |
| `getCssText(): string` | Serializes `@name prelude;` or `@name prelude { declarations }` according to `hasBlock`. | `$rule->getCssText();` |
| `__get(string $name): mixed` | Provides virtual `name`, `prelude`, and `style`; delegates other names to `CSSRule::__get()` and throws `Error` for unknown names. | `$rule->name;` |

Other inherited public methods, including parent association, string conversion, and immutable magic property writes, are documented on [`CSSRule`](CSSRule.md).
