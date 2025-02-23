<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Trait;

trait IntTrait
{
	use NumericTrait;

	protected int $value;

	abstract public function __construct(int $value);

	public function getValue(): int
	{
		return $this->value;
	}
}
