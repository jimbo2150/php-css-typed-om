# CSSNumericType

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType`  
**Source:** [`src/TypedOM/Values/Numeric/CSSNumericType.php`](../../../src/TypedOM/Values/Numeric/CSSNumericType.php)  
**Purpose:** Mutable exponent map describing a numeric value's dimensions, including an optional percentage hint.

## Public API

No declared public properties, constants, or enum cases. Dimensions are magic properties; the instance stores only supplied dimensions, while absent dimension reads return zero and absent `percentHint` reads return null.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(array $dimensions = [])`; accepts dimension-name to integer/string map and validates each entry through `__set`; invalid dimensions or values throw `ValueError`/`InvalidArgumentException`. Example: `$type = new CSSNumericType(['length' => 1]);`. |
| `fromDimensions` | `public static function fromDimensions(array $dimensions): self`; creates a type requiring integer exponents for all dimensions except `percentHint` (which must be a valid non-`percentHint` dimension-name string); throws `InvalidArgumentException` for invalid types/values. Example: `$type = CSSNumericType::fromDimensions(['length' => 1]);`. |
| `__set` | `public function __set(string $key, string|int $value): void`; resolves the key through `CSSUnitTypeEnum` and validates through `verifyValue()`. Ordinary dimensions accept integers or integer-looking strings (storing `int`); `percentHint` requires a recognized dimension-name string (not `'percentHint'`). Invalid keys throw `ValueError`, and invalid values throw `InvalidArgumentException`. Example: `$type->length = 2;`. |
| `__get` | `public function __get(string $key): int|string|null`; returns stored exponent (default 0) or hint (default null); unknown names fail through `CSSUnitTypeEnum::from` with `ValueError`. Example: `$power = $type->length;`. |
| `__isset` | `public function __isset(string $key): bool`; true only for a recognized dimension whose value is set; unknown keys return false. Example: `$hasLength = isset($type->length);`. |
| `toArray` | `public function toArray(): array`; returns the stored dimensions only (`array<string,int|string>`). Example: `$dimensions = $type->toArray();`. |
| `applyPercentHint` | `public function applyPercentHint(string $hint): self`; validates `$hint` through `CSSUnitTypeEnum::tryFrom($hint)` (rejecting unknown names and `'percentHint'` with `InvalidArgumentException`), folds any `percent` exponent into `$hint` (keeping `'percent'` unchanged when `$hint === 'percent'`), and records `'percentHint' => $hint`. Example: `$hinted = $percentType->applyPercentHint('length');`. |
| `multiply` | `public function multiply(self $other): self`; applies any non-null `percentHint` to the unhinted side per W3C Typed OM, rejects conflicting non-null hints with `InvalidArgumentException`, and sums non-zero exponents. Example: `$productType = $lengthType->multiply($numberType);`. |
| `invert` | `public function invert(): self`; negates all stored numeric exponents while preserving percentage hint. Example: `$inverse = $type->invert();`. |
| `equals` | `public function equals(self $other): bool`; compares all supported dimensions with missing exponents treated as zero and percent hints compared exactly. Example: `$same = $type->equals($other);`. |
