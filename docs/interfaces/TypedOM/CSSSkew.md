# CSSSkew

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkew`  
**Source:** [`src/TypedOM/Values/CSSSkew.php`](../../../src/TypedOM/Values/CSSSkew.php)  
**Purpose:** Models a two-axis `skew(ax, ay)` transform using angles.

## Public API

No public constants or enum cases. Inherits `parse`/`parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api), and common transform methods `getValues`, `getFunctionName`, `is2D`, `setIs2D`, `__toString`, `__get`, `__set`, `identityMatrix`, and `multiplyMatrices` from [CSSTransformComponent](CSSTransformComponent.md#public-api). Overrides `toMatrix` and `clone`.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|int|float|string $axOrFunction, array|CSSNumericValue|int|float|string|null $ayOrValues = null)`; accepts direct angles or a `skew` function name with one or two arguments. Angles must be angle-dimensional; bad count/function/type throws `InvalidArgumentException`. Example: `$skew = new CSSSkew('10deg', '5deg');`. |
| `getAx` / `getAy` | `public function getAx(): CSSNumericValue`; `public function getAy(): CSSNumericValue`; returns each angle. Example: `$angleX = $skew->getAx();`. |
| `setAx` / `setAy` | `public function setAx(CSSNumericValue $ax): void`; `public function setAy(CSSNumericValue $ay): void`; validates and replaces the corresponding angle and updates serialized arguments. Example: `$skew->setAy(CSSNumericValue::parse('0.1turn'));`. |
| `toMatrix` | `public function toMatrix(): array`; computes the 2D matrix using the tangent of each angle; angle conversion failures throw `InvalidArgumentException`. Example: `$matrix = $skew->toMatrix();`. |
| `clone` | `public function clone(): static`; deep-copies both angles. Example: `$copy = $skew->clone();`. |
| `__get` | `public function __get(string $name): mixed`; magic read-only `ax` and `ay`; other names delegate to parent (`is2D`). Example: `$angle = $skew->ax;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; accepts `CSSNumericValue` for `ax`/`ay` via validating setters; otherwise delegates to parent or throws `Error`. Example: `$skew->ax = CSSNumericValue::parse('15deg');`. |
