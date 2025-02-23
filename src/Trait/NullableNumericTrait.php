<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Trait;

trait NullableNumericTrait
{
	use NumericTrait;

	protected int|float|null $value;

	abstract public function __construct(int|float|null $value);

	public function getValue(): int|float|null
	{
		return $this->value;
	}
}
