<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Exception;
use InvalidArgumentException;

/**
 * Enum for CSS unit types.
 */
enum CSSUnitTypeEnum: string
{
	case LENGTH = 'length';
	case PERCENT = 'percent';
	case PERCENT_HINT = 'percentHint';
	case ANGLE = 'angle';
	case TIME = 'time';
	case FREQ = 'frequency';
	case RES = 'resolution';
	case FLEX = 'flex';

	public function verifyValue(string|int $value): Exception|string {
		switch(true) {
			case $this === self::PERCENT_HINT:
				if(!is_int($value)) {
					return new InvalidArgumentException("Value for must be an integer.");
				}
				return 'int';
		}
		return 'string';
	}
}