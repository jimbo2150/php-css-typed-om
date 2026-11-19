# TypeableUnitTrait

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TypeableUnitTrait`  
**Source:** [`src/TypedOM/Traits/TypeableUnitTrait.php`](../../../src/TypedOM/Traits/TypeableUnitTrait.php)  
**Purpose:** Supplies a unit enum slot, read-only unit-symbol property, and unit-category accessor.

## Public API

| Member | Signature and behavior |
|---|---|
| `unit` | `public string $unit { get; }`; returns the unit enum's symbol or an empty string when no unit enum is set. Example: `$symbol = $numeric->unit;`. |
| `type` | `public function type(): ?string`; returns the unit category string, or throws `Exception` when no unit enum is set. Example: `$category = $numeric->type();`. |

No public constants or enum cases. The trait's `unitObj` property and `setUnit(string|CSSUnitEnum $unit)` method are protected, not public. `CSSNumericValue` aliases this trait's `type()` method as public `unitType()` because its own `type()` returns a `CSSNumericType`.
