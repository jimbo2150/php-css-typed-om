# `DOMPointReadOnly`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\Geometry\DOMPointReadOnly`
- **Source:** [`src/Geometry/DOMPointReadOnly.php`](../../../src/Geometry/DOMPointReadOnly.php)
- **Implements:** `ArrayAccess<string, float>`
- **Purpose:** Read-only 2D/3D/4D homogeneous coordinate point implementing W3C Geometry Interfaces Level 1.

## Properties

Read-only magic properties `x`, `y`, `z`, `w` (`float`), paired with `getX()`, `getY()`, `getZ()`, `getW()`.

## Public Methods

- `__construct(float $x = 0.0, float $y = 0.0, float $z = 0.0, float $w = 1.0)` — rejects non-finite `NAN`/`INF` coordinates (`InvalidArgumentException`).
- `fromPoint(DOMPointReadOnly|array $other = []): static` — creates a `static` instance from another point or `DOMPointInit` dictionary (`['x', 'y', 'z', 'w']`).
- `matrixTransform(DOMMatrixReadOnly|array|null $matrix = null): DOMPoint` — applies `DOMMatrixReadOnly::fromMatrix($matrix)->transformPoint($this)` when non-null or returns a `DOMPoint` copy.
- `toJSON(): array{x: float, y: float, z: float, w: float}`
- `ArrayAccess` allows reading `'x' | 'y' | 'z' | 'w'`; `offsetSet()` / `offsetUnset()` throw `InvalidArgumentException` and `__set()` throws `Error`.
