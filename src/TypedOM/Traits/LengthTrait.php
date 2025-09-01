<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

/**
 * Trait for simple value classes (color, keyword, image).
 * 
 * Provides common functionality for simple CSS values that don't need complex operations.
 */
trait LengthTrait
{

	/** @var ?int The length of the values array, or null if not set */
	public ?int $length {
		get => isset($this->values) ? count($this->values) : null;
	}

}