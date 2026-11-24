# `CSSStyleSheet`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet`
- **Source:** [`src/CSSOM/CSSStyleSheet.php`](../../../src/CSSOM/CSSStyleSheet.php)
- **Purpose:** Mutable standalone CSS stylesheet with CSS rules and parse diagnostics.
- **Inheritance:** `StyleSheet`; inherited public API: [`StyleSheet`](StyleSheet.md).

## Properties

Magic read-only `cssRules`, `cssText`, `parseErrors`, and `baseURL`; stylesheet metadata properties and writable `disabled` are inherited from `StyleSheet`.

## Public Methods

| Signature | Parameters / behavior | Throws / result | Example |
|---|---|---|---|
| `__construct(?string $href = null, string $title = '', MediaList|string|null $media = null, ?CSSRule $ownerRule = null, ?StyleSheet $parentStyleSheet = null, bool $disabled = false, ?string $baseURL = null)` | Initializes inherited metadata (`media` accepts a `MediaList` or media-query string, `disabled`, and optional `baseURL`) and an empty `CSSRuleList`. Rejects control characters in `$baseURL`. | | `$sheet = new CSSStyleSheet(media: 'screen', disabled: false, baseURL: 'https://example.test/');` |
| `getBaseURL(): ?string` | Returns the configured base URL or `null`. | | `$sheet->getBaseURL();` |
| `parse(string $cssText, ?CSSParserOptions $options = null): self` (static) | Parses with recovery and stores diagnostics on returned sheet. | Returns a `CSSStyleSheet`. | `$sheet = CSSStyleSheet::parse('a { color: red }');` |
| `getCssRules(): CSSRuleList` | Returns the owned mutable list. | | `$sheet->getCssRules();` |
| `getParseErrors(): array` | Returns ordered `{message, offset}` diagnostics. | | `$sheet->getParseErrors();` |
| `getCssText(): string` | Joins each rule's CSS text with newlines while rejecting cyclic/DAG-shared rule trees (`LogicException`). | | `$sheet->getCssText();` |
| `insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int` | Strictly parses exactly one rule, inserts at index, and returns index. Throws `OutOfBoundsException` for invalid index or `InvalidArgumentException`/`CSSParserLimitException` for malformed/over-limit CSS; no mutation occurs before parsing succeeds. | | `$sheet->insertRule('a { color: red }');` |
| `deleteRule(int $index = 0): void` | Removes rule at index. Throws `OutOfBoundsException` when invalid. | | `$sheet->deleteRule(0);` |
| `replaceSync(string $cssText, ?CSSParserOptions $options = null): void` | Atomically replaces all rules and resets/updates parse diagnostics (`$parseErrors` and `$errorsTruncated`). When `$options` is `null`, parsing inherits the stylesheet's configured limits; when non-`null`, those limits and `$maxErrors` become the stylesheet's new limits. Enforces `CSSParserOptions` resource limits with `CSSParserLimitException`. | | `$sheet->replaceSync('b { color: blue }');` |
| `recordParseError(string $message, int $offset): void` | Appends a diagnostic record up to `$maxErrors` (default `CSSParserOptions::DEFAULT_MAX_ERRORS`), emitting one truncation note after reaching the limit. | | `$sheet->recordParseError('Unexpected token', 4);` |
| `__clone(): void` | Deep-clones owned `CSSRuleList` and rule subtrees iteratively (`O(1)` PHP stack) with cloned metadata (including rebound `MediaList` owner), invalidated cached options, and recomputed total rule and declaration counts. | | `$copy = clone $sheet;` |
| `__toString(): string` | Returns `getCssText()`. | | `(string) $sheet;` |
| `__get(string $name): mixed` | Exposes `cssRules`, `cssText`, `parseErrors`, and `baseURL`; other names delegate to `StyleSheet::__get()`. | | `$sheet->cssRules;` |
| `__isset(string $name): bool` | Returns `true` for `cssRules`, `cssText`, `parseErrors`, non-null `baseURL`, or parent virtual properties. | | `isset($sheet->cssRules);` |

Inherited metadata getters, `setDisabled()`, `__set()`, and remaining `StyleSheet` APIs are documented on [`StyleSheet`](StyleSheet.md).
