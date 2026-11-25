# `CSSFunctionDeclarations`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionDeclarations`
- **Source:** [`src/CSSOM/CSSFunctionDeclarations.php`](../../../src/CSSOM/CSSFunctionDeclarations.php)
- **Purpose:** Rule node holding descriptor declarations nested in an `@function` rule.
- **Inheritance:** `CSSRule`; inherited public API: [`CSSRule`](CSSRule.md).

## Properties

Magic read-only `style` returns the `CSSFunctionDescriptors` object. Standard CSSRule virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(CSSFunctionDescriptors $style, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Stores/clones the descriptor object, retains `$maxBytes` when detached, and sets this rule as its parent rule. | `$declarations = new CSSFunctionDeclarations(new CSSFunctionDescriptors('result: 1'));` |
| `getType(): int` | Returns `CSSRule::FUNCTION_DECLARATIONS_RULE`. | `$declarations->getType();` |
| `getStyle(): CSSFunctionDescriptors` | Returns the associated descriptor block. | `$declarations->getStyle()->getResult();` |
| `getCssText(): string` | Returns descriptor serialization. | `$declarations->getCssText();` |
| `__get(string $name): mixed` | Exposes `style`; other names delegate to `CSSRule::__get()`. | `$declarations->style;` |
| `__isset(string $name): bool` | True for `style`; other names delegate to `CSSRule::__isset()`. | `isset($declarations->style);` |
| `__clone(): void` | Clones the nested descriptor block and rebinds its parent rule cleanly. | `$copy = clone $declarations;` |

The remaining inherited public methods are documented in [`CSSRule`](CSSRule.md).
