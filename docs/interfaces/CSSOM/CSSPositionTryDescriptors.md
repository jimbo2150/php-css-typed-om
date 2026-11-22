# `CSSPositionTryDescriptors`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSPositionTryDescriptors`
- **Source:** [`src/CSSOM/CSSPositionTryDescriptors.php`](../../../src/CSSOM/CSSPositionTryDescriptors.php)
- **Purpose:** Strict descriptor declaration block for `@position-try` rules (CSS Anchor Positioning Module Level 1).
- **Inheritance:** `CSSStyleDeclaration`; inherited public API: [`CSSStyleDeclaration`](CSSStyleDeclaration.md).

## Validation Policy

- Enforces the CSS Anchor Positioning 1 position-try property whitelist (inset, margin, sizing, self-alignment, `position-anchor`, and `position-area`).
- Disallows custom properties (`--*`) and `!important`.
- Direct mutation throws `InvalidArgumentException` on disallowed descriptors or `!important`; `CSSStyleSheet::parse()` records byte-offset diagnostics and preserves valid descriptors.
- Inspect the whitelist via `CSSPositionTryDescriptors::getAllowedDescriptors()`.
