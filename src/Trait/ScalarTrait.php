<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Trait;

trait ScalarTrait
{
	protected string|int|float|bool|null $value;

	abstract public function __construct(string|int|float|bool|null $value);

	public function __toString(): string
	{
		return (string) $this->value;
	}

	public function getValue(): string|int|float|bool|null
	{
		return $this->value;
	}
}
