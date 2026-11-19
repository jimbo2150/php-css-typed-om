# CSSUnitTypeEnum

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitTypeEnum`  
**Source:** [`src/TypedOM/Values/Numeric/CSSUnitTypeEnum.php`](../../../src/TypedOM/Values/Numeric/CSSUnitTypeEnum.php)  
**Purpose:** String-backed enum of numeric dimension names and `percentHint`.

## Public API

Cases: `LENGTH='length'`, `ANGLE='angle'`, `TIME='time'`, `FREQ='frequency'`, `RES='resolution'`, `FLEX='flex'`, `PERCENT='percent'`, and `PERCENT_HINT='percentHint'`. The enum exposes standard public readonly properties `name: string` and `value: string`.

The backed-enum public methods `cases(): array`, `from(int|string $value): static`, and `tryFrom(int|string $value): static|null` are available; `from` throws `ValueError` for unknown backing values. Example: `$dimension = CSSUnitTypeEnum::from('length');`.

| Method | Signature and behavior |
|---|---|
| `verifyValue` | `public function verifyValue(string|int $value): Exception|string`; validates a dimension value and returns the PHP type name used for coercion (`int` or `string`), or returns an `InvalidArgumentException` object for an invalid `percentHint`. For `percentHint`, integer input is accepted as integer; strings must name another enum case. For other cases, integer-looking values become `int`, all others remain `string`. Example: `$kind = CSSUnitTypeEnum::LENGTH->verifyValue(2);`. |
