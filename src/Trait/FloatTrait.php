<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Trait;

trait FloatTrait
{
	use NumericTrait;

	protected float $value;

	abstract public function __construct(float $value);

	public function getValue(): float
	{
		return $this->value;
	}
}
