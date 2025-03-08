<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WebCore\css;

use Jimbo2150\PhpCssTypedOm\Css\CSSPropertyID;
use Jimbo2150\PhpCssTypedOm\CSSValueID;

class CSSPrimitiveValueValue
{
	public CSSPropertyID $propertyID;
	public CSSValueID $valueID;
	public float $number;
	public string $string;
	public CSSCalcValue $calc;
	public CSSAttrValue $attr;
}
