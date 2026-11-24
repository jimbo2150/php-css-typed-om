# `CSSOpaqueRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSOpaqueRule`
- **Source:** [`src/CSSOM/CSSOpaqueRule.php`](../../../src/CSSOM/CSSOpaqueRule.php)
- **Purpose:** Preserves source text for rules the parser does not model structurally.
- **Inheritance:** `CSSRule`; inherited public API: [`CSSRule`](CSSRule.md).

## Properties

The constructor-promoted `source` field is private and has no accessor. Standard CSSRule virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $source, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Stores the supplied source text verbatim after checking `$maxBytes`, disallowed control characters, and balanced syntax (`CSSSyntaxScanner::isBalanced()`) (`CSSParserLimitException` / `InvalidArgumentException` if violated). | `$rule = new CSSOpaqueRule('@unknown value;');` |
| `getType(): int` | Always returns `CSSRule::UNKNOWN_RULE`. | `$rule->getType();` |
| `getCssText(): string` | Returns the stored source verbatim. | `$rule->getCssText();` |

All inherited public methods are documented in [`CSSRule`](CSSRule.md).
