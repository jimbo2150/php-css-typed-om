# `CSSStyleDeclaration`

- **PHP name:** `Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration`
- **Source:** [`src/CSSOM/CSSStyleDeclaration.php`](../../../src/CSSOM/CSSStyleDeclaration.php)
- **Purpose:** Ordered, mutable CSS declaration block with structural validation and CSS serialization.
- **Implements:** `ArrayAccess<string, string>`, `Countable`, `IteratorAggregate<string, array{value: string, priority: string}>`.

## Properties

Magic `cssText` reads/writes serialized declarations; `length` and `parentRule` are read-only. Other names are treated as CSS property accessors (camel case maps to kebab case; `cssFloat` maps to `float`; vendor prefixes are prefixed). Magic assignment accepts string, integer, or float property values. No public constants or instance properties are declared.

## Public Methods

| Signature | Parameters / behavior | Throws / result | Example |
|---|---|---|---|
| `__construct(string $cssText = '', ?CSSParserOptions $options = null)` | Parses initial CSS text strictly when non-empty under `$options` limits and persists all resolved limits (`maxBytes`, `maxDepth`, `maxMathDepth`, `maxDeclarations`). | `InvalidArgumentException` (or `CSSParserLimitException`) for invalid declarations. | `$style = new CSSStyleDeclaration('color: red');` |
| `parse(string $cssText, bool $strict = true, ?array &$diagnostics = null, ?CSSParserOptions $options = null): static` (static) | Parses declarations under `$options` limits and persists resolved limits (`maxBytes`, `maxDepth`, `maxMathDepth`, `maxDeclarations`) on the returned declaration; diagnostics append error messages up to `maxErrors`. Non-strict mode skips invalid declarations. | Strict invalid input throws `InvalidArgumentException` (or `CSSParserLimitException`). | `$style = CSSStyleDeclaration::parse('color: red');` |
| `parseWithOffsets(string $cssText, bool $strict = true, ?array &$diagnostics = null, int $baseOffset = 0, ?CSSParserOptions $options = null): static` (static) | Parses with diagnostic records `{message, offset}` while persisting resolved `$options` limits; rejects negative `$baseOffset` and integer offset overflow, adds `$baseOffset` to offsets, and enforces control-byte checks even on empty/comment-only slices. | Strict invalid input throws `InvalidArgumentException` (or `CSSParserLimitException`). | `$style = CSSStyleDeclaration::parseWithOffsets('color: red');` |
| `getAllowedDescriptors(): ?array` (static) | Returns allowed descriptor names when restricted by a descriptor subclass, or `null` when unrestricted. | | `$allowed = CSSStyleDeclaration::getAllowedDescriptors();` |
| `getParentRule(): ?CSSRule` | Returns the associated rule or `null`. | | `$style->getParentRule();` |
| `getLength(): int` | Returns stored declaration count (with standard shorthands expanded into constituent longhands per CSSOM §6.7). | | `$style->getLength();` |
| `item(int $index): string` | Returns stored longhand/custom property name at insertion-order index, or `''` for invalid index. | | `$style->item(0);` |
| `getPropertyValue(string $property): string` | Normalizes name and returns its value (reconstructing canonical shortest shorthand syntax for shorthands, or `''` on missing/conflicting longhand priorities/mixed values). | Invalid property names throw `InvalidArgumentException`. | `$style->getPropertyValue('color');` |
| `getPropertyPriority(string $property): string` | Returns `important` or `''` (for shorthands, `important` only when all constituent longhands have `important`); absent property returns `''`. | Invalid property names throw `InvalidArgumentException`. | `$style->getPropertyPriority('color');` |
| `setProperty(string $property, string $value, string $priority = ''): void` | Validates name, value (inheriting the owning rule/stylesheet `maxBytes`, or stored/default budget when detached), and priority, plus `maxDeclarations` (counting expanded longhands and rejecting comment-only values on non-custom properties). Expands shorthands into constituent longhands (or pending-substitution/system-font placeholders) atomically. Empty value removes the declaration (or all constituent longhands); valid priority is empty or `important`. State remains unchanged on failure. | `InvalidArgumentException` (or `CSSParserLimitException`) for invalid input/limits. | `$style->setProperty('color', 'red', 'important');` |
| `removeProperty(string $property): string` | Removes the property (or all constituent longhands for a shorthand, updating attached sheet declaration count) and returns its previous value or `''`. | Invalid property names throw `InvalidArgumentException`. | `$old = $style->removeProperty('color');` |
| `clear(): void` | Removes every declaration and updates attached sheet declaration count. | | `$style->clear();` |
| `getCssText(): string` | Serializes declarations in insertion order as `name: value[ !important];`, collapsing longhands into canonical shortest shorthands (respecting logical/physical cascade barriers and omitting orphaned pending-substitution longhands), separated by spaces. | | `$style->getCssText();` |
| `setCssText(string $cssText, ?CSSParserOptions $options = null): void` | Strictly parses a complete replacement under `$options` limits (inheriting owning stylesheet/rule/stored limits when `$options === null`) before changing current state atomically. | `InvalidArgumentException` (or `CSSParserLimitException`) on failure; previous declarations remain. | `$style->setCssText('display: grid');` |
| `__clone(): void` | Detaches `parentRule`, snapshots resolved `maxBytes` / `maxDeclarations` limits, and clears cached key index on cloned block. | | `$copy = clone $style;` |
| `getIterator(): Traversable` | Iterates property => `{value, priority}` records in insertion order. | | `foreach ($style as $name => $declaration) {}` |
| `count(): int` | Returns number of declarations. | | `count($style);` |
| `offsetExists(mixed $offset): bool` | True for a string offset naming a present property after normalization. | Invalid names may throw `InvalidArgumentException`. | `isset($style['color']);` |
| `offsetGet(mixed $offset): string` | Returns value for a string property offset; other offset types return `''`. | | `$style['color'];` |
| `offsetSet(mixed $offset, mixed $value): void` | Requires string name and string value, then calls `setProperty()` without priority. | `InvalidArgumentException` for non-string args or invalid declaration. | `$style['color'] = 'blue';` |
| `offsetUnset(mixed $offset): void` | String offset removes that property; non-string offsets are ignored. | Invalid property names throw `InvalidArgumentException`. | `unset($style['color']);` |
| `__toString(): string` | Returns `getCssText()`. | | `(string) $style;` |
| `__get(string $name): mixed` | Supports `cssText`, `length`, `parentRule`, otherwise reads the property named by the accessor conversion. | Invalid converted property names throw `InvalidArgumentException`. | `$style->backgroundColor;` |
| `__set(string $name, mixed $value): void` | `cssText` string replaces declarations; `length`/`parentRule` are rejected; scalar values set a converted property. | `Error` for unsupported types/read-only writes; validation errors propagate. | `$style->backgroundColor = 'red';` |
| `__isset(string $name): bool` | True for `cssText`, `length`, `parentRule` (when attached), or a present normalized property key. | Invalid property names may throw `InvalidArgumentException`. | `isset($style->color);` |
| `normalizePropertyName(string $property): string` (static) | Trims CSS whitespace and validates CSS identifier + UTF-8 validity (rejecting bare `'--'` and decoded control bytes); canonicalizes custom-property escapes via `CSS::escape()` (`--*`) while lowercasing ordinary names. | `InvalidArgumentException` for invalid names. | `CSSStyleDeclaration::normalizePropertyName('COLOR');` |
| `validatePropertyValue(string $value): void` (static) | Rejects structural delimiters/unbalanced syntax and top-level `!important` (priority must be passed separately). | `InvalidArgumentException` for invalid values. | `CSSStyleDeclaration::validatePropertyValue('rgb(1 2 3)');` |
| `getPropertySourceSpan(string $property): ?CSSSourceSpan` | Returns the parsed [`CSSSourceSpan`](CSSSourceSpan.md) for `$property` (or shared shorthand span when all constituent longhands retain the identical originating span), cleared on explicit mutation; returns `null` when unavailable. | Invalid names throw `InvalidArgumentException`. | `$style->getPropertySourceSpan('color');` |
| `getSourceSpans(): array` | Returns tracked [`CSSSourceSpan`](CSSSourceSpan.md) objects keyed by canonical longhand/custom property name (`[]` when `trackSpans` was false or cleared). | `array<string, CSSSourceSpan>` | `$style->getSourceSpans();` |

Parsing internals and accessor conversion helpers are private.
