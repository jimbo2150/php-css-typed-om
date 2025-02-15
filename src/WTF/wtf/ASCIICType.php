<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\wtf\ASCIICType;

function isASCII(string $character): bool
{
	return false !== mb_detect_encoding($character, 'ASCII', true);
}
