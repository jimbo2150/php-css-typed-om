<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use ArrayIterator;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Traversable;

/** Read-only ordered collection of standalone stylesheets. */
final class StyleSheetList implements Countable, IteratorAggregate
{
	/** @param list<StyleSheet> $sheets */
	public function __construct(private readonly array $sheets = [])
	{
		foreach ($sheets as $sheet) {
			if (!$sheet instanceof StyleSheet) {
				throw new InvalidArgumentException('StyleSheetList entries must be StyleSheet objects.');
			}
		}
	}

	public function item(int $index): ?StyleSheet
	{
		return $this->sheets[$index] ?? null;
	}

	public function count(): int
	{
		return count($this->sheets);
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
		throw new \Error('Undefined property: ' . static::class . '::$' . $name);
	}
}
