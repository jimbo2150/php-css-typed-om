<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Trait;

trait NullableBoolTrait
{
	use BoolTrait;

	protected ?bool $value;

	abstract public function __construct(?bool $value);

	public function getValue(): ?bool
	{
		return $this->value;
	}
}
