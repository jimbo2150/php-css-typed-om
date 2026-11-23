# CSSSkewX

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewX`  
**Source:** [`src/TypedOM/Values/CSSSkewX.php`](../../../src/TypedOM/Values/CSSSkewX.php)  
**Purpose:** Models the x-axis `skewX(ax)` transform.

## Public API

No public constants or enum cases. Inherits `parse`/`parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api), and common transform methods `getValues`, `getFunctionName`, `is2D`, `__toString`, `__get`, `__set`, `identityMatrix`, and `multiplyMatrices` from [CSSTransformComponent](CSSTransformComponent.md#public-api). Overrides `setIs2D`, `toMatrix`, and `clone`.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|int|float|string $valueOrFunction, array|CSSNumericValue|int|float|string|null $values = null)`; accepts an angle, or the `skewx` name with exactly one argument; requires an angle dimension and throws `InvalidArgumentException` for invalid function/count/type. Example: `$skew = new CSSSkewX('12deg');`. |
| `getAx` | `public function getAx(): CSSNumericValue`; returns the x skew angle. Example: `$angle = $skew->getAx();`. |
| `setAx` | `public function setAx(CSSNumericValue|int|float $ax): void`; validates/stores an angle and updates serialized arguments; incompatible values throw `InvalidArgumentException`. Example: `$skew->setAx(CSSNumericValue::parse('0.25turn'));`. |
| `setIs2D` | `public function setIs2D(bool $is2D): void`; skew transforms are strictly 2D; passing `false` throws `InvalidArgumentException`. |
| `toMatrix` | `public function toMatrix(): DOMMatrix`; returns a 2D `DOMMatrix` using the tangent of the x angle; non-finite tangents throw `InvalidArgumentException`. Example: `$matrix = $skew->toMatrix();`. |
| `clone` | `public function clone(): static`; copies the angle. Example: `$copy = $skew->clone();`. |
| `__get` | `public function __get(string $name): mixed`; exposes magic read-only `ax`; other names delegate to parent (`is2D`). Example: `$angle = $skew->ax;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; accepts `CSSNumericValue` for magic `ax` through its setter; delegates to parent or throws `Error`. Example: `$skew->ax = CSSNumericValue::parse('8deg');`. |
