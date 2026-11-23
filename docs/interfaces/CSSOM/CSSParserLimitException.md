# `CSSParserLimitException`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException`
- **Source:** [`src/CSSOM/CSSParserLimitException.php`](../../../src/CSSOM/CSSParserLimitException.php)
- **Extends:** `InvalidArgumentException`
- **Purpose:** Thrown by strict parsing, Typed OM, and rendering entry points when a configured resource limit (`maxBytes`, `maxDepth`, `maxRules`, `maxMathDepth`, or `maxDeclarations`, or `CSSRenderer` nesting limit) is exceeded.

Because `CSSParserLimitException` extends `InvalidArgumentException`, callers catching `InvalidArgumentException` continue to handle all limit failures while callers that need to distinguish DoS/resource limits from syntax errors can catch `CSSParserLimitException` directly.
