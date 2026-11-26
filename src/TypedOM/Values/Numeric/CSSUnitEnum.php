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

	/** @var array<string, float> */
	private const array CANONICAL_FACTORS_BY_SYMBOL = [
		'px' => 1.0,
		'cm' => 96.0 / 2.54,
		'mm' => 96.0 / 25.4,
		'q' => 96.0 / 101.6,
		'in' => 96.0,
		'pc' => 16.0,
		'pt' => 96.0 / 72.0,
		'deg' => 1.0,
		'rad' => 180.0 / M_PI,
		'grad' => 0.9,
		'turn' => 360.0,
		's' => 1.0,
		'ms' => 0.001,
		'hz' => 1.0,
		'khz' => 1000.0,
		'dppx' => 1.0,
		'x' => 1.0,
		'dpi' => 1.0 / 96.0,
		'dpcm' => 2.54 / 96.0,
		'fr' => 1.0,
	];

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
	 * Returns the px multiplication factor for absolute length units, or null otherwise.
	 */
	public function absoluteToPxFactor(): ?float
	{
		return match ($this) {
			self::LENGTH_px => 1.0,
			self::LENGTH_cm => 96.0 / 2.54,
			self::LENGTH_mm => 96.0 / 25.4,
			self::LENGTH_Q => 96.0 / 101.6,
			self::LENGTH_in => 96.0,
			self::LENGTH_pc => 16.0,
			self::LENGTH_pt => 96.0 / 72.0,
			default => null,
		};
	}

	/**
	 * Returns true for relative (font/viewport/container) length units.
	 */
	public function isRelativeLength(): bool
	{
		return $this->type() === CSSUnitTypeEnum::LENGTH && $this->absoluteToPxFactor() === null;
	}

	/**
	 * Returns the radians multiplication factor for angle units, or null otherwise.
	 */
	public function angleToRadiansFactor(): ?float
	{
		return match ($this) {
			self::ANGLE_rad => 1.0,
			self::ANGLE_grad => M_PI / 200.0,
			self::ANGLE_turn => 2.0 * M_PI,
			self::ANGLE_deg => M_PI / 180.0,
			default => null,
		};
	}

	/**
	 * Returns `[canonicalUnit, factor]` for CSS Values 4 canonicalizable units (px, deg, s, Hz, dppx), or null otherwise.
	 *
	 * @return array{0: self, 1: float}|null
	 */
	public function toCanonicalUnitAndFactor(): ?array
	{
		return match ($this) {
			self::LENGTH_px => [self::LENGTH_px, 1.0],
			self::LENGTH_in => [self::LENGTH_px, 96.0],
			self::LENGTH_cm => [self::LENGTH_px, 96.0 / 2.54],
			self::LENGTH_mm => [self::LENGTH_px, 96.0 / 25.4],
			self::LENGTH_Q => [self::LENGTH_px, 96.0 / 101.6],
			self::LENGTH_pt => [self::LENGTH_px, 96.0 / 72.0],
			self::LENGTH_pc => [self::LENGTH_px, 16.0],
			self::ANGLE_deg => [self::ANGLE_deg, 1.0],
			self::ANGLE_rad => [self::ANGLE_deg, 180.0 / M_PI],
			self::ANGLE_grad => [self::ANGLE_deg, 0.9],
			self::ANGLE_turn => [self::ANGLE_deg, 360.0],
			self::TIME_s => [self::TIME_s, 1.0],
			self::TIME_ms => [self::TIME_s, 0.001],
			self::FREQ_Hz => [self::FREQ_Hz, 1.0],
			self::FREQ_kHz => [self::FREQ_Hz, 1000.0],
			self::RES_dppx, self::RES_x => [self::RES_dppx, 1.0],
			self::RES_dpi => [self::RES_dppx, 1.0 / 96.0],
			self::RES_dpcm => [self::RES_dppx, 2.54 / 96.0],
			default => null,
		};
	}

	/**
	 * Returns the conversion factor matching CSSNumericValue's dpi-based resolution ratios for non-length dimensions.
	 *
	 * @internal
	 */
	public function dimensionFactorDpiBase(): ?float
	{
		return match ($this) {
			self::ANGLE_deg => 1.0,
			self::ANGLE_rad => 180.0 / M_PI,
			self::ANGLE_grad => 0.9,
			self::ANGLE_turn => 360.0,
			self::TIME_s => 1.0,
			self::TIME_ms => 0.001,
			self::FREQ_Hz => 1.0,
			self::FREQ_kHz => 1000.0,
			self::RES_dpi => 1.0,
			self::RES_dpcm => 2.54,
			self::RES_dppx, self::RES_x => 96.0,
			self::FLEX_fr => 1.0,
			default => null,
		};
	}

	/**
	 * Returns the canonical unit enum for a CSS numeric dimension category.
	 */
	public static function canonicalUnitForDimension(string $dimension): ?self
	{
		return match ($dimension) {
			'length' => self::LENGTH_px,
			'angle' => self::ANGLE_deg,
			'time' => self::TIME_s,
			'frequency' => self::FREQ_Hz,
			'resolution' => self::RES_dppx,
			'flex' => self::FLEX_fr,
			'percent' => self::PERCENT,
			default => null,
		};
	}

	/**
	 * Returns the canonical factor (`px`/`deg`/`s`/`Hz`/`dppx`/`fr`) for a lowercase CSS unit symbol (including `'q'`).
	 */
	public static function canonicalFactorBySymbol(string $unitLower): ?float
	{
		return self::CANONICAL_FACTORS_BY_SYMBOL[$unitLower] ?? null;
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
