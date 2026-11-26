# `CSSToken`

- **PHP type:** `Jimbo2150\PhpCssTypedOm\Parser\CSSToken`
- **Source:** [`src/Parser/CSSToken.php`](../../../src/Parser/CSSToken.php)
- **Kind:** `final readonly class`

Immutable representation of a single CSS Syntax Module Level 3 §4.2 token emitted by `CSSTokenizer`.

## Public Constants and Properties

- `HASH_ID = 'id'`, `HASH_UNRESTRICTED = 'unrestricted'`
- `NUMERIC_INTEGER = 'integer'`, `NUMERIC_NUMBER = 'number'`
- `CSSTokenType $type` — token kind.
- `string $value` — decoded value for ident, function, at-keyword, hash, string, url, and delim tokens (`''` for whitespace, structural, bad, and EOF tokens).
- `int $start`, `int $end` — inclusive/exclusive byte offsets in `CSSTokenizer::getPreprocessedSource()`. For `<function-token "url">` in `url(  "foo")`, `$end` stops immediately after `(` so `getRawSlice()` returns `'url('`.
- `?string $flag` — `'id'|'unrestricted'` on `Hash`, `'integer'|'number'` on `Number` and `Dimension`, and `null` on `Percentage` and all other tokens per CSS Syntax L3 §4.2.
- `?float $numericValue` — IEEE-754 float for `Number`, `Percentage`, and `Dimension` (may be `INF`/`-INF` for overflow such as `1e999`, distinct from `CSSUnitValue`'s finite invariant).
- `?string $unit` — decoded unit identifier for `Dimension`.
- `?string $repr` — raw numeric text for `Number`, `Percentage`, and `Dimension`.

## Methods

- `isHashId(): bool` — true when `$type === CSSTokenType::Hash && $flag === self::HASH_ID`.
- `isInteger(): bool` — true when `$type` is `Number` or `Dimension` and `$flag === self::NUMERIC_INTEGER`.
- `isEof(): bool` — true when `$type === CSSTokenType::Eof`.
- `getRawSlice(string $source): string` — returns `substr($source, $this->start, $this->end - $this->start)`.
