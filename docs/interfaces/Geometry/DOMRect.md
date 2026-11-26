# `DOMRect`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\Geometry\DOMRect`
- **Source:** [`src/Geometry/DOMRect.php`](../../../src/Geometry/DOMRect.php)
- **Inheritance:** `DOMRectReadOnly`; inherited APIs: [`DOMRectReadOnly`](DOMRectReadOnly.md).
- **Purpose:** Mutable W3C Geometry Interfaces Level 1 rectangle companion.

## Public Methods

- `setX(float $value): void`, `setY(float $value): void`, `setWidth(float $value): void`, `setHeight(float $value): void` — atomically validate finite values and finite `x + width` / `y + height` sums.
- Magic `__set()` supports writable `x`, `y`, `width`, `height`; writing computed `top`, `right`, `bottom`, `left` throws `Error`.
