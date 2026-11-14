<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM;

use ArrayIterator;
use Countable;
use Error;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Traversable;

/**
 * Read-only Typed OM view over a CSSStyleDeclaration.
 *
 * @implements IteratorAggregate<string, list<CSSStyleValue>>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/StylePropertyMapReadOnly
 */
class StylePropertyMapReadOnly implements Countable, IteratorAggregate
{
	public function __construct(protected readonly CSSStyleDeclaration $style)
	{
	}

	public function get(string $property): ?CSSStyleValue
	{
		$values = $this->getAll($property);
		return $values[0] ?? null;
	}

	/** @return list<CSSStyleValue> */
	public function getAll(string $property): array
	{
		$value = $this->style->getPropertyValue($property);
		return $value === '' ? [] : CSSStyleValue::parseAll($property, $value);
	}

	public function has(string $property): bool
	{
		return $this->style->getPropertyValue($property) !== '';
	}

	public function count(): int
	{
		return $this->style->getLength();
	}

	public function getSize(): int
	{
		return count($this);
	}

	public function getStyleDeclaration(): CSSStyleDeclaration
	{
		return $this->style;
	}

	/** @return list<string> */
	public function keys(): array
	{
		$keys = [];
		foreach ($this->style as $property => $_declaration) {
			$keys[] = $property;
		}
		return $keys;
	}

	/** @return list<list<CSSStyleValue>> */
	public function values(): array
	{
		$values = [];
		foreach ($this->style as $property => $declaration) {
			$values[] = CSSStyleValue::parseAll($property, $declaration['value']);
		}
		return $values;
	}

	/** @return list<array{string, list<CSSStyleValue>}> */
	public function entries(): array
	{
		$entries = [];
		foreach ($this->style as $property => $declaration) {
			$entries[] = [$property, CSSStyleValue::parseAll($property, $declaration['value'])];
		}
		return $entries;
	}

	/**
	 * Invokes $callback($values, $property, $this) for each entry.
	 */
	public function forEach(callable $callback): void
	{
		foreach ($this->style as $property => $declaration) {
			$callback(CSSStyleValue::parseAll($property, $declaration['value']), $property, $this);
		}
	}

	/** @return Traversable<string, list<CSSStyleValue>> */
	public function getIterator(): Traversable
	{
		$map = [];
		foreach ($this->style as $property => $declaration) {
			$map[$property] = CSSStyleValue::parseAll($property, $declaration['value']);
		}
		return new ArrayIterator($map);
	}

	public function __get(string $name): mixed
	{
		if ($name === 'size') {
			return $this->getSize();
		}
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}
}
