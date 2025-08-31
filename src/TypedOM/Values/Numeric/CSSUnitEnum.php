<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

enum CSSUnitEnum: string
{
	case NUMBER = '';
	case PERCENT = '%';

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

	case ANGLE_deg = 'deg';
	case ANGLE_grad = 'grad';
	case ANGLE_rad = 'rad';
	case ANGLE_turn = 'turn';

	case TIME_s = 's';
	case TIME_ms = 'ms';

	case FREQ_Hz = 'hz';
	case FEQ_kHz = 'khz';

	case RES_dpi = 'dpi';
	case RES_dpcm = 'dpcm';
	case RES_dppx = 'dppx';

	case FLEX_fr = 'fr';

	public function type(): ?CSSUnitTypeEnum {
		[$type] = $this->splitType();
		return CSSUnitTypeEnum::{$type} ?? null;
	}

	public function toString(): string {
		if($this === self::PERCENT) {
			return 'percent';
		}
		$parts = $this->splitType();
		if(count($parts) > 1) {
			return $parts[1];
		}
		return $this->value;
	}

	private function splitType(): array {
		return explode('_', $this->name);
	}
}