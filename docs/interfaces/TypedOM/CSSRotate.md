# CSSRotate

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSRotate`  
**Source:** [`src/TypedOM/Values/CSSRotate.php`](../../../src/TypedOM/Values/CSSRotate.php)  
**Purpose:** Models `rotate`, axis-specific rotations, and `rotate3d` with a numeric axis and angle.

## Public API

No public constants or enum cases. Inherits `parse`/`parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api), and common transform methods `getValues`, `getFunctionName`, `is2D`, `setIs2D`, `$is2D`, `__toString`, `identityMatrix`, and `multiplyMatrices` from [CSSTransformComponent](CSSTransformComponent.md#public-api). Overrides `toMatrix` and `clone`.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|int|float|string $angleOrFunction, array|CSSNumericValue|int|float|string|null $valuesOrY = null, CSSNumericValue|int|float|string|null $z = null, CSSNumericValue|int|float|string|null $angle = null)`; one angle constructs `rotate()`. Function plus array accepts supported rotate functions; alternatively x/y/z plus angle creates `rotate3d`. Axes must be unitless and non-zero (`InvalidArgumentException` on `[0, 0, 0]`); non-positive `(0, 0, -1)` promotes to 3D `rotate3d`; angle must be an angle. Invalid function/count/types throw `InvalidArgumentException`. Example: `$rotation = new CSSRotate('30deg');`. |
| `getAngle` / `setAngle` | `public function getAngle(): CSSNumericValue`; `public function setAngle(CSSNumericValue|int|float $angle): void`; get or validate/set the angle, requiring the angle dimension; setter synchronizes serialization. Example: `$rotation->setAngle(CSSNumericValue::parse('0.5turn'));`. |
| `getX` / `setX` | `public function getX(): CSSNumericValue`; `public function setX(CSSNumericValue|int|float $x): void`; get or validate/set unitless x-axis component and reject a zero resulting axis. Example: `$x = $rotation->getX();`. |
| `getY` / `setY` | `public function getY(): CSSNumericValue`; `public function setY(CSSNumericValue|int|float $y): void`; get or validate/set unitless y-axis component and reject a zero resulting axis. Example: `$rotation->setY(CSSNumericValue::parse('1'));`. |
| `getZ` / `setZ` | `public function getZ(): CSSNumericValue`; `public function setZ(CSSNumericValue|int|float $z): void`; get or validate/set unitless z-axis component and reject a zero resulting axis. Example: `$z = $rotation->getZ();`. |
| `setIs2D` / `getFunctionName` / `getValues` | `setIs2D(bool $is2D)` toggles 2D/3D mode while `getFunctionName()` and `getValues()` synchronize live axis/angle mutations before returning (`rotate`, `rotatex`, `rotatey`, `rotatez`, `rotate3d`). Example: `$rotation->getFunctionName();`. |
| `toMatrix` | `public function toMatrix(): DOMMatrix`; computes a normalized-axis 4x4 `DOMMatrix` rotation using overflow/underflow-safe magnitude pre-scaling with `-0.0` sin/cos values canonicalized to `+0.0`; requires concrete convertible numbers/angles and throws `InvalidArgumentException` when conversion is unsupported or the axis is zero. Example: `$matrix = $rotation->toMatrix();`. |
| `__clone` / `clone` | `public function __clone(): void`; `public function clone(): static`; deep-clones angle and axis coordinates cleanly. Example: `$copy = $rotation->clone();`. |
| `$angle` / `$x` / `$y` / `$z` (property hooks) | `public CSSNumericValue $angle { get; set; }`, `$x`, `$y`, `$z`; read/write validated angle and axis components via setters. Unknown properties via `__get`/`__set` throw `Error`. Example: `$rotation->angle = CSSNumericValue::parse('45deg');`. |
