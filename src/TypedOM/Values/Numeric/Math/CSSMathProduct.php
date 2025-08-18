<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * A CSSMathProduct is a CSSNumericValue that is the result of a multiplication.
 * It is used to represent `calc()` expressions with multiplication.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathProduct
 */
class CSSMathProduct extends CSSMathValue
{

	public const ?string type = 'product';

	public const ?string operator = '*';
}
