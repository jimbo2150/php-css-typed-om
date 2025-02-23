<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\wtf\text;

function equalLettersIgnoringASCIICase(string $characters, string $lowercaseLetters): bool
{
	return $characters == $lowercaseLetters;
}
