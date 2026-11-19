# MultiValueTrait

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MultiValueTrait`  
**Source:** [`src/TypedOM/Traits/MultiValueTrait.php`](../../../src/TypedOM/Traits/MultiValueTrait.php)  
**Purpose:** Supplies a `CSSNumericArray`-backed collection and common constructor, string, and deep-clone methods.

## Public API

| Member | Signature and behavior |
|---|---|
| `values` | `public CSSNumericArray $values` with protected setter; stores or returns the numeric collection. Example: `$operands = $math->values;`. |
| `inner_values` | `public array $inner_values` (`array<CSSNumericValue>`) with private setter; getter projects `$this->values->values`. Example: `$operands = $math->inner_values;`. |
| `__construct` | `public function __construct(CSSNumericValue|CSSNumericArray|array $value)`; stores an existing numeric array or wraps a numeric value/list in `CSSNumericArray`; array validation is delegated to that class. Example: `$math = new CSSMathSum([$left, $right]);`. |
| `__toString` | `public function __toString(): string`; casts the numeric array to its string form. Example: `$text = (string) $math;`. |
| `clone` | `public function clone(): static`; clones the `CSSNumericArray` and constructs a new instance. Example: `$copy = $math->clone();`. |

No public constants or enum cases. A consuming class may override the supplied methods; `CSSMathValue` does so for serialization and cloning.
