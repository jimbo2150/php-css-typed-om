<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

/**
 * Enum representing CSS units for numeric values.
 * This enum defines various CSS units categorized by type (e.g., length, angle, time).
 * Each case corresponds to a specific unit string used in CSS.
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
	case LENGTH_rch = 'lch';
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
	case FEQ_kHz = 'khz';

	// Resolution units
	case RES_dpi = 'dpi';
	case RES_dpcm = 'dpcm';
	case RES_dppx = 'dppx';

	// Flex units
	case FLEX_fr = 'fr';

	/**
	 * Returns the type of the CSS unit.
	 *
	 * @return CSSUnitTypeEnum|null The unit type or null if not found.
	 */
	public function type(): ?CSSUnitTypeEnum {
		[$type] = $this->splitType();
		return CSSUnitTypeEnum::{$type} ?? null;
	}

	/**
	 * Returns the string representation of the unit.
	 *
	 * @return string The unit string (e.g., 'em', '%').
	 */
	public function toString(): string {
		$parts = $this->splitType();
		if(count($parts) > 1) {
			return $parts[1];
		}
		return $this->value;
	}

	/**
	 * Splits the enum name by '_' to extract type and unit.
	 *
	 * @return array The parts of the name.
	 */
	private function splitType(): array {
		return explode('_', $this->name);
	}
}