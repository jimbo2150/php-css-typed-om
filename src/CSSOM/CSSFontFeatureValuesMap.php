<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Traversable;

/**
 * Map of named font feature values to their integer index lists.
 *
 * @implements IteratorAggregate<string, list<int>>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSFontFeatureValuesMap
 */
final class CSSFontFeatureValuesMap implements Countable, IteratorAggregate
{
	/** @var array<string, list<int>> */
	private array $values = [];

	/** @return list<int>|null */
	public function get(string $name): ?array
	{
		return $this->values[$name] ?? null;
	}

	/**
	 * @param list<int> $values
	 * @throws InvalidArgumentException When the feature name or values are invalid
	 */
	public function set(string $name, array $values): void
	{
		$name = trim($name);
		if ($name === '') {
			throw new InvalidArgumentException('A non-empty font feature name is required.');
		}
		CSSSyntaxScanner::assertValidFragment($name, 'Font feature name', [';', '{', '}', ':']);
		if ($values === []) {
			throw new InvalidArgumentException('A font feature value list requires at least one integer.');
		}
		foreach ($values as $value) {
			if (!is_int($value)) {
				throw new InvalidArgumentException('A feature name and integer value list are required.');
			}
		}

		$this->values[$name] = array_values($values);
	}

	public function has(string $name): bool
	{
		return array_key_exists($name, $this->values);
	}

	public function delete(string $name): void
	{
		unset($this->values[$name]);
	}

	public function clear(): void
	{
		$this->values = [];
	}

	public function count(): int
	{
		return count($this->values);
	}

	public function getSize(): int
	{
		return count($this->values);
	}

	/** @return list<string> */
	public function keys(): array
	{
		return array_keys($this->values);
	}

	/** @return list<list<int>> */
	public function values(): array
	{
		return array_values($this->values);
	}

	/** @return list<array{string, list<int>}> */
	public function entries(): array
	{
		$entries = [];
		foreach ($this->values as $key => $value) {
			$entries[] = [$key, $value];
		}

		return $entries;
	}

	/** @return Traversable<string, list<int>> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->values);
	}

	public function __get(string $name): mixed
	{
		if ($name === 'size') {
			return $this->getSize();
		}
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}
}
