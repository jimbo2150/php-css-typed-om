<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

trait StringType
{
	public function __construct(protected string $value)
	{
	}

	public function __toString(): string
	{
		return $this->value;
	}

	public function toASCIILower(): static
	{
		return new static(strtolower((string) $this));
	}

	public function toASCIIUpper(): static
	{
		return new static(strtoupper((string) $this));
	}
}
