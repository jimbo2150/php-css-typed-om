# `CSSRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSRule`
- **Source:** [`src/CSSOM/CSSRule.php`](../../../src/CSSOM/CSSRule.php)
- **Purpose:** Abstract base class for CSSOM rule nodes, parent association, rule type constants, and common virtual properties.

## Public Constants

| Constant | Value | Rule kind |
|---|---:|---|
| `UNKNOWN_RULE` | `0` | Unrecognized/opaque rule. |
| `STYLE_RULE` | `1` | Qualified style rule. |
| `CHARSET_RULE` | `2` | `@charset`. |
| `IMPORT_RULE` | `3` | `@import`. |
| `MEDIA_RULE` | `4` | `@media`. |
| `FONT_FACE_RULE` | `5` | `@font-face`. |
| `PAGE_RULE` | `6` | `@page`. |
| `KEYFRAMES_RULE` | `7` | `@keyframes`. |
| `KEYFRAME_RULE` | `8` | Single keyframe. |
| `MARGIN_RULE` | `9` | Page margin box. |
| `NAMESPACE_RULE` | `10` | `@namespace`. |
| `COUNTER_STYLE_RULE` | `11` | `@counter-style`. |
| `SUPPORTS_RULE` | `12` | `@supports`. |
| `FONT_FEATURE_VALUES_RULE` | `14` | `@font-feature-values`. |
| `FONT_PALETTE_VALUES_RULE` | `15` | `@font-palette-values`. |
| `FUNCTION_RULE` | `16` | `@function`. |
| `PROPERTY_RULE` | `17` | `@property`. |
| `LAYER_BLOCK_RULE` | `18` | Block-form `@layer`. |
| `LAYER_STATEMENT_RULE` | `19` | Statement-form `@layer`. |
| `STARTING_STYLE_RULE` | `20` | `@starting-style`. |
| `POSITION_TRY_RULE` | `21` | `@position-try`. |
| `FUNCTION_DECLARATIONS_RULE` | `22` | Function declaration body. |
| `SCOPE_RULE` | `24` | `@scope`. |
| `CONTAINER_RULE` | `25` | `@container`. |
| `VIEW_TRANSITION_RULE` | `26` | `@view-transition`. |
| `NESTED_DECLARATIONS_RULE` | `27` | Declarations among nested rules. |

## Properties

Private `parentStyleSheet` and `parentRule` fields are exposed by getters and magic read-only virtual properties `parentStyleSheet` and `parentRule` (and detached rule instances retain their configured `$maxBytes` budget across parent-link queries). `type` and `cssText` are virtual read-only properties. No public instance fields are declared.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `getType(): int` (abstract) | Returns this node's rule-type constant. | `$rule->getType();` |
| `getCssText(): string` (abstract) | Returns CSS serialization for the node. | `$rule->getCssText();` |
| `getParentStyleSheet(): ?CSSStyleSheet` | Returns attached stylesheet, or `null` when detached. | `$rule->getParentStyleSheet();` |
| `getParentRule(): ?CSSGroupingRule` | Returns owning grouping rule, or `null` when not nested/attached. | `$rule->getParentRule();` |
| `setParentLinks(?CSSStyleSheet $sheet, ?CSSGroupingRule $parentRule): void` | Updates direct parent stylesheet and rule references without reparenting child lists. | `$rule->setParentLinks($sheet, $parent);` |
| `attach(?CSSStyleSheet $sheet, ?CSSGroupingRule $parentRule): void` | Updates both parent references; grouping subclasses also reparent child lists. | `$rule->attach($sheet, null);` |
| `__clone(): void` | Deeply detaches/clones rule state iteratively (including owned style declarations and grouping child lists without recursion). | `$copy = clone $rule;` |
| `__toString(): string` | Returns `getCssText()`. | `$css = (string) $rule;` |
| `__get(string $name): mixed` | Maps `type`, `cssText`, `parentStyleSheet`, and `parentRule`; unknown names throw `Error`. | `$rule->cssText;` |
| `__isset(string $name): bool` | Reports availability for `type`, `cssText`, `parentStyleSheet`, and `parentRule`. | `isset($rule->cssText);` |
| `__set(string $name, mixed $value): void` | Rejects every magic write with `Error`. | `$rule->cssText = 'x'; // throws Error` |

The class has no public constructor; subclasses provide construction.
