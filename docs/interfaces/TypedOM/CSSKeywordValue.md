# CSSKeywordValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue`  
**Source:** [`src/TypedOM/Values/CSSKeywordValue.php`](../../../src/TypedOM/Values/CSSKeywordValue.php)  
**Purpose:** Mutable typed value for one CSS identifier/keyword.

## Public API

No declared public constants or enum cases. Inherited parsing APIs are [`CSSStyleValue::parse`](CSSStyleValue.md#public-api) and `parseAll`; this class implements inherited string and clone contracts.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(string $keyword)`; validates and stores a trimmed non-empty CSS identifier. Throws `InvalidArgumentException` for invalid identifiers. Example: `$value = new CSSKeywordValue('auto');`. |
| `getKeyword` | `public function getKeyword(): string`; returns the stored keyword. Example: `$keyword = $value->getKeyword();`. |
| `getValue` | `public function getValue(): string`; alias returning the keyword. Example: `$raw = $value->getValue();`. |
| `setValue` | `public function setValue(string $value): void`; trims, validates as a CSS identifier and replaces the keyword; throws `InvalidArgumentException` if invalid. Example: `$value->setValue('max-content');`. |
| `__toString` | `public function __toString(): string`; returns the keyword verbatim. Example: `$css = (string) $value;`. |
| `__get` | `public function __get(string $name): mixed`; only virtual property `value` is readable, yielding `getValue()`; throws `Error` for other names. Example: `$keyword = $value->value;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; accepts a string assigned to virtual property `value` and delegates validation to `setValue`; other names or non-string values throw `Error`. Example: `$value->value = 'none';`. |
| `clone` | `public function clone(): static`; creates a new instance with the current keyword. Example: `$copy = $value->clone();`. |

## Properties

`value` is a magic read/write property backed by `getValue()`/`setValue()`; it is not a declared public property.
