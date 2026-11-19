# `CSSMediaRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSMediaRule`
- **Source:** [`src/CSSOM/CSSMediaRule.php`](../../../src/CSSOM/CSSMediaRule.php)
- **Purpose:** Represents `@media` and synchronizes condition text with a mutable `MediaList`.
- **Inheritance:** `CSSConditionRule` → `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSConditionRule`](CSSConditionRule.md), [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic read-only `media` returns the associated `MediaList`; inherited `conditionText`, `cssRules`, and CSSRule virtual properties apply. The condition setter delegates to `MediaList::setMediaText()`.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $conditionText)` | Initializes the `media` grouping rule and normalizes condition text through `MediaList`. | `$rule = new CSSMediaRule('screen and (width > 40rem)');` |
| `getType(): int` | Returns `CSSRule::MEDIA_RULE`. | `$rule->getType();` |
| `getMedia(): MediaList` | Returns the list whose change callback updates this rule's prelude. | `$rule->getMedia()->appendMedium('print');` |
| `getConditionText(): string` | Returns normalized media-list text. | `$rule->getConditionText();` |
| `setConditionText(string $conditionText): void` | Replaces media query list text through `MediaList`; invalid syntax throws `InvalidArgumentException`. | `$rule->setConditionText('print');` |
| `__get(string $name): mixed` | Exposes `media`, otherwise delegates to `CSSConditionRule::__get()`. | `$rule->media;` |

Inherited condition, grouping, and rule methods are documented in the linked parent pages.
