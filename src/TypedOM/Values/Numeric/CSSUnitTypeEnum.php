<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Exception;
use InvalidArgumentException;

/**
 * Enumerates CSS Typed OM numeric dimension names and percentHint.
 */
enum CSSUnitTypeEnum: string
{
	case LENGTH = 'length';
	case ANGLE = 'angle';
	case TIME = 'time';
	case FREQ = 'frequency';
	case RES = 'resolution';
	case FLEX = 'flex';
	case PERCENT = 'percent';
	case PERCENT_HINT = 'percentHint';

	/**
	 * Validates a value assigned to a CSSNumericType property and returns its coerced PHP type.
	 */
	public function verifyValue(string|int $value): Exception|string
	{
		if ($this === self::PERCENT_HINT) {
			if (is_int($value)) {
				return 'int';
			}
			$resolved = self::tryFrom($value);
			if ($resolved !== null && $resolved !== self::PERCENT_HINT) {
				return 'string';
			}
			return new InvalidArgumentException('Invalid percentHint value: ' . $value);
		}

		if (is_int($value) || preg_match('/^-?\d+$/D', $value)) {
			return 'int';
		}

		return 'string';
	}
}
