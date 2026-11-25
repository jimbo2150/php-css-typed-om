# CSSVariableReferenceValue

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSVariableReferenceValue`  
**Source:** [`src/TypedOM/Values/CSSVariableReferenceValue.php`](../../../src/TypedOM/Values/CSSVariableReferenceValue.php)  
**Purpose:** Represents a `var(--custom-property[, fallback])` reference. The fallback is stored as a `CSSUnparsedValue`.

## Public API

No public constants or enum cases. It inherits `parse(string $propertyOrCssText, ?string $cssText = null, ?CSSParserOptions $options = null): CSSStyleValue` and `parseAll(string $property, string $cssText, ?CSSParserOptions $options = null): array` from [CSSStyleValue](CSSStyleValue.md#public-api); the base page documents parameters, behavior, exceptions, and examples.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(string $variable, CSSUnparsedValue|string|null $fallback = null, ?CSSParserOptions $options = null, int $depth = 0)`; normalizes/validates a custom-property name (rejecting bare `'--'` and decoded control bytes) and validates an optional fallback under `$options` (`maxDepth`, `maxBytes`) plus combined `var(...)` serialized byte length. Throws `InvalidArgumentException` for a non-custom-property name or structurally invalid fallback, or `CSSParserLimitException` on limit exhaustion. Example: `$ref = new CSSVariableReferenceValue('--accent', 'teal');`. |
| `getVariable` | `public function getVariable(): string`; returns the normalized custom-property name. Example: `$name = $ref->getVariable();`. |
| `setVariable` | `public function setVariable(string $variable): void`; normalizes the name (requiring the `--*` prefix) and transactionally validates `$this->maxBytes` plus every ancestor's own `maxBytes` (rolling back on failure); throws `InvalidArgumentException` or `CSSParserLimitException`. Example: `$ref->setVariable('--brand-color');`. |
| `getFallback` | `public function getFallback(): ?CSSUnparsedValue`; returns the fallback or `null`. Example: `$fallback = $ref->getFallback();`. |
| `setFallback` | `public function setFallback(CSSUnparsedValue|string|null $fallback, ?CSSParserOptions $options = null, int $depth = 0): void`; `null` detaches and clears the fallback immediately; otherwise computes per-ancestor remaining `maxDepth`, clamps inserted trees to ancestor `maxBytes`/`maxDepth`, and validates every ancestor's own `maxBytes` transactionally; throws `InvalidArgumentException` on invalid structure/control bytes or `CSSParserLimitException` on limit exhaustion. Example: `$ref->setFallback('blue');`. |
| `__clone` / `clone` | `public function __clone(): void`; `public function clone(): static`; copies the variable, deep-clones its fallback with `maxBytes`/`maxDepth` propagation and clean parent re-linking, and detaches the copy's parent reference. Example: `$copy = $ref->clone();`. |
| `__toString` | `public function __toString(): string`; serializes `var(name)` or `var(name, fallback)`, enforcing `$this->maxBytes` on the full outer `var(...)` string and `$fallback->maxBytes` only on the fallback's own content. Example: `$css = (string) $ref;`. |
| `__isset` / hooks | `__isset(string $name): bool` always returns `false`; `public string $variable { get; set; }` routes through `setVariable()`, and `public ?CSSUnparsedValue $fallback { get; set; }` routes through `setFallback()`. Unknown properties via `__get`/`__set` throw `Error`. Example: `$ref->fallback = 'navy';`. |
