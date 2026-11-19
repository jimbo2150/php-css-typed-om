# CSSTranslate

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTranslate`  
**Source:** [`src/TypedOM/Values/CSSTranslate.php`](../../../src/TypedOM/Values/CSSTranslate.php)  
**Purpose:** Models `translate`, `translateX/Y/Z`, and `translate3d` values with numeric x/y/z coordinates.

## Public API

No public constants or enum cases. Inherits `parse`/`parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api); common public transform APIs are listed in [CSSTransformComponent](CSSTransformComponent.md#public-api): `getValues`, `getFunctionName`, `is2D`, `setIs2D`, `__toString`, `__get`, `__set`, `identityMatrix`, and `multiplyMatrices`. It overrides `toMatrix` and `clone`.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|int|float|string $xOrFunction, array|CSSNumericValue|int|float|string|null $yOrValues = null, CSSNumericValue|int|float|string|bool|null $z = null, ?bool $is2D = null)`; ordinary arguments make x/y lengths or percentages and z a length; scalars default to px. A function-name plus value-array form supports `translate`, `translateX`, `translateY`, `translateZ`, `translate3d` with their CSS argument counts. Incompatible units/counts/functions throw `InvalidArgumentException`. Example: `$move = new CSSTranslate('12px', '5%');`. |
| `getX` / `setX` | `public function getX(): CSSNumericValue`; `public function setX(CSSNumericValue $x): void`; get or validate/set x as length or percentage, then synchronize serialized arguments. Setter throws `InvalidArgumentException` for another numeric type. Example: `$move->setX(CSSNumericValue::parse('2em'));`. |
| `getY` / `setY` | `public function getY(): CSSNumericValue`; `public function setY(CSSNumericValue $y): void`; get or validate/set y as length or percentage. Example: `$y = $move->getY();`. |
| `getZ` / `setZ` | `public function getZ(): CSSNumericValue`; `public function setZ(CSSNumericValue $z): void`; get or validate/set z as a length. Example: `$move->setZ(CSSNumericValue::parse('10px'));`. |
| `toMatrix` | `public function toMatrix(): array`; returns a 4x4 column-major translation matrix. Concrete values must be convertible to lengths; conversion errors propagate as `InvalidArgumentException`. Example: `$matrix = $move->toMatrix();`. |
| `clone` | `public function clone(): static`; clones coordinates and preserves the 2D flag. Example: `$copy = $move->clone();`. |
| `__get` | `public function __get(string $name): mixed`; magic read-only `x`, `y`, `z`; other names are delegated to the parent (`is2D`). Example: `$x = $move->x;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; accepts `CSSNumericValue` for magic `x`, `y`, or `z`, delegates parent properties, and otherwise throws `Error`. Example: `$move->x = CSSNumericValue::parse('4px');`. |
