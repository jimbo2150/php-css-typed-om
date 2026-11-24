# CSSKeywordValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue`  
**Source:** [`src/TypedOM/Values/CSSKeywordValue.php`](../../../src/TypedOM/Values/CSSKeywordValue.php)  
**Purpose:** Mutable typed value for one CSS identifier/keyword.

## Public API

No declared public constants or enum cases. Inherited parsing APIs are [`CSSStyleValue::parse`](CSSStyleValue.md#public-api) and `parseAll`; this class implements inherited string and clone contracts.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(string $keyword)`; enforces `CSSParserOptions::DEFAULT_MAX_BYTES`, trims CSS whitespace, and validates a single CSS identifier via `decodeIdentAt()` (preserving CSS escape syntax while rejecting bare `'--'` and decoded control bytes). Throws `InvalidArgumentException` / `CSSParserLimitException` for invalid or oversized identifiers. Example: `$value = new CSSKeywordValue('auto');`. |
| `getKeyword` | `public function getKeyword(): string`; returns the stored keyword. Example: `$keyword = $value->getKeyword();`. |
| `getValue` | `public function getValue(): string`; alias returning the keyword. Example: `$raw = $value->getValue();`. |
| `setValue` | `public function setValue(string $value): void`; enforces `DEFAULT_MAX_BYTES`, trims, validates as a single CSS identifier (rejecting bare `'--'` and decoded control bytes) and replaces the keyword; throws `InvalidArgumentException` / `CSSParserLimitException` if invalid. Example: `$value->setValue('max-content');`. |
| `__toString` | `public function __toString(): string`; returns the keyword verbatim. Example: `$css = (string) $value;`. |
| `clone` | `public function clone(): static`; creates a new instance with the current keyword. Example: `$copy = $value->clone();`. |

## Properties

`public string $value { get; set; }` is a PHP 8.4 hooked property backed by validated identifier normalization (`setValue()`). Unknown properties via `__get`/`__set` throw `Error`.
