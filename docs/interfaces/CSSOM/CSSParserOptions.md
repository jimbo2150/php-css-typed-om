# `CSSParserOptions`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions`
- **Source:** [`src/CSSOM/CSSParserOptions.php`](../../../src/CSSOM/CSSParserOptions.php)
- **Purpose:** Immutable resource limits for CSS parsing.
- **Type:** `final readonly class`.

## Public Constants and Properties

| Name | Type / default | Meaning |
|---|---|---|
| `DEFAULT_MAX_BYTES` | `int`, `16 * 1024 * 1024` | Default input byte ceiling. |
| `DEFAULT_MAX_DEPTH` | `int`, `256` | Default nesting-depth ceiling. |
| `DEFAULT_MAX_RULES` | `int`, `100_000` | Default total rule-count ceiling. |
| `DEFAULT_MAX_MATH_DEPTH` | `int`, `256` | Default math-expression depth ceiling. |
| `DEFAULT_MAX_ERRORS` | `int`, `1_000` | Default recorded parse-error diagnostic ceiling. |
| `DEFAULT_MAX_DECLARATIONS` | `int`, `500_000` | Default parsed declaration ceiling. |
| `$maxBytes` | `?int`, default `DEFAULT_MAX_BYTES` | Maximum CSS input size in bytes; `null` disables this limit. |
| `$maxDepth` | `?int`, default `DEFAULT_MAX_DEPTH` | Maximum nesting depth; `null` disables this limit. |
| `$maxRules` | `?int`, default `DEFAULT_MAX_RULES` | Maximum recorded rule count; `null` disables this limit. |
| `$maxMathDepth` | `?int`, default `DEFAULT_MAX_MATH_DEPTH` | Maximum math nesting depth; `null` disables this limit. |
| `$maxErrors` | `?int`, default `DEFAULT_MAX_ERRORS` | Maximum parse-error diagnostics retained before truncation; `null` disables this limit. |
| `$maxDeclarations` | `?int`, default `DEFAULT_MAX_DECLARATIONS` | Maximum parsed declarations accepted across a stylesheet or block; `null` disables this limit. |

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(?int $maxBytes = self::DEFAULT_MAX_BYTES, ?int $maxDepth = self::DEFAULT_MAX_DEPTH, ?int $maxRules = self::DEFAULT_MAX_RULES, ?int $maxMathDepth = self::DEFAULT_MAX_MATH_DEPTH, ?int $maxErrors = self::DEFAULT_MAX_ERRORS, ?int $maxDeclarations = self::DEFAULT_MAX_DECLARATIONS)` | Accepts positive integers or `null` for each limit. Throws `InvalidArgumentException` if a provided value is less than 1. | `$options = new CSSParserOptions(maxRules: 500);` |
| `default(): self` (static) | Returns the shared immutable default `CSSParserOptions` instance. | `$options = CSSParserOptions::default();` |
| `unlimited(): self` (static) | Creates options with all six limits set to `null`. | `$options = CSSParserOptions::unlimited();` |
