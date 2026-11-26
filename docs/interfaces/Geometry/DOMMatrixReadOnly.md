# `DOMMatrixReadOnly`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly`
- **Source:** [`src/Geometry/DOMMatrixReadOnly.php`](../../../src/Geometry/DOMMatrixReadOnly.php)
- **Implements:** `ArrayAccess<int, float>`, `Countable`, `Stringable`
- **Purpose:** Read-only 4x4 column-major transformation matrix implementing W3C Geometry Interfaces Level 1.

## Properties

Public declared property `public protected(set) bool $is2D`; read-only magic properties `a`, `b`, `c`, `d`, `e`, `f`, `m11`..`m44`, and `isIdentity` (with matching `get*()` / `is2D()` / `isIdentity()` methods).

## Public Methods

- `__construct(DOMMatrixReadOnly|array|string|null $init = null)` — accepts `null` (identity), a `DOMMatrixReadOnly`, a 6- or 16-element numeric list, a `DOMMatrixInit` dictionary array (`a..f`, `m11..m44`, `is2D`, optional bool `isIdentity`), or a CSS transform string (stripping balanced comments before checking `''` / `'none'` for 2D identity) pre-checked against `CSSParserOptions::default()->maxBytes`. Determines `is2D` from `$explicitIs2D ?? elementsAre2D`. Control bytes in string or array inputs throw `InvalidArgumentException`.
- `fromMatrix(DOMMatrixReadOnly|array $other = []): static`
- `fromFloat32Array(array $values): static`, `fromFloat64Array(array $values): static`, `fromElements(array $elements, bool $is2D): static`, `multiplyElements(array $left, array $right, bool $leftIs2D = false, bool $rightIs2D = false): array` (static; array factories reject control characters and non-finite inputs)
- Immutable operations returning [`DOMMatrix`](DOMMatrix.md): `translate()`, `scale()`, `scaleNonUniform()`, `scale3d()`, `rotate()`, `rotateFromVector()`, `rotateAxisAngle()` (magnitude pre-scaled to avoid overflow/underflow), `skewX()`, `skewY()`, `multiply()`, `flipX()`, `flipY()`, `inverse()` (throws `InvalidArgumentException` on singular matrix).
- `transformPoint(DOMPointReadOnly|array $point = []): DOMPoint` — transforms a point or `DOMPointInit` dictionary and returns a [`DOMPoint`](DOMPoint.md) implementing `ArrayAccess<string, float>`.
- `toFloat32Array(): array`, `toFloat64Array(): array`, `toJSON(): array`, `__toString(): string` (`matrix(...)` or `matrix3d(...)`).
- `ArrayAccess` allows reading indices `0..15`; writes throw `InvalidArgumentException`.
