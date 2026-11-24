# CSSImageValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSImageValue`  
**Source:** [`src/TypedOM/Values/CSSImageValue.php`](../../../src/TypedOM/Values/CSSImageValue.php)  
**Purpose:** Immutable typed wrapper for a single supported CSS image-function expression.

## Public API

No public constants or enum cases. Inherited `parse`/`parseAll` are documented in [CSSStyleValue](CSSStyleValue.md#public-api).

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(string $image)`; enforces `CSSParserOptions::DEFAULT_MAX_BYTES`, trims and validates a balanced, control-free `url()` (including CSS-escaped `url(` identifiers), `image()`, `image-set()`, `cross-fade()`, `paint()`, `element()`, or (repeating) linear/radial/conic gradient expression with no trailing tokens (rejecting `;`, `{`, `}` inside non-`url()` functions). Throws `InvalidArgumentException` / `CSSParserLimitException` otherwise. Example: `$image = new CSSImageValue('url("logo.svg")');`. |
| `getImage` | `public function getImage(): string`; returns the normalized stored image text. Example: `$source = $image->getImage();`. |
| `clone` | `public function clone(): static`; returns a validated copy. Example: `$copy = $image->clone();`. |
| `__toString` | `public function __toString(): string`; returns stored CSS text. Example: `$css = (string) $image;`. |
| `$image` (property) | `public readonly string $image`; read-only normalized image text. Undeclared property accesses via `__get`/`__set` throw `Error`. Example: `$text = $image->image;`. |
