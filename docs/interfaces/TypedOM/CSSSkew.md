# CSSSkew

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkew`  
**Source:** [`src/TypedOM/Values/CSSSkew.php`](../../../src/TypedOM/Values/CSSSkew.php)  
**Purpose:** Models a two-axis `skew(ax, ay)` transform using angles.

## Public API

No public constants or enum cases. Inherits `parse`/`parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api), and common transform methods `getValues`, `getFunctionName`, `is2D`, `$is2D`, `__toString`, `identityMatrix`, and `multiplyMatrices` from [CSSTransformComponent](CSSTransformComponent.md#public-api). Overrides `setIs2D`, `toMatrix`, and `clone`.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|int|float|string $axOrFunction, array|CSSNumericValue|int|float|string|null $ayOrValues = null)`; accepts direct angles or a `skew` function name with one or two arguments. Angles must be angle-dimensional; bad count/function/type throws `InvalidArgumentException`. Example: `$skew = new CSSSkew('10deg', '5deg');`. |
| `getAx` / `getAy` | `public function getAx(): CSSNumericValue`; `public function getAy(): CSSNumericValue`; returns each angle. Example: `$angleX = $skew->getAx();`. |
| `setAx` / `setAy` | `public function setAx(CSSNumericValue|int|float $ax): void`; `public function setAy(CSSNumericValue|int|float $ay): void`; validates and replaces the corresponding angle and updates serialized arguments. Example: `$skew->setAy(CSSNumericValue::parse('0.1turn'));`. |
| `setIs2D` | `public function setIs2D(bool $is2D): void`; skew transforms are strictly 2D; passing `false` throws `InvalidArgumentException`. |
| `toMatrix` | `public function toMatrix(): DOMMatrix`; computes the 2D `DOMMatrix` using the tangent of each angle; non-finite tangents or angle conversion failures throw `InvalidArgumentException`. Example: `$matrix = $skew->toMatrix();`. |
| `clone` | `public function clone(): static`; deep-copies both angles. Example: `$copy = $skew->clone();`. |
| `$ax` / `$ay` (property hooks) | `public CSSNumericValue $ax { get; set; }` and `public CSSNumericValue $ay { get; set; }`; read/write validated skew angles via setters. Unknown properties via `__get`/`__set` throw `Error`. Example: `$skew->ax = CSSNumericValue::parse('15deg');`. |
