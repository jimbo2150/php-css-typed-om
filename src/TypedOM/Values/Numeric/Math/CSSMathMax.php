<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

/**
 * A CSSMathMax is a CSSNumericValue that is the result of a max() function.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathMax
 */
class CSSMathMax extends CSSMathValue
{

	public const ?string operator = 'max';
}
