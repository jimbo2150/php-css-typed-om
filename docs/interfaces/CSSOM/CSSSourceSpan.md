# `CSSSourceSpan`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSSourceSpan`
- **Source:** [`src/CSSOM/CSSSourceSpan.php`](../../../src/CSSOM/CSSSourceSpan.php)
- **Implements:** `Stringable`
- **Type:** `final readonly class`
- **Purpose:** Immutable source location metadata (0-based byte offsets and 1-based line / UTF-8 code-point column coordinates) attached when `CSSParserOptions::$trackSpans` is enabled.

## Public Properties

- `public int $startOffset`
- `public int $endOffset`
- `public int $startLine`
- `public int $startColumn`
- `public int $endLine`
- `public int $endColumn`

## Public Methods

- `__construct(int $startOffset, int $endOffset, int $startLine, int $startColumn, int $endLine, int $endColumn)`
- `toArray(): array{startOffset: int, endOffset: int, startLine: int, startColumn: int, endLine: int, endColumn: int}`
- `__toString(): string` — returns `"$startLine:$startColumn-$endLine:$endColumn"`.
