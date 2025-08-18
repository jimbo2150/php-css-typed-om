<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

trait CSSValuableTrait
{
	/** @var array<CSSNumericValue> */
   	public private(set) array $values {
		set => $value;
	}

	public function __construct(array $values)
	{
		if (count($values) < 1) {
			throw new \InvalidArgumentException('CSSMathOperator requires at least one value.');
		}
		$this->values = $values;
	}	
}