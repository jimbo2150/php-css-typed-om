# `CSSStyleProperties`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleProperties`
- **Source:** [`src/CSSOM/CSSStyleProperties.php`](../../../src/CSSOM/CSSStyleProperties.php)
- **Purpose:** Style declaration block used by `CSSStyleRule`, `CSSKeyframeRule`, and `CSSNestedDeclarations`.
- **Inheritance:** `CSSStyleDeclaration`; inherited public API: [`CSSStyleDeclaration`](CSSStyleDeclaration.md).

## Additional Public Methods

- `public static function fromDeclaration(?CSSStyleDeclaration $style): self` — returns `$style` if it is a detached `CSSStyleProperties` (cloning it if already parented), or copies its declarations into a new `CSSStyleProperties` while propagating resolved limit budgets (`maxBytes`, `maxDepth`, `maxMathDepth`, `maxDeclarations`) monotonically.
- `public function getCssFloat(): string` — returns the value of the `float` property.
- `public function setCssFloat(string $value): void` — sets the `float` property.
