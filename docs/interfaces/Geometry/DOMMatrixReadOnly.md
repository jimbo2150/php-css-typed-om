# `DOMMatrixReadOnly`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly`
- **Source:** [`src/Geometry/DOMMatrixReadOnly.php`](../../../src/Geometry/DOMMatrixReadOnly.php)
- **Implements:** `ArrayAccess<int, float>`, `Countable`, `Stringable`
- **Purpose:** Read-only 4x4 column-major transformation matrix implementing W3C Geometry Interfaces Level 1.

## Properties

Read-only magic properties: `a`, `b`, `c`, `d`, `e`, `f`, `m11`..`m44`, `is2D`, and `isIdentity` (with matching `get*()` / `is2D()` / `isIdentity()` methods).

## Public Methods

- `__construct(DOMMatrixReadOnly|array|string|null $init = null)` — accepts `null` (identity), a `DOMMatrixReadOnly`, a 6- or 16-element numeric list, a `DOMMatrixInit` dictionary array (`a..f`, `m11..m44`, `is2D`), or a CSS transform string (`''` / `'none'` yield 2D identity).
- `fromMatrix(DOMMatrixReadOnly|array $other = []): static`
- `fromFloat32Array(array $values): static`, `fromFloat64Array(array $values): static`, `fromElements(array $elements, bool $is2D): static`
- Immutable operations returning [`DOMMatrix`](DOMMatrix.md): `translate()`, `scale()`, `scaleNonUniform()`, `scale3d()`, `rotate()`, `rotateFromVector()`, `rotateAxisAngle()`, `skewX()`, `skewY()`, `multiply()`, `flipX()`, `flipY()`, `inverse()` (throws `InvalidArgumentException` on singular matrix).
- `transformPoint(array $point = []): array` — returns `['x' => float, 'y' => float, 'z' => float, 'w' => float]`.
- `toFloat32Array(): array`, `toFloat64Array(): array`, `toJSON(): array`, `__toString(): string` (`matrix(...)` or `matrix3d(...)`).
- `ArrayAccess` allows reading indices `0..15`; writes throw `InvalidArgumentException`.
