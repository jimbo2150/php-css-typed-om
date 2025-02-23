<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\icu\unicode;

use Jimbo2150\PhpCssTypedOm\Parser\UnicodeCharacterType;

/**
 * Unicode character.
 */
final class UChar
{
	use UnicodeCharacterType;
	public const int UCHAR_MIN_VALUE = \IntlChar::CODEPOINT_MIN;
	public const int UCHAR_MAX_VALUE = \IntlChar::CODEPOINT_MAX;
}
