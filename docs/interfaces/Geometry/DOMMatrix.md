# `DOMMatrix`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix`
- **Source:** [`src/Geometry/DOMMatrix.php`](../../../src/Geometry/DOMMatrix.php)
- **Inheritance:** [`DOMMatrixReadOnly`](DOMMatrixReadOnly.md)
- **Purpose:** Mutable 4x4 column-major transformation matrix returned by `CSSTransformValue::toMatrix()` and `CSSTransformComponent::toMatrix()`.

## Additional Public Methods

- Mutable element setters `setA()`..`setF()`, `setM11()`..`setM44()`, mutable magic `__set()` for `a..f` / `m11..m44`, and mutable `offsetSet(0..15, float)` (automatically clearing `is2D` when 3D components become non-2D).
- In-place mutators returning `$this`: `multiplySelf()`, `preMultiplySelf()`, `translateSelf()`, `scaleSelf()`, `scale3dSelf()`, `rotateSelf()`, `rotateFromVectorSelf()`, `rotateAxisAngleSelf()`, `skewXSelf()`, `skewYSelf()`, `invertSelf()` (throws `InvalidArgumentException` on zero/non-finite determinant or finite-element overflow, remaining atomic on failure), and `setMatrixValue(string $transformList)`.
