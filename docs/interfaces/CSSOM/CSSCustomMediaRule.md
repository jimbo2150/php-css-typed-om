# `CSSCustomMediaRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSCustomMediaRule`
- **Source:** [`src/CSSOM/CSSCustomMediaRule.php`](../../../src/CSSOM/CSSCustomMediaRule.php)
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).
- **Purpose:** Represents a Media Queries Level 5 `@custom-media` statement at-rule.

## Properties

Read-only magic properties `name` (`string`) and `query` (`MediaList|bool`).

## Public Methods

- `__construct(string $prelude = '', ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES, bool $browserErrorRecovery = false)` — parses `@custom-media <dashed-ident> [ <media-query-list> | true | false ]`.
- `getType(): int` — returns `CSSRule::CUSTOM_MEDIA_RULE` (`29`).
- `getNameText(): string` — returns the `<dashed-ident>` custom media name.
- `getQuery(): MediaList|bool` — returns `bool` when defined as `true`/`false`, or the live synced [`MediaList`](MediaList.md).
- `__clone(): void` — clones the owned `MediaList` (if any) and rebinds its owner/sync callback.
