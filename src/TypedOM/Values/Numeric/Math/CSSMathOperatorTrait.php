<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

trait CSSMathOperatorTrait
{
	use CSSMathOperationTrait;

	public const ?string operator = null;

	public function toString(): string
	{
		return implode(' ' . $this->operator . ' ', $this->values);
	}	
}