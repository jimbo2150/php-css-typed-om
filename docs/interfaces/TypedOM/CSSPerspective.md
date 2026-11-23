# CSSPerspective

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPerspective`  
**Source:** [`src/TypedOM/Values/CSSPerspective.php`](../../../src/TypedOM/Values/CSSPerspective.php)  
**Purpose:** Models `perspective(length)` or `perspective(none)` as a 3D transform component.

## Public API

No public constants or enum cases. Inherits `parse`/`parseAll` from [CSSStyleValue](CSSStyleValue.md#public-api), and common transform methods `getFunctionName`, `is2D`, `__toString`, `identityMatrix`, and `multiplyMatrices` from [CSSTransformComponent](CSSTransformComponent.md#public-api). Overrides `getValues`, `setIs2D`, `toMatrix`, and `clone`.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(CSSNumericValue|CSSKeywordValue|int|float|string $lengthOrFunction, array|CSSNumericValue|CSSKeywordValue|int|float|string|null $values = null)`; accepts a length/positive scalar (default unit px), `CSSKeywordValue('none')`, or a `perspective` function name and one-element argument array. Other keyword, wrong count/function, non-length, or nonpositive concrete length throws `InvalidArgumentException`. Example: `$perspective = new CSSPerspective('800px');`. |
| `getLength` | `public function getLength(): CSSNumericValue|CSSKeywordValue`; returns the length or a cloned canonical `'none'` `CSSKeywordValue`. Example: `$length = $perspective->getLength();`. |
| `getValues` | `public function getValues(): array`; returns `[$this->getLength()]` so `'none'` keywords remain isolated. |
| `setLength` | `public function setLength(CSSNumericValue|CSSKeywordValue|int|float|string $length): void`; validates/stores a positive length or fresh `'none'` keyword and synchronizes serialized arguments; invalid values throw `InvalidArgumentException`. Example: `$perspective->setLength('none');`. |
| `setIs2D` | `public function setIs2D(bool $is2D): void`; perspective transforms are strictly 3D; passing `true` throws `InvalidArgumentException`. |
| `toMatrix` | `public function toMatrix(): DOMMatrix`; returns 3D identity for `none`; otherwise requires a positive finite concrete length convertible to px and sets the finite perspective matrix entry. Throws `InvalidArgumentException` if conversion, positivity, or finiteness fails. Example: `$matrix = $perspective->toMatrix();`. |
| `clone` | `public function clone(): static`; clones the stored length/keyword. Example: `$copy = $perspective->clone();`. |
| `$length` (property hook) | `public CSSNumericValue\|CSSKeywordValue $length { get; set; }`; reads via `getLength()` (cloning `'none'`) and writes via `setLength()`, plus inherited `public bool $is2D`. Unknown properties via `__get`/`__set` throw `Error`. Example: `$perspective->length = '600px';`. |
