<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\wtf\text;

use Jimbo2150\PhpCssTypedOm\Parser\CharacterType;

class CharacterParsingBuffer extends StringParsingBuffer
{
	use CharacterType;

	public function __construct(resource|string $characters)
	{
		parent::__construct($characters);
		if ($this->size() < 1 || $this->size() > 1) {
			throw new \Exception('String must contain only a single character.');
		}
	}
}
