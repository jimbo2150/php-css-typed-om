<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * A CSSMathInvert is a CSSNumericValue that is the result of an inversion.
 * It is used to represent `calc()` expressions with inversion.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathInvert
 */
class CSSMathInvert extends CSSMathValue
{
	public const ?string type = 'invert';

	public const ?string operator = '-';
}
