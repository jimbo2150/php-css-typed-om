# `MediaList`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\MediaList`
- **Source:** [`src/CSSOM/MediaList.php`](../../../src/CSSOM/MediaList.php)
- **Purpose:** Transactional mutable list of validated, deduplicated CSS media query strings.
- **Implements:** `ArrayAccess<int, string>`, `Countable`, `IteratorAggregate<int, string>`.

## Properties

Magic `mediaText` reads/writes normalized list text; `length` is read-only. No public constants or fields are declared.

## Public Methods

| Signature | Parameters / behavior | Throws / result | Example |
|---|---|---|---|
| `__construct(string $mediaText = '', ?Closure $onChange = null, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Parses initial text under `$maxBytes` (retaining `$maxBytes` when detached/ownerless); optional callback receives normalized replacement text before internal state is committed. | `InvalidArgumentException` / `CSSParserLimitException` for invalid syntax or exceeded caps. | `$media = new MediaList('screen, print');` |
| `cloneWithCallback(?Closure $onChange): self` | `@internal` helper that returns an independent copy wired to a new `$onChange` callback (used when cloning owning rules). | Returns cloned `MediaList`. | `$copy = $media->cloneWithCallback(null);` |
| `__clone(): void` | Clears `$onChange` and owner reference while preserving cloned queries and `$maxBytes`. | | `$copy = clone $media;` |
| `getMediaText(): string` | Joins stored media queries using `, `. | | `$media->getMediaText();` |
| `setMediaText(string $mediaText): void` | Enforces owning stylesheet/rule `maxBytes` (default `DEFAULT_MAX_BYTES`), validates and splits comma-separated queries up to `CSSStyleValue::MAX_LIST_ITEMS`, removes empty and comment-only entries plus case-insensitive duplicates, invokes callback, then replaces list. | `InvalidArgumentException` / `CSSParserLimitException`. | `$media->setMediaText('screen, print');` |
| `appendMedium(string $medium): void` | Requires one non-empty, non-comment-only query without top-level commas; appends only if no case-insensitive match exists and `MAX_LIST_ITEMS` / owner `maxBytes` limits are respected. Callback runs before commit. | `InvalidArgumentException` / `CSSParserLimitException`. | `$media->appendMedium('speech');` |
| `deleteMedium(string $medium): void` | Removes first case-insensitive trimmed match; callback runs before commit. Missing entry throws `InvalidArgumentException`. | `InvalidArgumentException` when absent or invalid. | `$media->deleteMedium('print');` |
| `contains(string $medium): bool` | Case-insensitive membership test after trimming. | | `$media->contains('SCREEN');` |
| `item(int $index): string` | Returns indexed entry or `''`. | | `$media->item(0);` |
| `count(): int` | Returns query count. | | `count($media);` |
| `offsetExists(mixed $offset): bool` | True for an integer offset holding an entry. | | `isset($media[0]);` |
| `offsetGet(mixed $offset): ?string` | Integer offset returns query or `null`; other types return `null`. | | `$media[0];` |
| `offsetSet(mixed $offset, mixed $value): void` | Always throws `InvalidArgumentException`; use `appendMedium()` or `mediaText`. | | `$media[] = 'print'; // throws` |
| `offsetUnset(mixed $offset): void` | Always throws `InvalidArgumentException`; use `deleteMedium()` or `mediaText`. | | `unset($media[0]); // throws` |
| `getIterator(): Traversable` | Iterates query strings in order. | | `foreach ($media as $query) {}` |
| `__toString(): string` | Returns normalized media text. | | `(string) $media;` |
| `__get(string $name): mixed` | Supports `mediaText` and `length`; unknown names throw `Error`. | | `$media->length;` |
| `__isset(string $name): bool` | True for `mediaText` and `length`. | | `isset($media->mediaText);` |
| `__set(string $name, mixed $value): void` | String `mediaText` assignment calls `setMediaText()`; unsupported property/type throws `Error`. | | `$media->mediaText = 'print';` |
