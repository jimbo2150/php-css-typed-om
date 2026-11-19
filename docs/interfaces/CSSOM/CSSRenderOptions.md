# `CSSRenderOptions`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSRenderOptions`
- **Source:** [`src/CSSOM/CSSRenderOptions.php`](../../../src/CSSOM/CSSRenderOptions.php)
- **Purpose:** Immutable formatting and callback configuration for `CSSRenderer`.
- **Type:** `final readonly class`.

## Public Properties

| Property | Type / default | Meaning |
|---|---|---|
| `$mode` | `CSSRenderMode`, `CSSRenderMode::Pretty` | Pretty or minified output. |
| `$ruleCallback` | `?\Closure`, `null` | Optional callback of shape `fn (CSSRule $rule, string $rendered): string`; called after each rule's children are rendered and its return replaces that rule's output. |
| `$keepComments` | `bool`, `true` | Whether comments in `CSSOpaqueRule` source are retained. Comments removed while parsing structured declarations cannot be restored. |

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(CSSRenderMode $mode = CSSRenderMode::Pretty, ?\Closure $ruleCallback = null, bool $keepComments = true)` | Initializes readonly formatting options. Callback receives each rule and rendered text, returning replacement text. | `$options = new CSSRenderOptions(CSSRenderMode::Minified);` |
