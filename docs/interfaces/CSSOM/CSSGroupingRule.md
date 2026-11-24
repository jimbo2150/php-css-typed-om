# `CSSGroupingRule`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSGroupingRule`
- **Source:** [`src/CSSOM/CSSGroupingRule.php`](../../../src/CSSOM/CSSGroupingRule.php)
- **Purpose:** Base for rule nodes containing a mutable nested `CSSRuleList`.
- **Inheritance:** `CSSRule`; inherited public API: [`CSSRule`](CSSRule.md).

## Properties

Protected `name` and `prelude` hold the validated rule name and prelude; magic read-only `cssRules` exposes the nested list; `type`, `cssText`, `parentStyleSheet`, and `parentRule` come from `CSSRule`.

## Public Methods

| Signature | Parameters / behavior | Example |
|---|---|---|
| `__construct(string $name, string $prelude = '', ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)` | Validates the rule identifier, structural prelude, and optional `$maxBytes` limit, then initializes an empty list. Throws `InvalidArgumentException` / `CSSParserLimitException` for invalid syntax/limits. | `$rule = new CSSGroupingRule('media', 'screen');` |
| `getType(): int` | Returns `CSSRule::UNKNOWN_RULE`; specific subclasses override it. | `$rule->getType();` |
| `getName(): string` | Returns the rule name. | `$rule->getName();` |
| `getPrelude(): string` | Returns the trimmed prelude. | `$rule->getPrelude();` |
| `getCssRules(): CSSRuleList` | Returns the owned nested rule list. | `$rule->getCssRules();` |
| `insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int` | Parses exactly one rule (inheriting `@function` descriptor mode when nested inside `CSSFunctionRule`) and inserts it; returns index. Requires attachment to a stylesheet. Throws `LogicException` if detached, `OutOfBoundsException` for invalid index, `InvalidArgumentException`/`CSSParserLimitException` for invalid/over-limit CSS. | `$sheet->insertRule('@media screen {}'); $group = $sheet->getCssRules()[0]; $group->insertRule('a { color: red }');` |
| `deleteRule(int $index): void` | Removes the rule at index. Requires attachment to a stylesheet (`LogicException` if detached) and valid index (`OutOfBoundsException` if invalid). | `$group->deleteRule(0);` |
| `getCssText(): string` | Serializes at-rule, prelude, and nested rule text iteratively without recursion; enforces `CSSRenderer::MAX_RENDER_DEPTH` and rejects cyclic/DAG-shared trees (`LogicException`). | `$group->getCssText();` |
| `initializeRules(CSSStyleSheet $sheet): void` | Associates this grouping rule with a sheet, retaining its current parent rule, and reparents its list iteratively. | `$group->initializeRules($sheet);` |
| `__get(string $name): mixed` | Exposes `cssRules`; other names delegate to `CSSRule::__get()`. | `$group->cssRules;` |

All additional inherited methods are documented on [`CSSRule`](CSSRule.md).
