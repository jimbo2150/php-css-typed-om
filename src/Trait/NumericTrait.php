<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Trait;

trait NumericTrait
{
	use ScalarTrait;

	protected int|float $value;

	abstract public function __construct(int|float $value);

	public function getValue(): int|float
	{
		return $this->value;
	}
}
