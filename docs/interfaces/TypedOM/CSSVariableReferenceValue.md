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
| `setFallback` | `public function setFallback(CSSUnparsedValue|string|null $fallback, ?CSSParserOptions $options = null, int $depth = 0): void`; stores `null`, validates/clones a supplied unparsed value, or validates and converts text to `CSSUnparsedValue` honoring `$options` (defaulting to the instance's byte limit); throws `InvalidArgumentException` on invalid structure/control bytes or `CSSParserLimitException` on limit exhaustion. Example: `$ref->setFallback('blue');`. |
| `clone` | `public function clone(): static`; copies the variable and deep-clones its fallback. Example: `$copy = $ref->clone();`. |
| `__toString` | `public function __toString(): string`; serializes `var(name)` or `var(name, fallback)` while enforcing the stricter of the instance's and fallback's byte limits. Example: `$css = (string) $ref;`. |
| `$variable` (property hook) | `public string $variable { get; set; }`; reads normalized custom-property name and writes through validated `--*` normalization + instance byte limit. Unknown properties via `__get`/`__set` throw `Error`. Example: `$name = $ref->variable;`. |
| `$fallback` (property hook) | `public ?CSSUnparsedValue $fallback { get; set; }`; reads live fallback `CSSUnparsedValue` (or `null`) and validates/stores `CSSUnparsedValue|string|null` assignments via `normalizeFallback()` honoring the instance's byte limit. Example: `$ref->fallback = 'navy';`. |
