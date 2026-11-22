# CSSMatrixComponent

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMatrixComponent`  
**Source:** [`src/TypedOM/Values/CSSMatrixComponent.php`](../../../src/TypedOM/Values/CSSMatrixComponent.php)  
**Purpose:** Represents a `matrix()` or `matrix3d()` transform backed by [`DOMMatrix`](../Geometry/DOMMatrix.md).

## Public API

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(DOMMatrixReadOnly|array|string $matrixOrFunction, array|bool|null $valuesOrOptions = null)`; accepts a `DOMMatrixReadOnly`, a 6- or 16-element numeric array (optionally followed by a bool or `['is2D' => bool]`), or a `matrix`/`matrix3d` function name and argument list. |
| `getMatrix` | `public function getMatrix(): DOMMatrix`; returns the backing `DOMMatrix`. |
| `setMatrix` | `public function setMatrix(DOMMatrixReadOnly|array $matrix): void`; validates and clones the supplied matrix. |
| `getFunctionName` / `__toString` | Dynamically reflects live `is2D()` and `matrix` state (`matrix(a, b, c, d, e, f)` when 2D, `matrix3d(...)` when 3D). |
| `toMatrix` | `public function toMatrix(): DOMMatrix`; returns a cloned `DOMMatrix` (flattened to 2D when `is2D()` is true). |
| `clone` | Deep-clones the component and its matrix. |
| `__get` / `__set` | Exposes `matrix` (`DOMMatrix`) and delegates `is2D` to `CSSTransformComponent`. |
