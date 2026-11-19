# LengthTrait

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Traits\LengthTrait`  
**Source:** [`src/TypedOM/Traits/LengthTrait.php`](../../../src/TypedOM/Traits/LengthTrait.php)  
**Purpose:** Supplies a computed public length property based on a consuming object's `values` member.

## Public API

| Property | Signature and behavior |
|---|---|
| `length` | `public ?int $length { get; }`; getter returns `count($this->values)` if a `values` property is set, otherwise null. There is no setter. Example: `$count = $object->length;`. |

The trait declares no public methods, constants, or enum cases. It assumes the consuming class's `values`, when present, is countable.
