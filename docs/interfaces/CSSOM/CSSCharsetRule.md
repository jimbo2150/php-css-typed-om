# `CSSCharsetRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSCharsetRule`
- **Source:** [`src/CSSOM/CSSCharsetRule.php`](../../../src/CSSOM/CSSCharsetRule.php)
- **Purpose:** Represents a CSS `@charset` statement and exposes its encoding token.
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited public APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `encoding` reads the result of `getEncoding()`; inherited `name`, `prelude`, `style`, `type`, `cssText`, `parentStyleSheet`, and `parentRule` are also readable. Magic writes are rejected.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $prelude, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Checks `$maxBytes` before scanning; requires non-empty, non-comment-only encoding text (`CSSParserLimitException` / `InvalidArgumentException` otherwise). | `$rule = new CSSCharsetRule('"utf-8"');` |
| `getEncoding(): string` | Strips CSS comments, trims CSS whitespace, and unwraps a single valid outer matching quote pair (`scanQuotedStringEnd()`) from the stored prelude. | `$rule->getEncoding();` |
| `getType(): int` | Always returns `CSSRule::CHARSET_RULE`. | `$rule->getType();` |
| `__get(string $name): mixed` | Adds virtual `encoding`, otherwise delegates to `CSSAtRule::__get()`. | `$rule->encoding;` |
| `__isset(string $name): bool` | True for `encoding`, otherwise delegates to `CSSAtRule::__isset()`. | `isset($rule->encoding);` |

All inherited methods are listed in the linked parent pages.
