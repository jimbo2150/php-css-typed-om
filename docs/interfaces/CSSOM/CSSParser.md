# `CSSParser`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSParser`
- **Source:** [`src/CSSOM/CSSParser.php`](../../../src/CSSOM/CSSParser.php)
- **Purpose:** Static parser for stylesheets, individual top-level/nested rules, and declaration blocks.
- **Constants:** No public constants; parser rule-category constants are private.

## Public Methods

| Signature | Parameters / behavior | Throws / result | Example |
|---|---|---|---|
| `parseStylesheet(string $cssText, ?CSSParserOptions $options = null, bool $throwOnLimit = false): CSSStyleSheet` | `$cssText` is parsed with recovery; `$options` configures resource limits. Diagnostics and byte offsets are retained on the sheet, or `CSSParserLimitException` is thrown when `$throwOnLimit` is `true` and a limit is exceeded. If input exceeds `maxBytes`, returns an empty sheet with an error (or throws when `$throwOnLimit` is `true`). | Returns a mutable sheet. | `$sheet = CSSParser::parseStylesheet('a { color: red }');` |
| `parseDeclarationBlock(string $cssText, ?CSSParserOptions $options = null): CSSStyleDeclaration` | Strictly parses a declaration block under `$options` limits (`maxBytes`, `maxDeclarations`, `maxErrors`). | Throws `InvalidArgumentException` (or `CSSParserLimitException`) when malformed or over-limit. | `$style = CSSParser::parseDeclarationBlock('color: red');` |
| `parseSingleRule(string $cssText, ?CSSParserOptions $options = null, int $initialDepth = 0, int $initialRuleCount = 0): CSSRule` | Requires exactly one valid top-level rule; optional limits apply (including caller depth/rule offsets). Rejects control bytes (including inside comments) strictly. | Throws `InvalidArgumentException` (or `CSSParserLimitException` on limit exhaustion) for zero/multiple rules, control bytes, or parse errors. | `$rule = CSSParser::parseSingleRule('a { color: red }');` |
| `parseSingleNestedRule(string $cssText, ?CSSParserOptions $options = null, int $initialDepth = 0, int $initialRuleCount = 0): CSSRule` | Parses one rule intended inside a style/grouping block, including nested qualified rules (excluding non-nestable at-rules such as `@keyframes`). | Throws `InvalidArgumentException` (or `CSSParserLimitException`) for malformed input or exceeded limits. | `$rule = CSSParser::parseSingleNestedRule('&:hover { color: red }');` |
| `parseSingleKeyframeRule(string $cssText, ?CSSParserOptions $options = null, int $initialDepth = 0, int $initialRuleCount = 0): CSSKeyframeRule` | Parses a single keyframe rule inside `@keyframes`. | Throws `InvalidArgumentException` (or `CSSParserLimitException`) for malformed input or exceeded limits. | `$kf = CSSParser::parseSingleKeyframeRule('from { opacity: 0; }');` |
| `parseSingleMarginRule(string $cssText, ?CSSParserOptions $options = null, int $initialDepth = 0, int $initialRuleCount = 0): CSSMarginRule` | Parses a single `@page` margin rule strictly (rejecting control bytes including inside comments). | Throws `InvalidArgumentException` (or `CSSParserLimitException`) for malformed input or exceeded limits. | `$mr = CSSParser::parseSingleMarginRule('@top-left { content: "x"; }');` |

All methods are static; the class declares no public constructor or instance API.
