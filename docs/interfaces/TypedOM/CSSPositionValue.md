# CSSPositionValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPositionValue`  
**Source:** [`src/TypedOM/Values/CSSPositionValue.php`](../../../src/TypedOM/Values/CSSPositionValue.php)  
**Purpose:** Holds validated horizontal (`x`) and vertical (`y`) coordinates as numeric values or axis-appropriate position keywords.

## Public API

No public constants or enum cases. Inherited `parse`/`parseAll` are documented in [CSSStyleValue](CSSStyleValue.md#public-api).

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSStyleValue $x, CSSStyleValue $y)`; accepts numeric length/percentage/derived values, or `left`/`right`/`center` on x and `top`/`bottom`/`center` on y. Throws `InvalidArgumentException` for incompatible coordinates. Example: `$position = new CSSPositionValue(CSSNumericValue::parse('25%'), new CSSKeywordValue('center'));`. |
| `fromCssText` | `public static function fromCssText(string $cssText): self`; parses one- or two-component text, fills the omitted coordinate with `center`, normalizes valid vertical-first keyword ordering, and rejects invalid syntax/axis order with `InvalidArgumentException`. Example: `$position = CSSPositionValue::fromCssText('top right');`. |
| `getX` | `public function getX(): CSSStyleValue`; returns the x coordinate. Example: `$x = $position->getX();`. |
| `setX` | `public function setX(CSSStyleValue $x): void`; validates and replaces x; throws `InvalidArgumentException` if not a length/percentage-compatible numeric value or x keyword. Example: `$position->setX(new CSSKeywordValue('left'));`. |
| `getY` | `public function getY(): CSSStyleValue`; returns the y coordinate. Example: `$y = $position->getY();`. |
| `setY` | `public function setY(CSSStyleValue $y): void`; validates and replaces y; throws `InvalidArgumentException` if not a length/percentage-compatible numeric value or y keyword. Example: `$position->setY(CSSNumericValue::parse('12px'));`. |
| `clone` | `public function clone(): static`; deep-clones both coordinates. Example: `$copy = $position->clone();`. |
| `__toString` | `public function __toString(): string`; serializes coordinates separated by one space. Example: `$css = (string) $position;`. |
| `__get` | `public function __get(string $name): mixed`; exposes magic read-only `x` and `y`; unknown properties throw `Error`. Example: `$x = $position->x;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; accepts `CSSStyleValue` for magic `x` or `y` writes using their validators; otherwise throws `Error`. Example: `$position->y = new CSSKeywordValue('bottom');`. |
