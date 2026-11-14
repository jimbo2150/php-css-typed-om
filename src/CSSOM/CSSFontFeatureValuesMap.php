<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Countable;
use InvalidArgumentException;

/** Map of named font feature values to their integer arguments. */
final class CSSFontFeatureValuesMap implements Countable
{
	/** @var array<string, list<int>> */
	private array $values = [];

	/** @return list<int>|null */
	public function get(string $name): ?array
	{
		return $this->values[$name] ?? null;
	}

	/** @param list<int> $values */
	public function set(string $name, array $values): void
	{
		if (trim($name) === '' || array_filter($values, static fn (mixed $value): bool => !is_int($value)) !== []) {
			throw new InvalidArgumentException('A feature name and integer value list are required.');
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
}
