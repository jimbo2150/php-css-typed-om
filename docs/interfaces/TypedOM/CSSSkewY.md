# CSSSkewY

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewY`  
**Source:** [`src/TypedOM/Values/CSSSkewY.php`](../../../src/TypedOM/Values/CSSSkewY.php)  
**Purpose:** Models the y-axis `skewY(ay)` transform.

## Public API

No public constants or enum cases. Inherits `parse`/`parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api), and common transform methods `getValues`, `getFunctionName`, `is2D`, `setIs2D`, `__toString`, `__get`, `__set`, `identityMatrix`, and `multiplyMatrices` from [CSSTransformComponent](CSSTransformComponent.md#public-api). Overrides `toMatrix` and `clone`.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|int|float|string $valueOrFunction, array|CSSNumericValue|int|float|string|null $values = null)`; accepts an angle, or the `skewy` name with exactly one argument; requires an angle dimension and throws `InvalidArgumentException` for invalid function/count/type. Example: `$skew = new CSSSkewY('12deg');`. |
| `getAy` | `public function getAy(): CSSNumericValue`; returns the y skew angle. Example: `$angle = $skew->getAy();`. |
| `setAy` | `public function setAy(CSSNumericValue $ay): void`; validates/stores an angle and updates serialized arguments; incompatible values throw `InvalidArgumentException`. Example: `$skew->setAy(CSSNumericValue::parse('0.25turn'));`. |
| `toMatrix` | `public function toMatrix(): array`; returns a 2D skew matrix using the tangent of the y angle. Example: `$matrix = $skew->toMatrix();`. |
| `clone` | `public function clone(): static`; copies the angle. Example: `$copy = $skew->clone();`. |
| `__get` | `public function __get(string $name): mixed`; exposes magic read-only `ay`; other names delegate to parent (`is2D`). Example: `$angle = $skew->ay;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; accepts `CSSNumericValue` for magic `ay` through its setter; delegates to parent or throws `Error`. Example: `$skew->ay = CSSNumericValue::parse('8deg');`. |
