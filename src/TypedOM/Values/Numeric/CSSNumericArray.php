<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use ReflectionClass;
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
	/** @var list<CSSNumericValue> */
	public protected(set) array $values = [];

	public int $length {
		get => count($this->values);
	}

	/**
	 * @param CSSNumericValue|self|list<CSSNumericValue> $value
	 */
	public function __construct(CSSNumericValue|self|array $value)
	{
		if ($value instanceof self) {
			$this->values = $value->values;
		} elseif ($value instanceof CSSNumericValue) {
			$this->values = [$value];
		} else {
			if (count($value) > CSSNumericValue::MAX_MATH_NODES) {
				throw new CSSParserLimitException(sprintf('Maximum CSS numeric array node count of %d exceeded.', CSSNumericValue::MAX_MATH_NODES));
			}
			$values = [];
			foreach ($value as $entry) {
				if (!$entry instanceof CSSNumericValue) {
					throw new InvalidArgumentException('All values must be of type ' . CSSNumericValue::class);
				}
				$values[] = $entry;
			}
			$this->values = $values;
		}
	}

	/** @internal */
	public static function empty(): self
	{
		static $empty = null;

		return $empty ??= new self([]);
	}

	/**
	 * @internal Fast path for already-validated list<CSSNumericValue>.
	 * @param list<CSSNumericValue> $values
	 */
	public static function fromTrustedList(array $values): self
	{
		if ($values === []) {
			return self::empty();
		}
		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $arr */
		$arr = $reflection->newInstanceWithoutConstructor();
		$arr->values = $values;

		return $arr;
	}

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

	public function __toString(): string
	{
		if ($this->values === []) {
			return '';
		}

		$parts = [];
		$totalBytes = 0;
		$maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES;
		foreach ($this->values as $i => $value) {
			$part = (string) $value;
			$totalBytes += strlen($part) + ($i > 0 ? 2 : 0);
			if ($totalBytes > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS numeric array serialization exceeds maximum size of %d bytes.', $maxBytes));
			}
			$parts[] = $part;
		}

		return implode(', ', $parts);
	}

	public function clone(): static
	{
		return clone $this;
	}
}
