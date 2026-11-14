<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Countable;
use ArrayAccess;
use ArrayIterator;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MultiValueArrayTrait;
use InvalidArgumentException;
use Traversable;

/**
 * Represents a CSS value that can be expressed as a number, or a number and a unit.
 * It is the base class for CSSUnitValue and other future math-related CSS classes.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericValue
 */
/** @implements ArrayAccess<int, CSSNumericValue> @implements IteratorAggregate<int, CSSNumericValue> */
class CSSNumericArray extends CSSNumericValue implements ArrayAccess, Countable, IteratorAggregate
{
	use MultiValueArrayTrait;

	/**
	 * Count the number of values.
	 *
	 * @return int The number of values
	 */
	public function count(): int {
		return $this->length;
	}

	public function item(int $index): ?CSSNumericValue
	{
		return $this->values[$index] ?? null;
	}

	public function offsetExists(mixed $offset): bool
	{
		return is_int($offset) && isset($this->values[$offset]);
	}

	public function offsetGet(mixed $offset): ?CSSNumericValue
	{
		return is_int($offset) ? $this->item($offset) : null;
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (!$value instanceof CSSNumericValue) {
			throw new InvalidArgumentException('CSSNumericArray values must be CSSNumericValue objects.');
		}
		if ($offset === null) {
		$values = $this->values;
		$values[] = $value;
		$this->values = $values;
			return;
		}
		if (!is_int($offset) || !isset($this->values[$offset])) {
			throw new InvalidArgumentException('CSSNumericArray index is out of range.');
		}
		$values = $this->values;
		$values[$offset] = $value;
		$this->values = $values;
	}

	public function offsetUnset(mixed $offset): void
	{
		if (!is_int($offset) || !isset($this->values[$offset])) {
			throw new InvalidArgumentException('CSSNumericArray index is out of range.');
		}
		$values = $this->values;
		array_splice($values, $offset, 1);
		$this->values = $values;
	}

	/** @return Traversable<int, CSSNumericValue> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->values);
	}

	public function clone(): static
	{
		return new static(array_map(static fn (CSSNumericValue $value): CSSNumericValue => $value->clone(), $this->values));
	}
}
