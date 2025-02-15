<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Css;

use function Jimbo2150\PhpCssTypedOm\Parser\isCustomPropertyName;
use function Jimbo2150\PhpCssTypedOm\WTF\wtf\ASCIICType\isASCII;

enum CSSPropertyID
{
	case CSSPropertyInvalid;
	case CSSPropertyCustom;
	case CSSPropertyBuiltIn;

	public static function from(int|string $value): static
	{
		if (empty($value) || false == isASCII($value)) {
			return self::CSSPropertyInvalid;
		}
		if (isCustomPropertyName($value)) {
			return self::CSSPropertyCustom;
		}

		return self::CSSPropertyBuiltIn;
	}

	public static function tryFrom(int|string $value): ?static
	{
		return self::from($value);
	}
}
