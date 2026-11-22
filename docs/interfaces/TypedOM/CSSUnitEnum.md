# CSSUnitEnum

**PHP name:** `Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum`  
**Source:** [`src/TypedOM/Values/Numeric/CSSUnitEnum.php`](../../../src/TypedOM/Values/Numeric/CSSUnitEnum.php)  
**Purpose:** String-backed enum of recognized CSS number, percentage, length, angle, time, frequency, resolution, and flex units.

## Public API

### Cases

The enum cases and their backing symbols are: `NUMBER=''`, `PERCENT='%'`; length cases `LENGTH_em`, `LENGTH_ex`, `LENGTH_ch`, `LENGTH_ic`, `LENGTH_rem`, `LENGTH_lh`, `LENGTH_rlh`, `LENGTH_vw`, `LENGTH_vh`, `LENGTH_vi`, `LENGTH_vb`, `LENGTH_vmin`, `LENGTH_vmax`, `LENGTH_cm`, `LENGTH_mm`, `LENGTH_Q`, `LENGTH_in`, `LENGTH_pt`, `LENGTH_pc`, `LENGTH_px`, `LENGTH_cap`, `LENGTH_rcap`, `LENGTH_cqb`, `LENGTH_cqh`, `LENGTH_cqw`, `LENGTH_cqi`, `LENGTH_cqmax`, `LENGTH_cqmin`, `LENGTH_dvb`, `LENGTH_dvh`, `LENGTH_dvi`, `LENGTH_dvmax`, `LENGTH_dvmin`, `LENGTH_dvw`, `LENGTH_lvb`, `LENGTH_lvh`, `LENGTH_lvi`, `LENGTH_lvmax`, `LENGTH_lvmin`, `LENGTH_lvw`, `LENGTH_rch`, `LENGTH_rex`, `LENGTH_ric`, `LENGTH_svb`, `LENGTH_svh`, `LENGTH_svi`, `LENGTH_svmax`, `LENGTH_svmin`, `LENGTH_svw`; angle `ANGLE_deg`, `ANGLE_grad`, `ANGLE_rad`, `ANGLE_turn`; time `TIME_s`, `TIME_ms`; frequency `FREQ_Hz`, `FREQ_kHz`; resolution `RES_dpi`, `RES_dpcm`, `RES_dppx`, `RES_x`; flex `FLEX_fr`. Backed values use the symbols shown in source; lookup is case-insensitive.

The enum exposes the standard public readonly `name: string` and `value: string` properties. The standard backed-enum public methods `cases(): array`, `from(int|string $value): static`, and `tryFrom(int|string $value): static|null` are also available. `from` throws `ValueError` for an unknown symbol; `tryFrom` returns null. Example: `$px = CSSUnitEnum::from('px');`.

| Method | Signature and behavior |
|---|---|
| `type` | `public function type(): ?CSSUnitTypeEnum`; maps case-name prefixes to `LENGTH`, `PERCENT`, `ANGLE`, `TIME`, `FREQ`, `RES`, or `FLEX`; `NUMBER` and any unrecognized prefix yield null. Example: `$category = CSSUnitEnum::LENGTH_px->type();`. |
| `toString` | `public function toString(): string`; returns the CSS serialization suffix (`''` for `NUMBER`, `'%'` for `PERCENT`, `'x'` for `RES_x`, etc.). |
| `toUnitName` | `public function toUnitName(): string`; returns the canonical CSS Typed OM unit name (`'number'` for `NUMBER`, `'percent'` for `PERCENT`, and `toString()` for all other units). |
| `tryFromSymbol` | `public static function tryFromSymbol(string $symbol): ?self`; strictly resolves CSS dimension syntax suffixes (`''`, `'%'`, `'px'`, `'x'`, ...) case-insensitively in O(1) time (rejecting Typed OM names like `'number'` or `'percent'`). |
| `tryFromUnitName` | `public static function tryFromUnitName(string $unit): ?self`; resolves either Typed OM unit names (`'number'`, `'percent'`, `'flex'`) or CSS unit symbols case-insensitively in O(1) time. |
