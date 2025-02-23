<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Trait;

trait NullableStringTrait
{
	use StringTrait;

	protected ?string $value;

	abstract public function __construct(?string $value);

	public function getValue(): ?string
	{
		return $this->value;
	}
}
