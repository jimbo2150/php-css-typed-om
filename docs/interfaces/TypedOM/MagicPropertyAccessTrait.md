# MagicPropertyAccessTrait

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait`  
**Source:** [`src/TypedOM/Traits/MagicPropertyAccessTrait.php`](../../../src/TypedOM/Traits/MagicPropertyAccessTrait.php)  
**Purpose:** Provides array-backed magic property reads/writes with optional type coercion/validation and bulk accessors.

## Public API

| Method | Signature and behavior |
|---|---|
| `__get` | `public function __get(string $name): mixed`; returns a mapped property, or the `propertyTypes` map for magic `type`; throws `Error` for an unknown property. Example: `$value = $object->name;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; writes an existing mapped property; optionally coerces numeric float/int, string, or bool based on its configured type, otherwise validates using `gettype`/`is_a`; throws `Error` for unknown names and `TypeError` for type mismatch. Example: `$object->setProperties(['count' => 2]);`. |
| `__isset` | `public function __isset(string $name): bool`; true if a mapped value exists or if `type` is requested while the trait's type map property exists. Example: `$present = isset($object->name);`. |
| `getProperties` | `public function getProperties(): array`; returns the current property map (`array<string,mixed>`). Example: `$properties = $object->getProperties();`. |
| `setProperties` | `public function setProperties(array $properties): void`; applies each named value through `__set`, so validation/coercion and errors match individual writes. Example: `$object->setProperties(['width' => 12.5]);`. |

The trait has private `initializeProperties(array $properties, array $propertyTypes): void` for consumers but it is not public and is not part of the callable public API. The trait declares no public properties or constants.
