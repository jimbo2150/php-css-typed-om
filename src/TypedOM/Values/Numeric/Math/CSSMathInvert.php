<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

/**
 * A CSSMathInvert is a CSSNumericValue that is the result of an inversion.
 * It is used to represent `calc()` expressions with inversion.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathInvert
 */
class CSSMathInvert extends CSSMathValue
{
	/** @var string The sign representation */
	public const ?string sign = '1 / {value}';

	/** @var string The operator name */
	public const ?string operator = '';
}
