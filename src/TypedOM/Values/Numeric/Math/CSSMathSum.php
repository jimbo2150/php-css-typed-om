<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * A CSSMathSum is a CSSNumericValue that is the result of a summation.
 * It is used to represent `calc()` expressions with addition.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathSum
 */
class CSSMathSum extends CSSMathValue
{

	public const ?string type = 'sum';

	public const ?string operator = '+';
}
