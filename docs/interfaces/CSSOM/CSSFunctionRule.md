# `CSSFunctionRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionRule`
- **Source:** [`src/CSSOM/CSSFunctionRule.php`](../../../src/CSSOM/CSSFunctionRule.php)
- **Purpose:** Represents a CSS `@function` with a custom name, parsed parameter signature, return type, and nested rule body.
- **Inheritance:** `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `name` and `returnType` are read-only. Constructor-promoted `functionName`, `parameters`, and `functionReturnType` are private; they are exposed by getters. `cssRules` and CSSRule virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $functionName, string $prelude = '', array $parameters = [], string $functionReturnType = '*')` | `$parameters` is intended as a list of `{name, type, defaultValue}` records. Requires a normalized custom name beginning `--`; uses the name as prelude when `$prelude` is empty. The direct constructor does not validate parameter records or return type; the parser factory does. Throws `InvalidArgumentException` for an invalid name/prelude. | `$rule = new CSSFunctionRule('--double');` |
| `fromPrelude(string $prelude): self` | Parses name, parameter types/defaults, and optional `returns <type>`/`returns *`; throws `InvalidArgumentException` for malformed syntax. | `$rule = CSSFunctionRule::fromPrelude('--double(--x <number>) returns <number>');` |
| `getType(): int` | Returns `CSSRule::FUNCTION_RULE`. | `$rule->getType();` |
| `getNameText(): string` | Returns the validated custom function name. | `$rule->getNameText();` |
| `getReturnType(): string` | Returns parsed return type, defaulting to `*`. | `$rule->getReturnType();` |
| `getParameters(): array` | Returns ordered parameter records with `name`, `type` (default `*`), and nullable `defaultValue`. | `$rule->getParameters();` |
| `__get(string $name): mixed` | Exposes `name` and `returnType`; all other names delegate to `CSSGroupingRule::__get()`. | `$rule->returnType;` |

Inherited grouping methods and CSSRule methods are documented on the parent pages.
