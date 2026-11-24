# `CSSSyntaxScanner`

- **PHP type:** `Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner`
- **Source:** [`src/Parser/CSSSyntaxScanner.php`](../../../src/Parser/CSSSyntaxScanner.php)
- **Kind:** final class of static CSS scanning utilities
- **Public constants:** `CSS_WHITESPACE = " \t\n\r\f"`, `IDENT_ESCAPE_REGEX`.

Scans CSS fragments while recognizing quoted strings, escapes, comments, and nested `()`, `[]`, and `{}` blocks. Offsets and slice boundaries are byte offsets. Methods do not generally reject malformed fragments unless noted; use `assertValidFragment()` when validation is needed.

## Methods

### `splitTopLevel(string $source, string $delimiter, int $start = 0, ?int $end = null): array`

Splits a source slice at each single-byte delimiter that occurs outside strings, comments, and nested blocks. Delimiters within those constructs do not split the text.

- `$source`: complete source string.
- `$delimiter`: exactly one byte to split on.
- `$start`: inclusive starting byte offset (clamped to zero or greater).
- `$end`: optional exclusive end offset, clamped to the source length.
- **Returns:** list of source substrings, retaining whitespace and empty segments.
- **Throws:** `InvalidArgumentException` when the delimiter is not one byte.

```php
$parts = CSSSyntaxScanner::splitTopLevel('a, fn(b, c), "d,e"', ',');
// ['a', ' fn(b, c)', ' "d,e"']
```

### `splitTopLevelWhitespace(string $source, int $start = 0, ?int $end = null): array`

Splits on whitespace outside strings, comments, and nested blocks. Separating whitespace/comments are discarded; each returned token retains its own source spelling.

- `$source`: source string to split.
- `$start`: inclusive starting byte offset.
- `$end`: optional exclusive end offset.
- **Returns:** list of nonempty top-level whitespace-delimited fragments.

```php
$tokens = CSSSyntaxScanner::splitTopLevelWhitespace('1px calc(2px + 3px) "a b"');
// ['1px', 'calc(2px + 3px)', '"a b"']
```

### `findTopLevel(string $source, string $delimiter, int $start = 0, ?int $end = null): ?int`

Returns the byte offset of the first single-byte delimiter outside strings, comments, and nested blocks, or `null` if none is found.

- `$source`: source string to scan.
- `$delimiter`: one-byte delimiter.
- `$start`: inclusive scan offset.
- `$end`: optional exclusive scan boundary.
- **Throws:** `InvalidArgumentException` when `$delimiter` is not one byte.

```php
$colon = CSSSyntaxScanner::findTopLevel('url("a:b"): red', ':');
// 10
```

### `findNextTopLevelDelimiter(string $source, array $delimiters, int $start = 0, ?int $end = null): ?array`

Finds the first byte matching any supplied delimiter outside strings, comments, and nested blocks.

- `$source`: source string to scan.
- `$delimiters`: list of delimiter bytes.
- `$start`: inclusive scan offset.
- `$end`: optional exclusive scan boundary.
- **Returns:** `['offset' => int, 'delimiter' => string]`, or `null` if no match.

```php
$match = CSSSyntaxScanner::findNextTopLevelDelimiter('a{b:c}; d', [';', '{']);
// ['offset' => 1, 'delimiter' => '{']
```

### `findNextTopLevelDelimiterBytes(string $source, string $delimiterBytes, int $start = 0, ?int $end = null): ?array`

String-mask variant of `findNextTopLevelDelimiter()` accepting single-byte delimiters as a contiguous string (e.g. `';{}'`).

- **Returns:** `['offset' => int, 'delimiter' => string]`, or `null` if no match.

### `findMatchingUrlEnd(string $source, int $openParenOffset, ?int $end = null): ?int`

Finds the closing `)` offset of a `url(...)` argument (both unquoted URLs and quoted `url("..." /* comment */)` forms) starting at `$openParenOffset` (`'('`), ignoring parentheses inside CSS escapes and strings, or `null` if unclosed.

### `findMatchingDelimiter(string $source, int $openOffset, ?int $end = null): ?int`

Finds the matching close byte for an opening `(`, `[`, or `{`, respecting nested blocks, quoted strings, escapes, and comments. Invalid offsets, a non-opening character, mismatched blocks, and unterminated content return `null`.

- `$source`: complete source string.
- `$openOffset`: byte offset of an opening delimiter.
- `$end`: optional exclusive scan boundary.
- **Returns:** matching close byte offset or `null`.

