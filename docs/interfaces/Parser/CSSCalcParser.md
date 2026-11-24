# `CSSCalcParser`

- **PHP type:** `Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser`
- **Source:** [`src/Parser/CSSCalcParser.php`](../../../src/Parser/CSSCalcParser.php)
- **Kind:** class with a static CSS `calc()` parser

Parses supported CSS `calc()`, nested `min()`/`max()`/`clamp()`, and arithmetic expressions iteratively into a tree of numeric Typed OM values without recursion across parenthesized groups. Uses a lazy single-pass `buildMathIndex()` structural index for nested expressions, handles parentheses, unary signs, and `+`, `-`, `*`, and `/` with normal arithmetic precedence, and enforces `CSSParserOptions::$maxMathDepth` and `CSSNumericValue::MAX_MATH_NODES` (`100_000`) across tokens, parentheses, commas, and skipped comments.

## Methods

### `parse(string $calcString, ?CSSParserOptions $options = null, int $depth = 0): CSSNumericValue`

Parses the complete input as one `calc(...)` expression and returns its numeric expression tree. The expression must have matching parentheses, valid tokens, sufficient operands, and dimensionally compatible operations. The configured math nesting limit is checked before parsing.

- `$calcString`: complete CSS `calc()` expression; trailing content after the closing parenthesis is rejected.
- `$options`: optional [`CSSParserOptions`](../CSSOM/CSSParserOptions.md) controlling parser resource limits. A default options object is used when omitted.
- `$depth`: current math nesting depth, primarily used by recursive parsing; normally omit it.
- **Returns:** a `CSSNumericValue` such as a unit value or a math expression node.
- **Throws:** `InvalidArgumentException` for malformed syntax, invalid numbers, incompatible dimensions, or exceeded `maxMathDepth`.

```php
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;

$width = CSSCalcParser::parse('calc(100% - 2rem)');
echo $width; // calc(100% - 2rem)
```

### `parseExpression(string $expression, ?CSSParserOptions $options = null, int $depth = 0): CSSNumericValue`

Parses an inner `<calc-sum>` expression (without the outer `calc(...)` wrapper) under the same limit and dimensional validation rules.

- **Throws:** `InvalidArgumentException` / `CSSParserLimitException` on invalid tokens, dimensions, or exceeded limits.

Prefer `CSSNumericValue::parse()` for general numeric input: it accepts unit values and multiple math-function forms, whereas this parser's direct entry point requires a `calc()` expression.
