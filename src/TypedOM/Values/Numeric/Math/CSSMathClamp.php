<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

/** Represents a CSS clamp() calculation. */
final class CSSMathClamp extends CSSMathValue
{
	public const ?string operator = 'clamp';

	public function __construct(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue|\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray|array $values)
	{
		parent::__construct($values);
		$this->requireOperandCount(3, 3);
	}
}
