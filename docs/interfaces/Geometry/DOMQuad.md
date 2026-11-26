# `DOMQuad`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\Geometry\DOMQuad`
- **Source:** [`src/Geometry/DOMQuad.php`](../../../src/Geometry/DOMQuad.php)
- **Purpose:** W3C Geometry Interfaces Level 1 quadrilateral backed by four owned mutable [`DOMPoint`](DOMPoint.md) vertices.

## Properties

Read-only `[SameObject]` magic properties `p1`, `p2`, `p3`, `p4` returning the live owned `DOMPoint` instances (`Error` on reassignment).

## Public Methods

- `__construct(DOMPointReadOnly|array $p1 = [], DOMPointReadOnly|array $p2 = [], DOMPointReadOnly|array $p3 = [], DOMPointReadOnly|array $p4 = [])` — always clones vertex inputs into owned `DOMPoint` objects.
- `fromRect(DOMRectReadOnly|array $other = []): self` — constructs vertices `(x, y)`, `(x + width, y)`, `(x + width, y + height)`, `(x, y + height)`.
- `fromQuad(DOMQuad|array $other = []): self`
- `getP1()`, `getP2()`, `getP3()`, `getP4(): DOMPoint`
- `getBounds(): DOMRect` — computes axis-aligned bounding `DOMRect`, validating finite dimensions (`right - left`, `bottom - top`).
- `toJSON(): array{p1: array{x: float, y: float, z: float, w: float}, p2: ..., p3: ..., p4: ...}`
- `__clone(): void` — deep-clones all four `DOMPoint` vertices.
