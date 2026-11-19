# CSSMatrixComponent

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMatrixComponent`  
**Source:** [`src/TypedOM/Values/CSSMatrixComponent.php`](../../../src/TypedOM/Values/CSSMatrixComponent.php)  
**Purpose:** Represents a `matrix()` or `matrix3d()` transform and converts its six- or sixteen-number argument list to a 4x4 column-major matrix.

## Public API

No public constants or enum cases. Inherits `parse`/`parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api) and common transform methods (`getValues`, `getFunctionName`, `is2D`, `setIs2D`, `__toString`, `__get`, `__set`, `identityMatrix`, `multiplyMatrices`) from [CSSTransformComponent](CSSTransformComponent.md#public-api). Its `clone` and `toMatrix` override the base implementations.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(array|string $matrixOrFunction, array|bool|null $valuesOrIs2D = true)`; accepts a six-/sixteen-element numeric matrix array, optionally followed by a boolean 2D flag; or a `matrix`/`matrix3d` function name and argument array. Arguments must be finite numbers (or unitless `CSSUnitValue`s in function form); invalid size, name, units, or values throw `InvalidArgumentException`. Example: `$matrix = new CSSMatrixComponent([1, 0, 0, 1, 12, 8]);`. |
| `getMatrix` | `public function getMatrix(): array`; returns the six- or sixteen-float component list. Example: `$values = $matrix->getMatrix();`. |
| `setMatrix` | `public function setMatrix(array $matrix): void`; validates six or sixteen finite numeric elements, stores float values, and updates serialized arguments; throws `InvalidArgumentException` otherwise. Example: `$matrix->setMatrix([1, 0, 0, 1, 0, 0]);`. |
| `toMatrix` | `public function toMatrix(): array`; expands six-element 2D components into a 4x4 column-major matrix, or returns the stored 16-element matrix. Example: `$matrix4x4 = $matrix->toMatrix();`. |
| `clone` | `public function clone(): static`; returns a new component preserving its matrix and 2D flag. Example: `$copy = $matrix->clone();`. |
| `__get` | `public function __get(string $name): mixed`; exposes `matrix` as a read-only virtual property; other names are delegated to the parent (`is2D` only). Example: `$values = $matrix->matrix;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; accepts an array for virtual `matrix` and delegates other writes to the parent's `is2D` setter; invalid names/types throw `Error`. Example: `$matrix->matrix = [1, 0, 0, 1, 4, 5];`. |
