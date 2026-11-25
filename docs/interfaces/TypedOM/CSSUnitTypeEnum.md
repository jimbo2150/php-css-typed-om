# CSSUnitTypeEnum

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitTypeEnum`  
**Source:** [`src/TypedOM/Values/Numeric/CSSUnitTypeEnum.php`](../../../src/TypedOM/Values/Numeric/CSSUnitTypeEnum.php)  
**Purpose:** String-backed enum of numeric dimension names and `percentHint`.

## Public API

Cases: `LENGTH='length'`, `ANGLE='angle'`, `TIME='time'`, `FREQ='frequency'`, `RES='resolution'`, `FLEX='flex'`, `PERCENT='percent'`, and `PERCENT_HINT='percentHint'`. The enum exposes standard public readonly properties `name: string` and `value: string`.

The backed-enum public methods `cases(): array`, `from(int|string $value): static`, and `tryFrom(int|string $value): static|null` are available; `from` throws `ValueError` for unknown backing values. Example: `$dimension = CSSUnitTypeEnum::from('length');`.

| Method | Signature and behavior |
|---|---|
| `verifyValue` | `public function verifyValue(string|int $value): Exception|string`; validates a dimension value and returns the PHP type name used for coercion (`int`) or returns an `InvalidArgumentException` object for invalid values. For `percentHint`, strings must name another dimension case (excluding `'percentHint'`), rejecting integers with `InvalidArgumentException`; for all other cases, integer values and canonical signed-64-bit-range integer strings (`'00'` / `'-0'` remain valid, overflowing integer strings return `InvalidArgumentException`) coerce to `'int'` and non-integer strings return `InvalidArgumentException`. Example: `$kind = CSSUnitTypeEnum::LENGTH->verifyValue(2);`. |
