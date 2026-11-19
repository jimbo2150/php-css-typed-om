# CSSNumericType

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType`  
**Source:** [`src/TypedOM/Values/Numeric/CSSNumericType.php`](../../../src/TypedOM/Values/Numeric/CSSNumericType.php)  
**Purpose:** Mutable exponent map describing a numeric value's dimensions, including an optional percentage hint.

## Public API

No declared public properties, constants, or enum cases. Dimensions are magic properties; the instance stores only supplied dimensions, while absent dimension reads return zero and absent `percentHint` reads return null.

| Method/property | Signature and behavior |
|---|---|
| `__construct` | `public function __construct(array $dimensions = [])`; accepts dimension-name to integer/string map and validates each entry through `__set`; invalid dimensions or values throw `ValueError`/`InvalidArgumentException`. Example: `$type = new CSSNumericType(['length' => 1]);`. |
| `fromDimensions` | `public static function fromDimensions(array $dimensions): self`; creates a type after requiring integer exponents for all dimensions except `percentHint` (which may be an integer or a valid dimension-name string); throws `InvalidArgumentException` for other exponent types. Example: `$type = CSSNumericType::fromDimensions(['length' => 1]);`. |
| `__set` | `public function __set(string $key, string|int $value): void`; resolves the key through `CSSUnitTypeEnum` and stores the value after the enum's coercion rule. Integer and integer-string exponents become integers, but other strings for ordinary dimensions are accepted and remain strings; `percentHint` accepts an integer or a recognized dimension name. Invalid keys throw `ValueError`, and invalid hints throw `InvalidArgumentException`. Example: `$type->length = 2;`. |
| `__get` | `public function __get(string $key): int|string|null`; returns stored exponent (default 0) or hint (default null); unknown names fail through `CSSUnitTypeEnum::from` with `ValueError`. Example: `$power = $type->length;`. |
| `__isset` | `public function __isset(string $key): bool`; true only for a recognized dimension whose value is set; unknown keys return false. Example: `$hasLength = isset($type->length);`. |
| `toArray` | `public function toArray(): array`; returns the stored dimensions only (`array<string,int|string>`). Example: `$dimensions = $type->toArray();`. |
| `multiply` | `public function multiply(self $other): self`; adds corresponding dimension exponents and carries the first available percent hint. Example: `$productType = $lengthType->multiply($numberType);`. |
| `invert` | `public function invert(): self`; negates all stored numeric exponents while preserving percentage hint. Example: `$inverse = $type->invert();`. |
| `equals` | `public function equals(self $other): bool`; compares all supported dimensions with missing exponents treated as zero and percent hints compared exactly. Example: `$same = $type->equals($other);`. |
