<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Trait for numeric Typed OM value properties with finite-value validation.
 */
trait SimpleValueTrait
{
	/** @var mixed The value */
	public protected(set) mixed $value = null;

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
		if (is_string($value) && CSSSyntaxScanner::hasControlCharacters($value)) {
			return false;
		}

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
