<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

trait UnicodeCharacterType
{
	use CharacterType;
	use UnicodeStringType;

	public function __construct(protected string $value)
	{
		$codepoint = \IntlChar::ord($this->value);
		if (null === $codepoint || $codepoint > \IntlChar::CODEPOINT_MAX) {
			$message = 'Invalid character or more than one character was provided.';
			throw new \Exception($message);
		}
	}
}