```php
$close = CSSSyntaxScanner::findMatchingDelimiter('fn(a, [b])', 2); // 9
```

### `findMatchingBlockEnd(string $source, int $openBraceOffset, ?int $end = null): ?int`

Finds the matching closing `}` for a rule/at-rule block starting at `$openBraceOffset`, respecting strings, escapes, comments, `url(...)`, and nested `{}`/`()`/`[]` while ignoring stray unmatched `)`/`]` tokens per CSS Syntax Level 3 error recovery. Returns `null` if unclosed before `$end`.

### `removeComments(string $source, int $start = 0, ?int $end = null): string`

Removes CSS comments outside quoted strings and `url(...)`, collapsing contiguous comment runs and inserting a separator space only when adjacent tokens would otherwise merge or re-tokenize (per CSS Syntax Level 3 tokenization boundaries). Scans and returns only the requested byte slice.

- `$source`: source string.
- `$start`: inclusive starting byte offset.
- `$end`: optional exclusive ending byte offset.

```php
$clean = CSSSyntaxScanner::removeComments('div/* note */span'); // 'div span'
$compound = CSSSyntaxScanner::removeComments('div/* note */.foo'); // 'div.foo'
```

### `isBalanced(string $source, int $start = 0, ?int $end = null): bool`

Checks whether brackets, strings, comments, and escapes in a byte slice are properly balanced/closed. Newlines inside quoted strings and a trailing escape are rejected.

- `$source`: source string to check.
- `$start`: inclusive starting byte offset.
- `$end`: optional exclusive ending byte offset.
- **Returns:** `true` only if all tracked constructs close correctly.

```php
var_dump(CSSSyntaxScanner::isBalanced('fn("[")')); // true
var_dump(CSSSyntaxScanner::isBalanced('[)'));       // false
```

### `hasUnclosedCommentOrString(string $source, int $start = 0, ?int $end = null): bool`

Checks only for an unclosed comment/string or a trailing escape in a source slice; it does not validate bracket nesting.

- `$source`: source string.
- `$start`: inclusive starting byte offset.
- `$end`: optional exclusive ending byte offset.
- **Returns:** `true` if a comment/string is unclosed or the slice ends in an escape.

```php
CSSSyntaxScanner::hasUnclosedCommentOrString('"unfinished'); // true
```

### `hasControlCharacters(string $source): bool`

Detects disallowed ASCII control bytes: NUL, bytes `0x01`–`0x08`, `0x0B`, `0x0E`–`0x1F`, and `0x7F`. CSS whitespace (`\t`, `\n`, `\f` `0x0C`, `\r`) is excluded from this check.

- `$source`: value to check.
- **Returns:** `true` if one of the disallowed bytes occurs.

```php
CSSSyntaxScanner::hasControlCharacters("color:\0red"); // true
```

### `assertValidFragment(string $source, string $label, array $forbiddenTopLevel = [';', '{', '}'], array $forbiddenAnywhere = []): void`

Validates that a CSS fragment has no forbidden control bytes and is balanced, and rejects configured delimiters outside strings/comments. Default forbidden delimiters apply only at top level, allowing nested blocks. Throws `InvalidArgumentException` on invalid input.

- `$source`: fragment to validate.
- `$label`: human-readable fragment label included in exception messages.
- `$forbiddenTopLevel`: list of bytes disallowed only when not inside a block; defaults to `;`, `{`, and `}`.
- `$forbiddenAnywhere`: list of bytes disallowed anywhere outside strings/comments.

```php
CSSSyntaxScanner::assertValidFragment('.card:hover', 'selector');
// Throws InvalidArgumentException for a top-level declaration separator:
CSSSyntaxScanner::assertValidFragment('.card; body', 'selector');
```

### `findLastTopLevelImportant(string $source, int $start = 0, ?int $end = null): ?int`

Returns the byte offset of the `!` belonging to a trailing top-level `!important` flag (with CSS escape awareness), or `null` when absent.

### `decodeCssIdentEscapes(string $ident): string`

Decodes CSS backslash escapes (hexadecimal code points with optional trailing whitespace and literal escapes) inside an identifier token.

### `trimCssWhitespace(string $value): string`

Trims only CSS Syntax Level 3 whitespace bytes (`" \t\n\r\f"`), preserving `\x0B` (vertical tab) and control characters for structural validation.
