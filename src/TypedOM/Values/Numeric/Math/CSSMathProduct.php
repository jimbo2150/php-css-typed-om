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
	public function __construct(CSSNumericValue|\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray|array $values)
	{
		parent::__construct($values);
		$this->requireOperandCount(1);
	}

	/** @var string The sign for multiplication */
	public const ?string sign = '*';

	/** @var string The operator name */
	public const ?string operator = '';
}
