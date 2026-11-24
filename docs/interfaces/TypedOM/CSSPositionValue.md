# CSSPositionValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPositionValue`  
**Source:** [`src/TypedOM/Values/CSSPositionValue.php`](../../../src/TypedOM/Values/CSSPositionValue.php)  
**Purpose:** Holds validated horizontal (`x`) and vertical (`y`) coordinates as numeric values or axis-appropriate position keywords.

## Public API

No public constants or enum cases. Declares PHP 8.4 property-hooked `public CSSStyleValue $x` and `public CSSStyleValue $y`. Inherited `parse`/`parseAll` are documented in [CSSStyleValue](CSSStyleValue.md#public-api).

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSStyleValue $x, CSSStyleValue $y)`; accepts numeric 1D lengths (`percentHint` `null` or `'length'`) or percentages (`percentHint` `null`), or `left`/`right`/`center` on x and `top`/`bottom`/`center` on y (cloning keywords). Throws `InvalidArgumentException` for incompatible coordinates. Example: `$position = new CSSPositionValue(CSSNumericValue::parse('25%'), new CSSKeywordValue('center'));`. |
| `fromCssText` | `public static function fromCssText(string $cssText, ?CSSParserOptions $options = null): self`; enforces `$options->maxBytes`, rejects control characters, parses one- or two-component text (normalizing unitless `0` to `0px` and respecting CSS whitespace), fills the omitted coordinate with `center`, normalizes valid vertical-first keyword ordering (including `'center left'` and `'center right'`), and rejects invalid syntax/axis order with `InvalidArgumentException`. Example: `$position = CSSPositionValue::fromCssText('top right');`. |
| `getX` | `public function getX(): CSSStyleValue`; returns the x coordinate (cloning `CSSKeywordValue` so canonical keywords cannot be mutated in place). Example: `$x = $position->getX();`. |
| `setX` | `public function setX(CSSStyleValue $x): void`; validates, normalizes/clones keywords, and replaces x; throws `InvalidArgumentException` if not a length (`percentHint` `null` or `'length'`), percentage (`percentHint` `null`), or x keyword. Example: `$position->setX(new CSSKeywordValue('left'));`. |
| `getY` | `public function getY(): CSSStyleValue`; returns the y coordinate (cloning `CSSKeywordValue`). Example: `$y = $position->getY();`. |
| `setY` | `public function setY(CSSStyleValue $y): void`; validates, normalizes/clones keywords, and replaces y; throws `InvalidArgumentException` if not a compatible length/percentage or y keyword. Example: `$position->setY(CSSNumericValue::parse('12px'));`. |
| `clone` | `public function clone(): static`; deep-clones both coordinates. Example: `$copy = $position->clone();`. |
| `__toString` | `public function __toString(): string`; serializes coordinates separated by one space. Example: `$css = (string) $position;`. |
| `$x` / `$y` (property hooks) | `public CSSStyleValue $x { get; set; }` and `public CSSStyleValue $y { get; set; }`; backed by validated setters and keyword-cloning getters. Undeclared property access via `__get`/`__set` throws `Error`. Example: `$position->y = new CSSKeywordValue('bottom');`. |
