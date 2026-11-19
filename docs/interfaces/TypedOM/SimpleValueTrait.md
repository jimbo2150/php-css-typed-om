# SimpleValueTrait

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Traits\SimpleValueTrait`  
**Source:** [`src/TypedOM/Traits/SimpleValueTrait.php`](../../../src/TypedOM/Traits/SimpleValueTrait.php)  
**Purpose:** Supplies a public-readable value slot, common string serialization, and shallow cloning for simple values.

## Public API

| Member | Signature and behavior |
|---|---|
| `value` | `public mixed $value` with protected setter; getter returns the current slot. Example: `$raw = $valueObject->value;`. |
| `__toString` | `public function __toString(): string`; casts the slot to string. Example: `$text = (string) $valueObject;`. |
| `clone` | `public function clone(): static`; returns a shallow clone of the current object. Example: `$copy = $valueObject->clone();`. |

No public constants or enum cases. Protected `setValue`, `isValueValid`, and `validateValue` are not public API; `setValue` accepts string/float and the validation helpers require a finite numeric value.
