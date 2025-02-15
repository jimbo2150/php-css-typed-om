<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\icu\unicode;

final class UChar
{
	protected string $character;

	public function __construct(string $character)
	{
		$codepoint = \IntlChar::ord($character);
		if (null === $codepoint || $codepoint > \IntlChar::CODEPOINT_MAX) {
			$message = 'Invalid character or more than one character was provided.';
			throw new \Exception($message);
		}
		$this->character = $character;
	}

	public function __toString(): string
	{
		return $this->character;
	}
}
