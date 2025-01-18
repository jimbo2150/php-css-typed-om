<?php
declare(strict_types=1);
namespace Jimbo2150\PhpCssTypedOm\Parser;

use Jimbo2150\PhpCssTypedOm\Css\CSSPropertyID;
use function Jimbo2150\PhpCssTypedOm\Css\Utility\findCSSProperty;
use function Jimbo2150\PhpCssTypedOm\Utility\isASCII;

function isCustomPropertyName(string $property): bool {
	return strlen($property) > 2 && substr($property, 0, 2) == '--';
}

function cssPropertyID(string $characters)
{
	if(!isASCII($characters))
        return CSSPropertyID::CSSPropertyInvalid;
    return findCSSProperty($characters);
}