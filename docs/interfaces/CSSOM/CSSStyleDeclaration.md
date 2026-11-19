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
| `__construct(string $cssText = '')` | Parses initial CSS text strictly when non-empty. | `InvalidArgumentException` for invalid declarations. | `$style = new CSSStyleDeclaration('color: red');` |
| `parse(string $cssText, bool $strict = true, ?array &$diagnostics = null): self` (static) | Parses declarations; diagnostics append error messages. Non-strict mode skips invalid declarations. | Strict invalid input throws `InvalidArgumentException`. | `$style = CSSStyleDeclaration::parse('color: red');` |
| `parseWithOffsets(string $cssText, bool $strict = true, ?array &$diagnostics = null, int $baseOffset = 0): self` (static) | Parses with diagnostic records `{message, offset}`; offsets include `$baseOffset`. | Strict invalid input throws `InvalidArgumentException`. | `$style = CSSStyleDeclaration::parseWithOffsets('color: red');` |
| `getParentRule(): ?CSSRule` | Returns the associated rule or `null`. | | `$style->getParentRule();` |
| `setParentRule(?CSSRule $parentRule): void` | Sets/clears the associated rule. | | `$style->setParentRule($rule);` |
| `getLength(): int` | Returns declaration count. | | `$style->getLength();` |
| `item(int $index): string` | Returns property name at insertion-order index, or `''` for invalid index. | | `$style->item(0);` |
| `getPropertyValue(string $property): string` | Normalizes name and returns its value, or `''` if absent. | Invalid property names throw `InvalidArgumentException`. | `$style->getPropertyValue('color');` |
| `getPropertyPriority(string $property): string` | Returns `important` or `''`; absent property returns `''`. | Invalid property names throw `InvalidArgumentException`. | `$style->getPropertyPriority('color');` |
| `setProperty(string $property, string $value, string $priority = ''): void` | Validates name, value and priority. Empty value removes the declaration; valid priority is empty or `important`. State remains unchanged on validation failure. | `InvalidArgumentException` for invalid name/value/priority. | `$style->setProperty('color', 'red', 'important');` |
| `removeProperty(string $property): string` | Removes the property and returns its previous value or `''`. | Invalid property names throw `InvalidArgumentException`. | `$old = $style->removeProperty('color');` |
| `clear(): void` | Removes every declaration. | | `$style->clear();` |
| `getCssText(): string` | Serializes declarations in insertion order as `name: value[ !important];`, separated by spaces. | | `$style->getCssText();` |
| `setCssText(string $cssText): void` | Strictly parses a complete replacement before changing current state. | `InvalidArgumentException` on invalid CSS; previous declarations remain. | `$style->setCssText('display: grid');` |
| `getIterator(): Traversable` | Iterates property => `{value, priority}` records in insertion order. | | `foreach ($style as $name => $declaration) {}` |
| `count(): int` | Returns number of declarations. | | `count($style);` |
| `offsetExists(mixed $offset): bool` | True for a string offset naming a present property after normalization. | Invalid names may throw `InvalidArgumentException`. | `isset($style['color']);` |
| `offsetGet(mixed $offset): string` | Returns value for a string property offset; other offset types return `''`. | | `$style['color'];` |
| `offsetSet(mixed $offset, mixed $value): void` | Requires string name and string value, then calls `setProperty()` without priority. | `InvalidArgumentException` for non-string args or invalid declaration. | `$style['color'] = 'blue';` |
| `offsetUnset(mixed $offset): void` | String offset removes that property; non-string offsets are ignored. | Invalid property names throw `InvalidArgumentException`. | `unset($style['color']);` |
| `__toString(): string` | Returns `getCssText()`. | | `(string) $style;` |
| `__get(string $name): mixed` | Supports `cssText`, `length`, `parentRule`, otherwise reads the property named by the accessor conversion. | Invalid converted property names throw `InvalidArgumentException`. | `$style->backgroundColor;` |
| `__set(string $name, mixed $value): void` | `cssText` string replaces declarations; `length`/`parentRule` are rejected; scalar values set a converted property. | `Error` for unsupported types/read-only writes; validation errors propagate. | `$style->backgroundColor = 'red';` |
| `normalizePropertyName(string $property): string` (static) | Trims and validates CSS identifier; lowercases ordinary names but preserves custom-property spelling. | `InvalidArgumentException` for invalid names. | `CSSStyleDeclaration::normalizePropertyName('COLOR');` |
| `validatePropertyValue(string $value): void` (static) | Rejects structural delimiters/unbalanced syntax and top-level `!important` (priority must be passed separately). | `InvalidArgumentException` for invalid values. | `CSSStyleDeclaration::validatePropertyValue('rgb(1 2 3)');` |

No public methods are omitted; parsing internals and accessor conversion helpers are private.
