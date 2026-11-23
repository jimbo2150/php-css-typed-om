<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\LengthTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MultiValueArrayTrait;
use Stringable;
use Traversable;

/**
 * Read-only ordered collection of CSSNumericValue items.
 *
 * @implements ArrayAccess<int, CSSNumericValue>
 * @implements IteratorAggregate<int, CSSNumericValue>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericArray
 */
class CSSNumericArray implements ArrayAccess, Countable, IteratorAggregate, Stringable
{
	use MultiValueArrayTrait, LengthTrait;

	public function count(): int
	{
		return count($this->values);
	}

	public function item(int $index): ?CSSNumericValue
	{
		return $this->values[$index] ?? null;
	}

	/** @return list<int> */
	public function keys(): array
	{
		return array_keys($this->values);
	}

	/** @return list<CSSNumericValue> */
	public function values(): array
	{
		return array_values($this->values);
	}

	/** @return list<array{int, CSSNumericValue}> */
	public function entries(): array
	{
		$entries = [];
		foreach ($this->values as $index => $value) {
			$entries[] = [$index, $value];
		}

		return $entries;
	}

	/**
	 * Invokes $callback($value, $index, $this) for each numeric item in order.
	 */
	public function forEach(callable $callback): void
	{
		foreach ($this->values as $index => $value) {
			$callback($value, $index, $this);
		}
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

	public function __get(string $name): mixed
	{
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	public function __isset(string $name): bool
	{
		return false;
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	public function __clone(): void
	{
		$cloned = [];
		$visits = 0;
		foreach ($this->values as $value) {
			$cloned[] = CSSNumericValue::cloneTreeIteratively($value, $visits);
		}
		$this->values = $cloned;
	}

	public function clone(): static
	{
		return clone $this;
	}
}
