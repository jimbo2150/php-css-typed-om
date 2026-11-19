# `CSS`

- **PHP type:** `Jimbo2150\PhpCssTypedOm\CSS`
- **Source:** [`src/CSS.php`](../../src/CSS.php)
- **Kind:** abstract utility class

Creates typed CSS numeric values through static magic factories and provides CSSOM identifier escaping. `CSS` is abstract because it is intended as a static utility.

## Public constant

| Constant | Value | Meaning |
| --- | --- | --- |
| `UNIT_MAP` | `['percent' => '%', 'number' => '', 'flex' => 'fr']` | Maps the special factory names to their CSS unit symbols. |

## Methods

### `__callStatic(string $name, array $arguments): CSSUnitValue`

PHP invokes this factory for supported unit calls such as `CSS::px(12)`; those factories are declared in the source PHPDoc. It resolves the unit name or symbol, validates that the first argument is a finite numeric string, integer, or float, and returns a [`CSSUnitValue`](TypedOM/CSSUnitValue.md).

**Parameters**

- `$name`: factory name or CSS unit symbol, case-insensitive for alphabetic names (for example `px`, `percent`, `deg`, or `%`).
- `$arguments`: arguments supplied to the static call. The first entry must be numeric; additional entries are ignored.

**Throws:** `BadMethodCallException` for unsupported names; `InvalidArgumentException` if the first argument is missing, nonnumeric, or non-finite.

```php
use Jimbo2150\PhpCssTypedOm\CSS;

$width = CSS::px(12);
$ratio = CSS::percent('37.5');
echo $width; // 12px
```

Available unit factories (all take one `string|int|float $value` and return `CSSUnitValue`): `percent`, `number`, `flex`, `em`, `ex`, `ch`, `ic`, `rem`, `lh`, `rlh`, `vw`, `vh`, `vi`, `vb`, `vmin`, `vmax`, `cm`, `mm`, `Q`, `in`, `pt`, `pc`, `px`, `deg`, `grad`, `rad`, `turn`, `s`, `ms`, `hz`, `khz`, `dpi`, `dpcm`, `dppx`, `fr`, `cap`, `rcap`, `cqb`, `cqh`, `cqw`, `cqi`, `cqmax`, `cqmin`, `dvb`, `dvh`, `dvi`, `dvmax`, `dvmin`, `dvw`, `lvb`, `lvh`, `lvi`, `lvmax`, `lvmin`, `lvw`, `rch`, `rex`, `ric`, `svb`, `svh`, `svi`, `svmax`, `svmin`, and `svw`. `number` is unitless; `percent` maps to `%`; `flex` and `fr` map to `fr`.

The source PHPDoc also advertises an `lch()` factory, but no `lch` unit exists in `CSSUnitEnum`; calling `CSS::lch()` therefore throws `BadMethodCallException` at runtime.

### `translateUnit(string $name): string`

Returns the symbol in `UNIT_MAP` for a special factory name, or the input unchanged if no translation exists.

**Parameters:** `$name` is the unit/factory name to translate. Pass lowercase special names for consistent behavior.

```php
echo CSS::translateUnit('percent'); // %
echo CSS::translateUnit('px');      // px
```

### `escape(string|int|float|null $value): string`

Escapes a value as a CSS identifier following CSSOM identifier escaping rules. It replaces NUL and malformed UTF-8 with U+FFFD, escapes leading digits and unsafe characters, and returns an empty string for an empty input. This is CSS identifier escaping, not HTML escaping or general CSS-value sanitization.

**Parameters:** `$value` is converted to a string before escaping; `null` yields U+FFFD.

```php
echo CSS::escape('2 column'); // \32 \ column
echo CSS::escape('--theme'); // --theme
```
