<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;

trait CSSMathOperationTrait
{
	use CSSValuableTrait;

	public function clone(): CSSStyleValue
	{
		return new static($this->values);
	}

	public function toString(): string
	{
		return implode(' ' . $this->operator . ' ', $this->values);
	}
}