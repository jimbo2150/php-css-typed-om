# `CSSInputPreprocessor`

- **PHP type:** `Jimbo2150\PhpCssTypedOm\Parser\CSSInputPreprocessor`
- **Source:** [`src/Parser/CSSInputPreprocessor.php`](../../../src/Parser/CSSInputPreprocessor.php)
- **Kind:** final class of static CSS Syntax Level 3 §3.2–§3.3 preprocessing utilities

Implements the CSS Syntax Module Level 3 input stream preprocessing step with WHATWG Encoding Standard §8.1.1 UTF-8 decoding and `maxBytes` amplification protection.

## Methods

### `preprocess(string $input, ?CSSParserOptions $options = null): string`

Preprocesses raw CSS bytes according to CSS Syntax Level 3 §3.3:

- Strips at most one leading UTF-8 BOM (`\xEF\xBB\xBF`).
- Normalizes `\r\n`, standalone `\r`, and `\f` (`0x0C`) to `\n`.
- Replaces `NUL` (`\x00`), UTF-8-encoded surrogates (`U+D800–U+DFFF`), and maximal subparts of ill-formed UTF-8 sequences with `U+FFFD`.
- Uses a zero-copy fast path for clean ASCII inputs.
- Enforces `$options->maxBytes` both on the raw input length and incrementally during expansion (preventing 3× byte amplification attacks on all-NUL inputs).
- **Throws:** `CSSParserLimitException` when the raw or preprocessed byte count exceeds `$options->maxBytes`.

### `needsPreprocessing(string $input): bool`

Checks without allocating an output string whether `$input` contains a leading UTF-8 BOM, `\r`, `\f`, `\x00`, UTF-8 surrogates, or ill-formed UTF-8 byte sequences.
