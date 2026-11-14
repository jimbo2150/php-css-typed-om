<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use ArrayAccess;
use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MultiValueArrayTrait;
use Traversable;

/**
 * Read-only ordered collection of CSSNumericValue items.
 *
 * @implements ArrayAccess<int, CSSNumericValue>
 * @implements IteratorAggregate<int, CSSNumericValue>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericArray
 */
class CSSNumericArray extends CSSNumericValue implements ArrayAccess, Countable, IteratorAggregate
{
	use MultiValueArrayTrait;

	public function count(): int
	{
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
		throw new InvalidArgumentException('CSSNumericArray is read-only.');
	}

	public function offsetUnset(mixed $offset): void
	{
		throw new InvalidArgumentException('CSSNumericArray is read-only.');
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
