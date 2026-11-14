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
	public function __construct(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue|\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray|array $values)
	{
		parent::__construct($values);
		$this->requireOperandCount(1);
	}

	/** @var string The operator name */
	public const ?string operator = 'min';
}
