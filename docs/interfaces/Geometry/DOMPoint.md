# `DOMPoint`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\Geometry\DOMPoint`
- **Source:** [`src/Geometry/DOMPoint.php`](../../../src/Geometry/DOMPoint.php)
- **Inheritance:** `DOMPointReadOnly`; inherited APIs: [`DOMPointReadOnly`](DOMPointReadOnly.md).
- **Purpose:** Mutable W3C Geometry Interfaces Level 1 point companion.

## Public Methods

- Inherits `__construct()`, `fromPoint()`, getters, `matrixTransform()`, `toJSON()`, and `ArrayAccess<string, float>` from [`DOMPointReadOnly`](DOMPointReadOnly.md).
- `setX(float $value): void`, `setY(float $value): void`, `setZ(float $value): void`, `setW(float $value): void` — finite-validated coordinate setters.
- Magic `__set()` and `offsetSet()` allow mutating `'x' | 'y' | 'z' | 'w'`; non-numeric values or non-finite floats throw `InvalidArgumentException`.
