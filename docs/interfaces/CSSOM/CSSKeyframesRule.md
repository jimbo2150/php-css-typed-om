# `CSSKeyframesRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSKeyframesRule`
- **Source:** [`src/CSSOM/CSSKeyframesRule.php`](../../../src/CSSOM/CSSKeyframesRule.php)
- **Purpose:** Represents standard or vendor-prefixed `@keyframes`, with keyframe-specific insertion, deletion, and lookup.
- **Inheritance:** `CSSGroupingRule` → `CSSRule`; inherited public APIs: [`CSSGroupingRule`](CSSGroupingRule.md), [`CSSRule`](CSSRule.md).

## Properties

Magic `name` reads/writes the prelude; magic read-only `length` returns the number of keyframe rules. `cssRules` contains the keyframes; remaining virtual properties are inherited.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $name, string $atRuleName = 'keyframes')` | Validates non-empty name and structural syntax; permits alternate at-rule name such as `-webkit-keyframes`. Throws `InvalidArgumentException` when invalid. | `$rule = new CSSKeyframesRule('pulse');` |
| `getNameText(): string` | Returns the animation name. | `$rule->getNameText();` |
| `setNameText(string $name): void` | Validates and stores a non-empty, structurally valid name. Throws `InvalidArgumentException` otherwise. | `$rule->setNameText('fade');` |
| `getType(): int` | Returns `CSSRule::KEYFRAMES_RULE`. | `$rule->getType();` |
| `getLength(): int` | Returns the number of keyframe rules in `cssRules`. | `$rule->getLength();` |
| `appendRule(string $ruleText, ?CSSParserOptions $options = null): void` | Inserts a keyframe at the end by calling `insertRule()`. It requires the rule be attached to a stylesheet; parser and bounds errors propagate. | `$rule->appendRule('to { opacity: 1 }');` |
| `insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int` | Strictly parses a single keyframe rule via `CSSParser::parseSingleKeyframeRule()` (rejecting nested blocks and out-of-range keyframe selectors), inserts at index, and returns index. | `$rule->insertRule('from { opacity: 0 }');` |
| `deleteRule(int|string $keyText): void` | Integer removes by list index (and can throw `OutOfBoundsException`); string removes the first case-insensitive key-text match. Missing strings are ignored. | `$rule->deleteRule('from');` |
| `findRule(string $keyText): ?CSSKeyframeRule` | Returns first case-insensitive trimmed key-text match or `null`. | `$keyframe = $rule->findRule('50%');` |
| `__get(string $name): mixed` | Exposes `name` and `length`; other names delegate to `CSSGroupingRule::__get()`. | `$rule->length;` |
| `__set(string $name, mixed $value): void` | String `name` assignments call `setNameText()`; other assignments delegate to the parent. | `$rule->name = 'fade';` |

Inherited grouping and CSSRule methods are documented in the linked parent pages.
