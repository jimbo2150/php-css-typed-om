<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

/**
 * A CSSMathMin is a CSSNumericValue that is the result of a min() function.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathMin
 */
class CSSMathMin extends CSSMathValue
{

	public const ?string operator = 'min';
}
