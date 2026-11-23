# CSSScale

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSScale`  
**Source:** [`src/TypedOM/Values/CSSScale.php`](../../../src/TypedOM/Values/CSSScale.php)  
**Purpose:** Models `scale`, axis-specific scale, and `scale3d` components.

## Public API

No public constants or enum cases. Inherits `parse`/`parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api), and common transform methods `getValues`, `getFunctionName`, `is2D`, `setIs2D`, `__toString`, `__get`, `__set`, `identityMatrix`, and `multiplyMatrices` from [CSSTransformComponent](CSSTransformComponent.md#public-api). Overrides `toMatrix` and `clone`.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|int|float|string $xOrFunction, array|CSSNumericValue|int|float|string|null $yOrValues = null, CSSNumericValue|int|float|string|null $z = null)`; ordinary x/y/z inputs and supported named-function-plus-array forms are converted to unitless numeric factors (with optional strict unit-category + `percentHint` checking). Omitted y copies x for direct construction, while omitted z defaults to 1 (`is2D = true`); passing a third `z` argument sets `is2D = false` unconditionally (W3C Typed OM). Bad function names/counts or incompatible types throw `InvalidArgumentException`. Example: `$scale = new CSSScale(1.5, 2);`. |
| `getX` / `getY` / `getZ` | `public function getX(): CSSNumericValue`; `public function getY(): CSSNumericValue`; `public function getZ(): CSSNumericValue`; returns the unitless factors. Example: `$x = $scale->getX();`. |
| `setX` / `setY` / `setZ` | `public function setX(CSSNumericValue|int|float $x): void`; `public function setY(CSSNumericValue|int|float $y): void`; `public function setZ(CSSNumericValue|int|float $z): void`; validates a unitless factor and updates serialized arguments; invalid types throw `InvalidArgumentException`. Example: `$scale->setY(0.75);`. |
| `toMatrix` | `public function toMatrix(): DOMMatrix`; returns a 4x4 `DOMMatrix` scale transform; requires concrete unitless numeric values. Example: `$matrix = $scale->toMatrix();`. |
| `clone` | `public function clone(): static`; clones scale factors and preserves single-argument `scale(x)` serialization. Example: `$copy = $scale->clone();`. |
| `__get` | `public function __get(string $name): mixed`; exposes read-only magic `x`, `y`, and `z`; other names delegate to the parent (`is2D`). Example: `$factor = $scale->x;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; accepts a numeric value or int/float for magic `x`, `y`, or `z`; invalid names/types throw `Error` or numeric validation errors. Example: `$scale->z = 1.2;`. |
