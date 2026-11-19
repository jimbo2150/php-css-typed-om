# MultiValueArrayTrait

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MultiValueArrayTrait`  
**Source:** [`src/TypedOM/Traits/MultiValueArrayTrait.php`](../../../src/TypedOM/Traits/MultiValueArrayTrait.php)  
**Purpose:** Stores CSS numeric values as a PHP list and supplies construction/string conversion for numeric-array consumers.

## Public API

| Member | Signature and behavior |
|---|---|
| `values` | `public array $values` (`array<CSSNumericValue>`) with protected setter; getter returns the stored list. Example: `$items = $arrayObject->values;`. |
| `__construct` | `public function __construct(CSSNumericValue|CSSNumericArray|array $value)`; copies a `CSSNumericArray`'s list, wraps one numeric value, or validates/appends each input array entry; nonnumeric entries throw `InvalidArgumentException`. Example: `$items = new CSSNumericArray([$a, $b]);`. |
| `__toString` | `public function __toString(): string`; joins values with comma-space. Example: `$text = (string) $items;`. |

No public constants or enum cases. The trait is used by `CSSNumericArray`, whose own public API is documented separately.
