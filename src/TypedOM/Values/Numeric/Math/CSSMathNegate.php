<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * A CSSMathNegate is a CSSNumericValue that is the result of a negation.
 * It is used to represent `calc()` expressions with negation.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathNegate
 */
class CSSMathNegate extends CSSMathValue
{
	public function __construct(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue|\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray|array $values)
	{
		parent::__construct($values);
		$this->requireOperandCount(1, 1);
	}
    public const ?string sign = '-';

    public const ?string operator = '';
}
