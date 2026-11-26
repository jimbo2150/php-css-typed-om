<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

/**
 * Rounding strategies supported by the CSS Values Level 4 `round()` function.
 *
 * @see https://drafts.csswg.org/css-values-4/#round-func
 */
enum CSSRoundingStrategy: string
{
	case NEAREST = 'nearest';
	case UP = 'up';
	case DOWN = 'down';
	case TO_ZERO = 'to-zero';
}
