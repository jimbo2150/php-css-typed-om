<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

/**
 * Enum for CSS unit types.
 */
enum CSSUnitTypeEnum: string
{
	case LENGTH = 'length';
	case PERCENT = 'percent';
	case ANGLE = 'angle';
	case TIME = 'time';
	case FREQ = 'frequency';
	case RES = 'resolution';
	case FLEX = 'flex';
}