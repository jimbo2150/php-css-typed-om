<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSOpaqueRule extends CSSRule
{
	public function __construct(private readonly string $source)
	{
	}

	public function getType(): int
	{
		return self::UNKNOWN_RULE;
	}

	public function getCssText(): string
	{
		return $this->source;
	}
}
