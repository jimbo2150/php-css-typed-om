<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/**
 * Read-only ordered collection of standalone stylesheets.
 *
 * @implements ArrayAccess<int, StyleSheet>
 * @implements IteratorAggregate<int, StyleSheet>
 */
final class StyleSheetList implements ArrayAccess, Countable, IteratorAggregate
{
	/** @var list<StyleSheet> */
	private array $sheets;

	/** @param list<StyleSheet> $sheets */
	public function __construct(array $sheets = [])
	{
		$normalized = [];
		foreach ($sheets as $sheet) {
			if (!$sheet instanceof StyleSheet) {
				throw new InvalidArgumentException('StyleSheetList entries must be StyleSheet objects.');
			}
			$normalized[] = $sheet;
		}
		$this->sheets = $normalized;
	}

	public function item(int $index): ?StyleSheet
	{
		return $this->sheets[$index] ?? null;
	}

	public function count(): int
	{
		return count($this->sheets);
	}

	public function offsetExists(mixed $offset): bool
	{
		return is_int($offset) && isset($this->sheets[$offset]);
	}

	public function offsetGet(mixed $offset): ?StyleSheet
	{
		return is_int($offset) ? $this->item($offset) : null;
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new InvalidArgumentException('StyleSheetList is read-only.');
	}

	public function offsetUnset(mixed $offset): void
	{
		throw new InvalidArgumentException('StyleSheetList is read-only.');
	}

	/** @return Traversable<int, StyleSheet> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->sheets);
	}

	public function __get(string $name): mixed
	{
		if ($name === 'length') {
			return $this->count();
		}
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'length';
	}
}
