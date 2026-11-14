<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use InvalidArgumentException;

/**
 * Trait for numeric/simple Typed OM value properties with finite-value validation.
 */
trait SimpleValueTrait
{
	/** @var mixed The value */
	public protected(set) mixed $value = null {
		get {
			return $this->value;
		}
	}

	/**
	 * Set the value after validation.
	 *
	 * @throws InvalidArgumentException When value type or finiteness is invalid
	 */
	protected function setValue(string|float $value): void
	{
		$this->validateValue($value);
		$this->value = $value;
	}

	protected static function isValueValid(mixed $value): bool
	{
		return is_numeric($value) && is_finite((float) $value);
	}

	/**
	 * @throws InvalidArgumentException When the value is invalid
	 */
	protected static function validateValue(mixed $value): bool
	{
		if (!static::isValueValid($value)) {
			throw new InvalidArgumentException(sprintf('Invalid value for %s.', static::class));
		}

		return true;
	}

	public function __toString(): string
	{
		return (string) $this->value;
	}

	public function clone(): static
	{
		return clone $this;
	}
}
