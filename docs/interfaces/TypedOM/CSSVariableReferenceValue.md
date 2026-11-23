# CSSVariableReferenceValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSVariableReferenceValue`  
**Source:** [`src/TypedOM/Values/CSSVariableReferenceValue.php`](../../../src/TypedOM/Values/CSSVariableReferenceValue.php)  
**Purpose:** Represents a `var(--custom-property[, fallback])` reference. The fallback is stored as a `CSSUnparsedValue`.

## Public API

No public constants or enum cases. It inherits `parse(string $propertyOrCssText, ?string $cssText = null, ?CSSParserOptions $options = null): CSSStyleValue` and `parseAll(string $property, string $cssText, ?CSSParserOptions $options = null): array` from [CSSStyleValue](CSSStyleValue.md#public-api); the base page documents parameters, behavior, exceptions, and examples.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(string $variable, CSSUnparsedValue|string|null $fallback = null, ?CSSParserOptions $options = null, int $depth = 0)`; normalizes/validates a custom-property name and validates an optional fallback under `$options` (`maxDepth`, `maxBytes`). Throws `InvalidArgumentException` for a non-custom-property name or structurally invalid fallback, or `CSSParserLimitException` on limit exhaustion. Example: `$ref = new CSSVariableReferenceValue('--accent', 'teal');`. |
| `getVariable` | `public function getVariable(): string`; returns the normalized custom-property name. Example: `$name = $ref->getVariable();`. |
| `setVariable` | `public function setVariable(string $variable): void`; normalizes the name and requires the `--` prefix; throws `InvalidArgumentException` otherwise. Example: `$ref->setVariable('--brand-color');`. |
| `getFallback` | `public function getFallback(): ?CSSUnparsedValue`; returns the fallback or `null`. Example: `$fallback = $ref->getFallback();`. |
| `setFallback` | `public function setFallback(CSSUnparsedValue|string|null $fallback): void`; stores `null`, validates/stores a supplied unparsed value, or validates and converts text to `CSSUnparsedValue`; throws `InvalidArgumentException` on invalid structure/control bytes. Example: `$ref->setFallback('blue');`. |
| `clone` | `public function clone(): static`; copies the variable and deep-clones its fallback. Example: `$copy = $ref->clone();`. |
| `__toString` | `public function __toString(): string`; serializes `var(name)` or `var(name, fallback)`. Example: `$css = (string) $ref;`. |
| `__get` | `public function __get(string $name): mixed`; magic read-only `variable` and `fallback`; unknown properties throw `Error`. Example: `$name = $ref->variable;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; magic writes to `variable` (string) or `fallback` (string, `CSSUnparsedValue`, or `null`) via validated setters; invalid name/type throws `Error`. Example: `$ref->fallback = 'navy';`. |
