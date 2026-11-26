<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

/**
 * Mathematical constants (`<calc-keyword>`) defined by CSS Values and Units Level 4.
 *
 * @see https://drafts.csswg.org/css-values-4/#calc-constants
 */
enum CSSMathConstantEnum: string
{
	case E = 'e';
	case PI = 'pi';
	case INFINITY = 'infinity';
	case NEG_INFINITY = '-infinity';
	case NAN = 'NaN';

	/**
	 * Resolves a CSS `<calc-keyword>` identifier ASCII case-insensitively.
	 */
	public static function tryFromKeyword(string $keyword): ?self
	{
		return match (strtolower($keyword)) {
			'e' => self::E,
			'pi' => self::PI,
			'infinity' => self::INFINITY,
			'-infinity' => self::NEG_INFINITY,
			'nan' => self::NAN,
			default => null,
		};
	}

	/**
	 * Returns the IEEE-754 floating-point value for this constant.
	 */
	public function toFloat(): float
	{
		return match ($this) {
			self::E => M_E,
			self::PI => M_PI,
			self::INFINITY => INF,
			self::NEG_INFINITY => -INF,
			self::NAN => NAN,
		};
	}
}
