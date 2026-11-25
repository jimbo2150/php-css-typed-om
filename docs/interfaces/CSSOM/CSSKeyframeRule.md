# `CSSKeyframeRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSKeyframeRule`
- **Source:** [`src/CSSOM/CSSKeyframeRule.php`](../../../src/CSSOM/CSSKeyframeRule.php)
- **Purpose:** Represents one keyframe selector list and its declaration block within `@keyframes`.
- **Inheritance:** `CSSRule`; inherited public API: [`CSSRule`](CSSRule.md).

## Properties

Magic `keyText` reads/writes the keyframe selector list (`from`, `to`, or `[0%, 100%]` percentages); magic read-only `style` returns [`CSSStyleProperties`](CSSStyleProperties.md).

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $keyText, ?CSSStyleDeclaration $style = null, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Enforces `$maxBytes` and retains it when detached, validates keyframe selector list, and wraps `$style` in `CSSStyleProperties` with `$this` as `parentRule`. | `$kf = new CSSKeyframeRule('from, to');` |
| `getKeyText(): string` | Returns the keyframe selector text. | `$kf->getKeyText();` |
| `setKeyText(string $keyText): void` | Accepts comma-separated `from`, `to`, or `[0%, 100%]` percentages (including decimals like `.5%`, capped at `CSSStyleValue::MAX_LIST_ITEMS` and `$this->resolveMaxBytesInternal()`); throws `InvalidArgumentException` / `CSSParserLimitException` otherwise. | `$kf->setKeyText('from, 50%, to');` |
| `getStyle(): CSSStyleProperties` | Returns the keyframe declaration block. | `$kf->getStyle()->setProperty('opacity', '1');` |
| `getType(): int` | Returns `CSSRule::KEYFRAME_RULE`. | `$kf->getType();` |
| `getCssText(): string` | Serializes `keyText { declarations }`. | `$kf->getCssText();` |
| `__get(string $name): mixed` | Exposes `keyText` and `style`; other names delegate to `CSSRule::__get()`. | `$kf->keyText;` |
| `__isset(string $name): bool` | True for `keyText` and `style`, otherwise delegates to `CSSRule::__isset()`. | `isset($kf->keyText);` |
| `__set(string $name, mixed $value): void` | String `keyText` assignments call `setKeyText()`; other writes delegate to `CSSRule::__set()`. | `$kf->keyText = 'to';` |
| `__clone(): void` | Clones the nested declaration block and rebinds its parent rule cleanly. | `$copy = clone $kf;` |
