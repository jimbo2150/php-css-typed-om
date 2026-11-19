# CSSRotate

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSRotate`  
**Source:** [`src/TypedOM/Values/CSSRotate.php`](../../../src/TypedOM/Values/CSSRotate.php)  
**Purpose:** Models `rotate`, axis-specific rotations, and `rotate3d` with a numeric axis and angle.

## Public API

No public constants or enum cases. Inherits `parse`/`parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api), and common transform methods `getValues`, `getFunctionName`, `is2D`, `setIs2D`, `__toString`, `__get`, `__set`, `identityMatrix`, and `multiplyMatrices` from [CSSTransformComponent](CSSTransformComponent.md#public-api). Overrides `toMatrix` and `clone`.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|int|float|string $angleOrFunction, array|CSSNumericValue|int|float|string|null $valuesOrY = null, CSSNumericValue|int|float|string|null $z = null, CSSNumericValue|int|float|string|null $angle = null)`; one angle constructs `rotate()`. Function plus array accepts supported rotate functions; alternatively x/y/z plus angle creates `rotate3d`. Axes must be unitless and non-zero; angle must be an angle. Invalid function/count/types throw `InvalidArgumentException`. Example: `$rotation = new CSSRotate('30deg');`. |
| `getAngle` / `setAngle` | `public function getAngle(): CSSNumericValue`; `public function setAngle(CSSNumericValue $angle): void`; get or validate/set the angle, requiring the angle dimension; setter synchronizes serialization. Example: `$rotation->setAngle(CSSNumericValue::parse('0.5turn'));`. |
| `getX` / `setX` | `public function getX(): CSSNumericValue`; `public function setX(CSSNumericValue $x): void`; get or validate/set unitless x-axis component and reject a zero resulting axis. Example: `$x = $rotation->getX();`. |
| `getY` / `setY` | `public function getY(): CSSNumericValue`; `public function setY(CSSNumericValue $y): void`; get or validate/set unitless y-axis component and reject a zero resulting axis. Example: `$rotation->setY(CSSNumericValue::parse('1'));`. |
| `getZ` / `setZ` | `public function getZ(): CSSNumericValue`; `public function setZ(CSSNumericValue $z): void`; get or validate/set unitless z-axis component and reject a zero resulting axis. Example: `$z = $rotation->getZ();`. |
| `toMatrix` | `public function toMatrix(): array`; computes a normalized-axis 4x4 column-major rotation matrix; requires concrete convertible numbers/angles and throws `InvalidArgumentException` when conversion is unsupported or the axis is zero. Example: `$matrix = $rotation->toMatrix();`. |
| `clone` | `public function clone(): static`; reconstructs the same function and arguments. Example: `$copy = $rotation->clone();`. |
| `__get` | `public function __get(string $name): mixed`; exposes magic read-only `angle`, `x`, `y`, `z`; all other names are delegated to the parent (`is2D`). Example: `$degrees = $rotation->angle;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; accepts `CSSNumericValue` for `angle`, `x`, `y`, or `z`, validating dimensions and nonzero axis; delegates parent property writes or throws `Error`. Example: `$rotation->angle = CSSNumericValue::parse('45deg');`. |
