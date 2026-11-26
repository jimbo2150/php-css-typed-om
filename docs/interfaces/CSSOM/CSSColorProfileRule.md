# `CSSColorProfileRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSColorProfileRule`
- **Source:** [`src/CSSOM/CSSColorProfileRule.php`](../../../src/CSSOM/CSSColorProfileRule.php)
- **Inheritance:** `CSSAtRule` → `CSSRule`; inherited APIs: [`CSSAtRule`](CSSAtRule.md), [`CSSRule`](CSSRule.md).
- **Purpose:** Represents a CSS Color 4 `@color-profile` at-rule.

## Properties

Read-only magic properties `name`, `src`, `renderingIntent`, and `components`, plus inherited `CSSAtRule` properties.

## Public Methods

- `__construct(string $prelude = '', ?CSSStyleDeclaration $style = null, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` — validates `<dashed-ident>` or `device-cmyk` profile name and descriptor block (`src`, `rendering-intent`, `components`).
- `getType(): int` — returns `CSSRule::COLOR_PROFILE_RULE` (`28`).
- `getNameText(): string` — returns the profile name.
- `getSrc(): string` — returns the `src` descriptor value (or `''`).
- `getRenderingIntent(): string` — returns the `rendering-intent` descriptor value (or `''`).
- `getComponents(): string` — returns the `components` descriptor value (or `''`).
