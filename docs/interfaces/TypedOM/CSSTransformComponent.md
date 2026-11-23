# CSSTransformComponent

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformComponent`  
**Source:** [`src/TypedOM/Values/CSSTransformComponent.php`](../../../src/TypedOM/Values/CSSTransformComponent.php)  
**Purpose:** Abstract base for transform-function values, holding serialized arguments and 2D/3D state and providing matrix helpers.

## Public API

No public properties, constants, or enum cases. It inherits `parse`, `parseAll`, and the `Stringable` contract from [CSSStyleValue](CSSStyleValue.md#public-api).

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(string $functionName, array $values, bool $twoDimensional = true)`; stores the function identifier, its ordered `list<CSSStyleValue|string>` arguments and 2D flag without validation. Example (from a subclass): `parent::__construct('translate', [$x, $y]);`. |
| `getValues` | `public function getValues(): array`; returns the stored argument list. Example: `$args = $component->getValues();`. |
| `getFunctionName` | `public function getFunctionName(): string`; returns the stored function name. Example: `$name = $component->getFunctionName();`. |
| `is2D` | `public function is2D(): bool`; returns the 2D flag. Example: `$twoDimensional = $component->is2D();`. |
| `setIs2D` | `public function setIs2D(bool $is2D): void`; changes the 2D flag. Example: `$component->setIs2D(false);`. |
| `toMatrix` | `public function toMatrix(): DOMMatrix`; base implementation returns a 4x4 identity `DOMMatrix`. Concrete components override this with their transform matrix behavior. Example: `$matrix = $component->toMatrix();`. |
| `clone` | `public function clone(): static`; deep-clones CSSStyleValue arguments, copies string arguments, then constructs the same runtime class with stored function name and 2D flag. Example: `$copy = $component->clone();`. |
| `__toString` | `public function __toString(): string`; emits `functionName(arg, arg)` using the stored list. Example: `$css = (string) $component;`. |
| `__get` | `public function __get(string $name): mixed`; exposes virtual read-only `is2D`; other names throw `Error`. Example: `$flag = $component->is2D;`. |
| `__set` | `public function __set(string $name, mixed $value): void`; accepts a boolean write to virtual `is2D`; otherwise throws `Error`. Example: `$component->is2D = false;`. |
| `identityMatrix` | `public static function identityMatrix(): array`; returns the 4x4 column-major identity matrix as `list<float>`. Example: `$identity = CSSTransformComponent::identityMatrix();`. |
| `multiplyMatrices` | `public static function multiplyMatrices(array $left, array $right): array`; multiplies two 16-element column-major 4x4 matrices and returns a `list<float>`. It assumes correctly sized numeric arrays; there is no explicit input validation. Example: `$combined = CSSTransformComponent::multiplyMatrices($left, $right);`. |
