# `CSSPageDescriptors`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageDescriptors`
- **Source:** [`src/CSSOM/CSSPageDescriptors.php`](../../../src/CSSOM/CSSPageDescriptors.php)
- **Purpose:** Strict descriptor declaration block for `@page` rules (CSS Paged Media Module Level 3).
- **Inheritance:** `CSSStyleDeclaration`; inherited public API: [`CSSStyleDeclaration`](CSSStyleDeclaration.md).

## Validation Policy

- Enforces the CSS Paged Media 3 page-context descriptor/property whitelist (`size`, `page-orientation`, `marks`, `bleed`, margins, padding, borders, backgrounds, typography, counters, etc.).
- Allows custom properties (`--*`) and `!important`.
- Direct mutation throws `InvalidArgumentException` on disallowed properties; `CSSStyleSheet::parse()` records byte-offset diagnostics while keeping valid page descriptors and nested `@margin` rules.
- Inspect the whitelist via `CSSPageDescriptors::getAllowedDescriptors()`.
