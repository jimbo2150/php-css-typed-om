<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

trait CharacterType
{
	use StringType;

	public function __construct(protected string $value)
	{
		self::validateASCIICharacter($this->value);
	}

	final public static function validateASCIICharacter(string $character): void
	{
		switch (true) {
			case strlen($character) < 1: // Empty string
				throw new \InvalidArgumentException('Must provide a single character.');
			case strlen($character) > 1: // More than 1 character
				$message = 'Cannot contain more than 1 character.';
				throw new \InvalidArgumentException($message);
		}
	}
}
