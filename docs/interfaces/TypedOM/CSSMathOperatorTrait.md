# CSSMathOperatorTrait

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathOperatorTrait`  
**Source:** [`src/TypedOM/Values/Numeric/Math/CSSMathOperatorTrait.php`](../../../src/TypedOM/Values/Numeric/Math/CSSMathOperatorTrait.php)  
**Purpose:** Trait for operator-style math nodes; contributes operator/sign constants and a string helper when composed into a compatible type.

## Public API

| Member | Signature and behavior |
|---|---|
| `sign` | `public const ?string sign = null`; default sign marker for a using class; may be redeclared/overridden by the consuming class. |
| `operator` | `public const ?string operator = null`; default operator name used by the helper; may be redeclared/overridden by the consuming class. |
| `toString` | `public function toString(): string`; joins `$this->values->values` using spaces around `$this->operator`. The consuming class must provide a compatible `values` property. Example: `$text = $node->toString();`. |

The trait itself does not declare a constructor or validate the operand collection. No tracked class currently imports this trait; concrete math classes define their own constants and use `CSSMathValue` serialization instead.
