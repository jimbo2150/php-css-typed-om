# MathOperationsTrait

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MathOperationsTrait`  
**Source:** [`src/TypedOM/Traits/MathOperationsTrait.php`](../../../src/TypedOM/Traits/MathOperationsTrait.php)  
**Purpose:** Trait-provided scalar arithmetic and a limited table-driven unit conversion API for CSS unit values.

## Public API

The trait uses private `$value: float` and `$unit: string` fields, initialized internally by a private helper; a consuming type must ensure compatible state is initialized.

| Method | Signature and behavior |
|---|---|
| `add` | `public function add(CSSUnitValue $other): ?CSSUnitValue`; returns null if unit symbols differ; otherwise returns a new value with summed magnitude and same unit. Example: `$sum = $left->add($right);`. |
| `subtract` | `public function subtract(CSSUnitValue $other): ?CSSUnitValue`; returns null for different unit symbols, otherwise a new difference with the same unit. Example: `$difference = $left->subtract($right);`. |
| `multiply` | `public function multiply(float $factor): CSSUnitValue`; returns a new value multiplied by the scalar. Example: `$scaled = $length->multiply(2.0);`. |
| `divide` | `public function divide(float $divisor): CSSUnitValue`; returns a new value divided by the scalar; throws `InvalidArgumentException` for zero. Example: `$half = $length->divide(2.0);`. |
| `to` | `public function to(string $targetUnit): ?CSSUnitValue`; returns same-unit copy or uses the trait's hard-coded pairwise conversion table; returns null when the source/target pair has no table entry. This is not the general `CSSNumericValue::to` conversion API. Example: `$converted = $length->to('em');`. |
| `getNumericValue` | `public function getNumericValue(): float`; returns the private magnitude. Example: `$magnitude = $length->getNumericValue();`. |
| `setNumericValue` | `public function setNumericValue(float $value): void`; replaces the magnitude without finiteness validation. Example: `$length->setNumericValue(20.0);`. |
| `getUnit` | `public function getUnit(): string`; returns the private unit string. Example: `$unit = $length->getUnit();`. |
| `setUnit` | `public function setUnit(string $unit): void`; replaces the unit string without validation. Example: `$length->setUnit('px');`. |
| `isValid` | `public function isValid(): bool`; true when magnitude is finite and unit string is nonempty. Example: `$valid = $length->isValid();`. |

No public properties or constants are declared. The trait body expects its initialization helper to have been called by the consumer; its conversion table contains a finite list of pairs and therefore does not imply comprehensive CSS unit support.
