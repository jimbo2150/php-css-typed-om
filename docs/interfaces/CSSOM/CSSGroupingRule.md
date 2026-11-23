# `CSSGroupingRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSGroupingRule`
- **Source:** [`src/CSSOM/CSSGroupingRule.php`](../../../src/CSSOM/CSSGroupingRule.php)
- **Purpose:** Base for rule nodes containing a mutable nested `CSSRuleList`.
- **Inheritance:** `CSSRule`; inherited public API: [`CSSRule`](CSSRule.md).

## Properties

Constructor-promoted protected `name` and `prelude` are not public fields. Magic read-only `cssRules` exposes the nested list; `type`, `cssText`, `parentStyleSheet`, and `parentRule` come from `CSSRule`.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $name, string $prelude = '')` | Validates the rule identifier and structural prelude, then initializes an empty list. Throws `InvalidArgumentException` for invalid syntax. | `$rule = new CSSGroupingRule('media', 'screen');` |
| `getType(): int` | Returns `CSSRule::UNKNOWN_RULE`; specific subclasses override it. | `$rule->getType();` |
| `getName(): string` | Returns the rule name. | `$rule->getName();` |
| `getPrelude(): string` | Returns the trimmed prelude. | `$rule->getPrelude();` |
| `getCssRules(): CSSRuleList` | Returns the owned nested rule list. | `$rule->getCssRules();` |
| `insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int` | Parses exactly one rule and inserts it; returns index. Requires attachment to a stylesheet. Throws `LogicException` if detached, `OutOfBoundsException` for invalid index, `InvalidArgumentException` for invalid/over-limit CSS. | `$sheet->insertRule('@media screen {}'); $group = $sheet->getCssRules()[0]; $group->insertRule('a { color: red }');` |
| `deleteRule(int $index): void` | Removes the rule at index. Requires attachment to a stylesheet (`LogicException` if detached) and valid index (`OutOfBoundsException` if invalid). | `$group->deleteRule(0);` |
| `getCssText(): string` | Serializes at-rule, prelude, and nested rule text iteratively without recursion; enforces `CSSRenderer::MAX_RENDER_DEPTH`. | `$group->getCssText();` |
| `initializeRules(CSSStyleSheet $sheet): void` | Associates this grouping rule with a sheet, retaining its current parent rule, and reparents its list iteratively. | `$group->initializeRules($sheet);` |
| `attach(?CSSStyleSheet $sheet, ?CSSGroupingRule $parentRule): void` | Updates inherited parent association and reparents nested subtrees iteratively in O(1) stack depth. | `$group->attach($sheet, null);` |
| `__get(string $name): mixed` | Exposes `cssRules`; other names delegate to `CSSRule::__get()`. | `$group->cssRules;` |

All additional inherited methods are documented on [`CSSRule`](CSSRule.md).
