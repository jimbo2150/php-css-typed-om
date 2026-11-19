# `CSSFunctionDescriptors`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionDescriptors`
- **Source:** [`src/CSSOM/CSSFunctionDescriptors.php`](../../../src/CSSOM/CSSFunctionDescriptors.php)
- **Purpose:** Declaration-block specialization for `@function` descriptors, with a convenience accessor for `result`.
- **Inheritance:** `CSSStyleDeclaration`; inherited public API: [`CSSStyleDeclaration`](CSSStyleDeclaration.md).

## Properties

Magic `result` reads the `result` declaration. Other style declaration virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `getResult(): string` | Returns the value of the `result` declaration, or `''` when absent. | `$descriptors->getResult();` |
| `__get(string $name): mixed` | Exposes `result`; delegates other names to `CSSStyleDeclaration::__get()`. | `$descriptors->result;` |

The inherited constructor and all other inherited public methods are detailed in [`CSSStyleDeclaration`](CSSStyleDeclaration.md).
