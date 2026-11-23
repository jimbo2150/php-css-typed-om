<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

/**
 * Enum representing CSS units for numeric values.
 */
enum CSSUnitEnum: string
{
	// Number and percent units
	case NUMBER = '';
	case PERCENT = '%';

	// Length units
	case LENGTH_em = 'em';
	case LENGTH_ex = 'ex';
	case LENGTH_ch = 'ch';
	case LENGTH_ic = 'ic';
	case LENGTH_rem = 'rem';
	case LENGTH_lh = 'lh';
	case LENGTH_rlh = 'rlh';
	case LENGTH_vw = 'vw';
	case LENGTH_vh = 'vh';
	case LENGTH_vi = 'vi';
	case LENGTH_vb = 'vb';
	case LENGTH_vmin = 'vmin';
	case LENGTH_vmax = 'vmax';
	case LENGTH_cm = 'cm';
	case LENGTH_mm = 'mm';
	case LENGTH_Q = 'Q';
	case LENGTH_in = 'in';
	case LENGTH_pt = 'pt';
	case LENGTH_pc = 'pc';
	case LENGTH_px = 'px';
	case LENGTH_cap = 'cap';
	case LENGTH_rcap = 'rcap';
	case LENGTH_cqb = 'cqb';
	case LENGTH_cqh = 'cqh';
	case LENGTH_cqw = 'cqw';
	case LENGTH_cqi = 'cqi';
	case LENGTH_cqmax = 'cqmax';
	case LENGTH_cqmin = 'cqmin';
	case LENGTH_dvb = 'dvb';
	case LENGTH_dvh = 'dvh';
	case LENGTH_dvi = 'dvi';
	case LENGTH_dvmax = 'dvmax';
	case LENGTH_dvmin = 'dvmin';
	case LENGTH_dvw = 'dvw';
	case LENGTH_lvb = 'lvb';
	case LENGTH_lvh = 'lvh';
	case LENGTH_lvi = 'lvi';
	case LENGTH_lvmax = 'lvmax';
	case LENGTH_lvmin = 'lvmin';
	case LENGTH_lvw = 'lvw';
	case LENGTH_rch = 'rch';
	case LENGTH_rex = 'rex';
	case LENGTH_ric = 'ric';
	case LENGTH_svb = 'svb';
	case LENGTH_svh = 'svh';
	case LENGTH_svi = 'svi';
	case LENGTH_svmax = 'svmax';
	case LENGTH_svmin = 'svmin';
	case LENGTH_svw = 'svw';

	// Angle units
	case ANGLE_deg = 'deg';
	case ANGLE_grad = 'grad';
	case ANGLE_rad = 'rad';
	case ANGLE_turn = 'turn';

	// Time units
	case TIME_s = 's';
	case TIME_ms = 'ms';

	// Frequency units
	case FREQ_Hz = 'hz';
	case FREQ_kHz = 'khz';

	// Resolution units
	case RES_dpi = 'dpi';
	case RES_dpcm = 'dpcm';
	case RES_dppx = 'dppx';
	case RES_x = 'x';

	// Flex units
	case FLEX_fr = 'fr';

	/**
	 * Returns the type of the CSS unit in O(1) without array allocation.
	 */
	public function type(): ?CSSUnitTypeEnum
	{
		return match ($this) {
			self::NUMBER => null,
			self::PERCENT => CSSUnitTypeEnum::PERCENT,
			self::ANGLE_deg, self::ANGLE_grad, self::ANGLE_rad, self::ANGLE_turn => CSSUnitTypeEnum::ANGLE,
			self::TIME_s, self::TIME_ms => CSSUnitTypeEnum::TIME,
			self::FREQ_Hz, self::FREQ_kHz => CSSUnitTypeEnum::FREQ,
			self::RES_dpi, self::RES_dpcm, self::RES_dppx, self::RES_x => CSSUnitTypeEnum::RES,
			self::FLEX_fr => CSSUnitTypeEnum::FLEX,
			default => CSSUnitTypeEnum::LENGTH,
		};
	}

	/**
	 * Returns the CSS serialization suffix of the unit (e.g., 'em', '%', '' for number).
	 */
	public function toString(): string
	{
		return match ($this) {
			self::FREQ_Hz => 'Hz',
			self::FREQ_kHz => 'kHz',
			default => $this->value,
		};
	}

	/**
	 * Returns the canonical CSS Typed OM unit name (e.g., 'number', 'percent', 'px', 'x').
	 */
	public function toUnitName(): string
	{
		return match ($this) {
			self::NUMBER => 'number',
			self::PERCENT => 'percent',
			self::FREQ_Hz => 'Hz',
			self::FREQ_kHz => 'kHz',
			default => $this->value,
		};
	}

	/**
	 * Resolves a CSS dimension syntax suffix case-insensitively in O(1) time.
	 * Accepts only valid CSS unit suffixes ('', '%', 'px', 'x', ...), not Typed OM names like 'number' or 'percent'.
	 */
	public static function tryFromSymbol(string $symbol): ?self
	{
		static $lookup = null;
		if ($lookup === null) {
			$lookup = [];
			foreach (self::cases() as $case) {
				$lookup[strtolower($case->value)] = $case;
			}
		}

		return $lookup[strtolower($symbol)] ?? null;
	}

	/**
	 * Resolves either a CSS Typed OM unit name ('number', 'percent', 'flex') or a CSS unit symbol in O(1) time.
	 */
	public static function tryFromUnitName(string $unit): ?self
	{
		static $lookup = null;
		if ($lookup === null) {
			$lookup = [
				'number' => self::NUMBER,
				'percent' => self::PERCENT,
				'flex' => self::FLEX_fr,
			];
			foreach (self::cases() as $case) {
				$lookup[strtolower($case->value)] = $case;
			}
		}

		return $lookup[strtolower($unit)] ?? null;
	}
}
