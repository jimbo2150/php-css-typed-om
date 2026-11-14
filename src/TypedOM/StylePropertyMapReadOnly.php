<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM;

use Countable;
use ArrayIterator;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Traversable;

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

	/** @return Traversable<string, CSSStyleValue> */
	public function getIterator(): Traversable
	{
		$values = [];
		foreach ($this->style as $property => $_declaration) {
			$value = $this->get($property);
			if ($value !== null) {
				$values[$property] = $value;
			}
		}
		return new ArrayIterator($values);
	}

	public function __get(string $name): mixed
	{
		if ($name === 'size') {
			return $this->getSize();
		}
		throw new \Error('Undefined property: ' . static::class . '::$' . $name);
	}
}
