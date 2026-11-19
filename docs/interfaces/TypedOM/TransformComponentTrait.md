# TransformComponentTrait

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TransformComponentTrait`  
**Source:** [`src/TypedOM/Traits/TransformComponentTrait.php`](../../../src/TypedOM/Traits/TransformComponentTrait.php)  
**Purpose:** Supplies named `CSSUnitValue` transform component storage and common 2D/3D/value accessors.

## Public API

| Method | Signature and behavior |
|---|---|
| `is2D` | `public function is2D(): bool`; returns the consumer's `is2D` flag. Example: `$twoDimensional = $component->is2D();`. |
| `getTransformType` | `abstract public function getTransformType(): string`; required on a consuming class to identify its transform kind. Example: `echo $component->getTransformType();`. |
| `getValues` | `public function getValues(): array`; returns `array<string,CSSUnitValue>` of stored components. Example: `$values = $component->getValues();`. |
| `getValue` | `public function getValue(string $name): ?CSSUnitValue`; returns the named value or null if absent. Example: `$x = $component->getValue('x');`. |
| `setValue` | `public function setValue(string $name, CSSUnitValue $value): void`; inserts or replaces a named component without additional validation. Example: `$component->setValue('x', new CSSUnitValue(2, 'px'));`. |

No public properties or constants are declared. Public use of methods from this trait assumes the consumer initializes compatible value storage; the protected initialization, clone, serialization, and required-value helpers are not public APIs. No tracked source class currently imports this trait.
