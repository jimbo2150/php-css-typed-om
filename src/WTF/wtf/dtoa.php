<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\wtf\dtoa;

function parseDouble(StringView $string, int &$parsedLength): float
{
	if ($string->is8Bit()) {
		return parseDouble($string->span8(), $parsedLength);
	}

	return parseDouble($string->span16(), $parsedLength);
}
