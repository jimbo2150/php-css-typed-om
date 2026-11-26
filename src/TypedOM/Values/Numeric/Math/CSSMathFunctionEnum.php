<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

/**
 * CSS Values and Units Level 4 math function identifiers represented by {@see CSSMathFunction}.
 *
 * @see https://drafts.csswg.org/css-values-4/#math
 */
enum CSSMathFunctionEnum: string
{
	// Stepped value functions (§10.5)
	case ROUND = 'round';
	case MOD = 'mod';
	case REM = 'rem';

	// Trigonometric functions (§10.4)
	case SIN = 'sin';
	case COS = 'cos';
	case TAN = 'tan';
	case ASIN = 'asin';
	case ACOS = 'acos';
	case ATAN = 'atan';
	case ATAN2 = 'atan2';

	// Exponential functions (§10.6)
	case POW = 'pow';
	case SQRT = 'sqrt';
	case HYPOT = 'hypot';
	case LOG = 'log';
	case EXP = 'exp';

	// Sign-related functions (§10.7)
	case ABS = 'abs';
	case SIGN = 'sign';

	public function minArgs(): int
	{
		return match ($this) {
			self::MOD, self::REM, self::ATAN2, self::POW => 2,
			default => 1,
		};
	}

	public function maxArgs(): ?int
	{
		return match ($this) {
			self::HYPOT => null,
			self::ROUND, self::MOD, self::REM, self::ATAN2, self::POW, self::LOG => 2,
			default => 1,
		};
	}

	public function isVariadic(): bool
	{
		return $this === self::HYPOT;
	}

	/** @internal */
	public function returnsNumber(): bool
	{
		return match ($this) {
			self::SIN, self::COS, self::TAN, self::POW, self::SQRT, self::LOG, self::EXP, self::SIGN => true,
			default => false,
		};
	}

	/** @internal */
	public function returnsAngle(): bool
	{
		return match ($this) {
			self::ASIN, self::ACOS, self::ATAN, self::ATAN2 => true,
			default => false,
		};
	}
}
