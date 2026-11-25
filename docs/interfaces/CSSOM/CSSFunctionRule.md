# `CSSFunctionRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionRule`
- **Source:** [`src/CSSOM/CSSFunctionRule.php`](../../../src/CSSOM/CSSFunctionRule.php)
- **Purpose:** Represents a CSS `@function` with a custom name, parsed parameter signature, return type, and nested rule body.
- **Inheritance:** `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `name` and `returnType` are read-only. Private `readonly` properties `functionName`, `parameters`, and `functionReturnType` are exposed by getters. `cssRules` and CSSRule virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $functionName, string $prelude = '', array $parameters = [], string $functionReturnType = '*', ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Checks `$maxBytes` before scanning; requires a normalized custom name starting with `--`, normalizes whitespace/comment-only `$prelude` back to generated parameters, validates every parameter record (`name`, `type`, `defaultValue`), `$functionReturnType`, and caps `$parameters` at `CSSStyleValue::MAX_LIST_ITEMS`. Throws `InvalidArgumentException` / `CSSParserLimitException`. | `$rule = new CSSFunctionRule('--double');` |
| `fromPrelude(string $prelude, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES): self` | Strips CSS comments across fragments and parses escaped/unquoted function name, top-level parameter list (capped at `CSSStyleValue::MAX_LIST_ITEMS`, requiring whitespace before optional `<type>`/`*`), and optional `returns <type>` / `returns *` within `$maxBytes`. Throws `InvalidArgumentException` / `CSSParserLimitException`. | `$rule = CSSFunctionRule::fromPrelude('--double(--x <number>) returns <number>');` |
| `getType(): int` | Returns `CSSRule::FUNCTION_RULE`. | `$rule->getType();` |
| `getNameText(): string` | Returns the validated custom function name. | `$rule->getNameText();` |
| `getReturnType(): string` | Returns parsed return type, defaulting to `*`. | `$rule->getReturnType();` |
| `getParameters(): array` | Returns ordered parameter records with `name`, `type` (default `*`), and nullable `defaultValue`. | `$rule->getParameters();` |
| `__get(string $name): mixed` | Exposes `name` and `returnType`; all other names delegate to `CSSGroupingRule::__get()`. | `$rule->returnType;` |
| `__isset(string $name): bool` | True for `name` and `returnType`, otherwise delegates to `CSSGroupingRule::__isset()`. | `isset($rule->name);` |

Inherited grouping methods and CSSRule methods are documented on the parent pages.
