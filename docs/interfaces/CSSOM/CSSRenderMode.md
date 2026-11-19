# `CSSRenderMode`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderMode`
- **Source:** [`src/CSSOM/CSSRenderMode.php`](../../../src/CSSOM/CSSRenderMode.php)
- **Purpose:** Selects pretty or minified CSS serialization.
- **Type:** Backed string enum.

## Enum Cases

| Case | Backing value | Meaning |
|---|---|---|
| `Pretty` | `'pretty'` | Adds two-space indentation and newline separators. |
| `Minified` | `'minified'` | Omits indentation and joins rendered rules without separators. |

## Public Properties and Methods

Each case exposes the built-in readonly string properties `name` and `value` (for example, `Pretty` and `'pretty'`). The backed enum methods are:

| Signature | Behavior | Example |
|---|---|---|
| `cases(): array` (static) | Returns `[CSSRenderMode::Pretty, CSSRenderMode::Minified]` in declaration order. | `CSSRenderMode::cases();` |
| `from(string $value): static` (static) | Returns the matching case; throws `ValueError` if the backing value is unknown. | `CSSRenderMode::from('pretty');` |
| `tryFrom(string $value): ?static` (static) | Returns the matching case or `null` for an unknown value. | `CSSRenderMode::tryFrom('compact');` |

Example: `$mode = CSSRenderMode::Pretty;`.
