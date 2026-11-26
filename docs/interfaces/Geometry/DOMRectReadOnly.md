# `DOMRectReadOnly`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\Geometry\DOMRectReadOnly`
- **Source:** [`src/Geometry/DOMRectReadOnly.php`](../../../src/Geometry/DOMRectReadOnly.php)
- **Purpose:** Read-only W3C Geometry Interfaces Level 1 axis-aligned rectangle with computed edge coordinates.

## Properties

Read-only magic properties `x`, `y`, `width`, `height`, plus computed `top` (`min(y, y + height)`), `right` (`max(x, x + width)`), `bottom` (`max(y, y + height)`), `left` (`min(x, x + width)`).

## Public Methods

- `__construct(float $x = 0.0, float $y = 0.0, float $width = 0.0, float $height = 0.0)` — validates finite inputs and finite `x + width` / `y + height` bounds.
- `fromRect(DOMRectReadOnly|array $other = []): static`
- `getX()`, `getY()`, `getWidth()`, `getHeight()`, `getTop()`, `getRight()`, `getBottom()`, `getLeft(): float`
- `toJSON(): array{x: float, y: float, width: float, height: float, top: float, right: float, bottom: float, left: float}`
