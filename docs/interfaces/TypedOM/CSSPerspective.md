# CSSPerspective

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPerspective`  
**Source:** [`src/TypedOM/Values/CSSPerspective.php`](../../../src/TypedOM/Values/CSSPerspective.php)  
**Purpose:** Models `perspective(length)` or `perspective(none)` as a 3D transform component.

## Public API

No public constants or enum cases. Inherits `parse`/`parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api), and common transform methods `getValues`, `getFunctionName`, `is2D`, `__toString`, `__get`, `__set`, `identityMatrix`, and `multiplyMatrices` from [CSSTransformComponent](CSSTransformComponent.md#public-api). Overrides `setIs2D`, `toMatrix`, and `clone`.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|CSSKeywordValue|int|float|string $lengthOrFunction, array|CSSNumericValue|CSSKeywordValue|int|float|string|null $values = null)`; accepts a length/positive scalar (default unit px), `CSSKeywordValue('none')`, or a `perspective` function name and one-element argument array. Other keyword, wrong count/function, non-length, or nonpositive concrete length throws `InvalidArgumentException`. Example: `$perspective = new CSSPerspective('800px');`. |
| `getLength` | `public function getLength(): CSSNumericValue|CSSKeywordValue`; returns the length or `none`. Example: `$length = $perspective->getLength();`. |
| `setLength` | `public function setLength(CSSNumericValue|CSSKeywordValue|int|float|string $length): void`; validates/stores a positive length or `none` and synchronizes serialized arguments; invalid values throw `InvalidArgumentException`. Example: `$perspective->setLength('none');`. |
| `setIs2D` | `public function setIs2D(bool $is2D): void`; perspective transforms are strictly 3D; passing `true` throws `InvalidArgumentException`. |
| `toMatrix` | `public function toMatrix(): DOMMatrix`; returns 3D identity for `none`; otherwise requires a positive finite concrete length convertible to px and sets the finite perspective matrix entry. Throws `InvalidArgumentException` if conversion, positivity, or finiteness fails. Example: `$matrix = $perspective->toMatrix();`. |
| `clone` | `public function clone(): static`; clones the stored length/keyword. Example: `$copy = $perspective->clone();`. |
| `__get` | `public function __get(string $name): mixed`; exposes magic read-only `length`; other names delegate to parent (`is2D`). Example: `$length = $perspective->length;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; accepts numeric values, strings, or numeric/keyword objects for magic `length`; delegates parent properties, otherwise throws `Error`. Example: `$perspective->length = '600px';`. |
