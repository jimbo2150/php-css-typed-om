<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

/**
 * A CSSMathSum is a CSSNumericValue that is the result of a summation.
 * It is used to represent `calc()` expressions with addition.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathSum
 */
class CSSMathSum extends CSSMathValue
{

	/** @var string The sign for addition */
	public const ?string sign = '+';

	/** @var string The operator name */
	public const ?string operator = 'sum';
}
