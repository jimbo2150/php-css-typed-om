# CSSTranslate

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTranslate`  
**Source:** [`src/TypedOM/Values/CSSTranslate.php`](../../../src/TypedOM/Values/CSSTranslate.php)  
**Purpose:** Models `translate`, `translateX/Y/Z`, and `translate3d` values with numeric x/y/z coordinates.

## Public API

No public constants or enum cases. Inherits `parse`/`parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api); common public transform APIs are listed in [CSSTransformComponent](CSSTransformComponent.md#public-api): `getValues`, `getFunctionName`, `is2D`, `setIs2D`, `$is2D`, `__toString`, `identityMatrix`, and `multiplyMatrices`. It overrides `toMatrix` and `clone`.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|int|float|string $xOrFunction, array|CSSNumericValue|int|float|string|null $yOrValues = null, CSSNumericValue|int|float|string|bool|null $z = null, ?bool $is2D = null)`; ordinary arguments make x/y lengths or percentages and z a length (strictly rejecting expressions with non-length `percentHint`); scalars default to px. Passing a numeric/string 3rd `$z` sets `is2D = false` (`translate3d`) unless overridden by `$is2D`; passing a boolean `$z` sets `$is2D` (rejecting conflicting `$z` bool + `$is2D`). A function-name plus value-array form supports `translate`, `translateX`, `translateY`, `translateZ`, `translate3d` with their CSS argument counts. Incompatible units/counts/functions throw `InvalidArgumentException`. Example: `$move = new CSSTranslate('12px', '5%');`. |
| `getX` / `setX` | `public function getX(): CSSNumericValue`; `public function setX(CSSNumericValue|int|float $x): void`; get or validate/set x as length or percentage, then synchronize serialized arguments. Setter throws `InvalidArgumentException` for another numeric type. Example: `$move->setX(CSSNumericValue::parse('2em'));`. |
| `getY` / `setY` | `public function getY(): CSSNumericValue`; `public function setY(CSSNumericValue|int|float $y): void`; get or validate/set y as length or percentage. Example: `$y = $move->getY();`. |
| `getZ` / `setZ` | `public function getZ(): CSSNumericValue`; `public function setZ(CSSNumericValue|int|float $z): void`; get or validate/set z as a length. Example: `$move->setZ(CSSNumericValue::parse('10px'));`. |
| `toMatrix` | `public function toMatrix(): DOMMatrix`; returns a 4x4 `DOMMatrix` translation transform. Concrete values must be convertible to lengths; conversion errors propagate as `InvalidArgumentException`. Example: `$matrix = $move->toMatrix();`. |
| `clone` | `public function clone(): static`; clones coordinates iteratively, preserving single-argument `translate(x)` serialization and the 2D flag. Example: `$copy = $move->clone();`. |
| `$x` / `$y` / `$z` (property hooks) | `public CSSNumericValue $x { get; set; }`, `$y`, `$z`; read/write coordinates via validating setters. Unknown properties via `__get`/`__set` throw `Error`. Example: `$move->x = CSSNumericValue::parse('4px');`. |
