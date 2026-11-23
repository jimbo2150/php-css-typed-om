<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

/**
 * Trait exposing a `$length` property hook over a `$values` sequence.
 */
trait LengthTrait
{

	/** @var ?int The length of the values array, or null if not set */
	public ?int $length {
		get => isset($this->values) ? count($this->values) : null;
	}

}
