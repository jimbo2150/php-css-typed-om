<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WebCore\css\parser;

final class CommentPosition
{
	private int $startOffset;
	private int $endOffset;
	private int $tokensBefore;

	public function __get(string $property): int
	{
		if (isset($this->$property)) {
			return $this->$property;
		} else {
			throw new \InvalidArgumentException('Property '.$property.' not defined.');
		}
	}
}
