<?php
declare(strict_types=1);
namespace Jimbo2150\PhpCssTypedOm\Css\Utility;

use Jimbo2150\PhpCssTypedOm\Css\CSSPropertyID;

function findCSSProperty(string $property): CSSPropertyID {
	return CSSPropertyID::from($property);
}