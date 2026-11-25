# `CSSImportRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSImportRule`
- **Source:** [`src/CSSOM/CSSImportRule.php`](../../../src/CSSOM/CSSImportRule.php)
- **Purpose:** Represents `@import` metadata; it does not fetch or load the referenced stylesheet.
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited public APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic read-only `href`, `media`, `mediaText`, `styleSheet`, `layerName`, and `supportsText` expose parsed metadata. The stylesheet getter always yields `null`; inherited at-rule properties also apply.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $prelude, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Checks `$maxBytes` before scanning; requires non-empty, non-comment-only text; strictly parses tail clauses (`layer` / `layer(name)` before optional `supports(...)` before media list, stripping comments inside clauses, rejecting duplicate or empty clauses, trailing junk, or tokens exceeding `CSSStyleValue::MAX_LIST_ITEMS`) using O(N) balanced delimiter matching. Owns a `MediaList` that inherits owner byte budgets, enforces total combined `@import` prelude length against the owning rule/sheet `maxBytes`, and blocks smuggling `layer`/`layer(...)`/`supports(...)` via `MediaList` mutations. Throws `InvalidArgumentException` / `CSSParserLimitException` on invalid/over-limit preludes. | `$rule = new CSSImportRule('url("theme.css") layer(theme) screen');` |
| `getHref(): string` | Returns the first prelude token with `url(...)` and surrounding quotes unwrapped. | `$rule->getHref();` |
| `getMedia(): MediaList` | Returns the parsed mutable media list. | `$rule->getMedia()->appendMedium('print');` |
| `getMediaText(): string` | Returns normalized media-list text. | `$rule->getMediaText();` |
| `getLayerName(): ?string` | Returns `null` when absent, `''` for bare `layer`, or the name in `layer(name)`. | `$rule->getLayerName();` |
| `getSupportsText(): ?string` | Returns text within a parsed `supports(...)`, or `null` when absent. | `$rule->getSupportsText();` |
| `getType(): int` | Returns `CSSRule::IMPORT_RULE`. | `$rule->getType();` |
| `getStyleSheet(): ?CSSStyleSheet` | Always returns `null`; this model records import metadata without loading sheets. | `$rule->getStyleSheet();` |
| `__get(string $name): mixed` | Exposes the six virtual properties above and delegates other names to `CSSAtRule::__get()`. | `$rule->mediaText;` |
| `__isset(string $name): bool` | True for `href`, `media`, `mediaText`, and non-null `layerName`/`supportsText` (`false` for `styleSheet`), otherwise delegates to `CSSAtRule::__isset()`. | `isset($rule->href);` |
| `__clone(): void` | Clones rule state and rebinds a cloned `MediaList` callback + owner reference cleanly. | `$copy = clone $rule;` |

Other inherited methods are documented on [`CSSAtRule`](CSSAtRule.md) and [`CSSRule`](CSSRule.md).
