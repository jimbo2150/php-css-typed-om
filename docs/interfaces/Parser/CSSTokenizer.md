# `CSSTokenizer`

- **PHP type:** `Jimbo2150\PhpCssTypedOm\Parser\CSSTokenizer`
- **Source:** [`src/Parser/CSSTokenizer.php`](../../../src/Parser/CSSTokenizer.php)
- **Implements:** `IteratorAggregate<int, CSSToken>`
- **Public constant:** `DEFAULT_MAX_TOKENS = 1_000_000`

Iterative, single-pass CSS Syntax Module Level 3 §4.2–§4.3 tokenizer. Public usage always applies CSS Syntax L3 §3.3 preprocessing via `CSSInputPreprocessor::preprocess()` before scanning.

## Public Methods

- `__construct(string $source, ?CSSParserOptions $options = null)` — preprocesses `$source` under `$options->maxBytes` and prepares the token cursor.
- `nextToken(): CSSToken` — advances and returns the next `CSSToken` (returns `CSSTokenType::Eof` once exhausted, idempotently thereafter).
- `peekToken(): CSSToken` — 1-token lookahead without advancing `nextToken()`.
- `getPreprocessedSource(): string` — returns the §3.3-preprocessed source string indexed by token byte offsets.
- `getParseErrors(): array` — returns `list<array{message: string, offset: int}>` diagnostics (unclosed comments/strings/URLs, `<bad-string-token>`, `<bad-url-token>`, invalid escapes), capped by `$options->maxErrors`.
- `getIterator(): Traversable` — lazy generator yielding non-EOF `CSSToken` instances.
- `tokenize(string $source, ?CSSParserOptions $options = null, bool $includeEof = false, ?int $maxTokens = self::DEFAULT_MAX_TOKENS): array` (static) — eagerly tokenizes into a `list<CSSToken>` bounded by `$maxTokens` (throws `InvalidArgumentException` if `$maxTokens < 1`, or `CSSParserLimitException` on overflow).
