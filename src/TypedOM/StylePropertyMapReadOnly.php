<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM;

use Countable;
use Error;
use Generator;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
use Traversable;

/**
 * Read-only Typed OM view over a CSSStyleDeclaration.
 *
 * @implements IteratorAggregate<string, list<CSSStyleValue>>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/StylePropertyMapReadOnly
 */
class StylePropertyMapReadOnly implements Countable, IteratorAggregate
{
	public int $size {
		get => $this->style->getLength();
	}

	public function __construct(protected readonly CSSStyleDeclaration $style)
	{
	}

	public function get(string $property): ?CSSStyleValue
	{
		$normalized = CSSStyleDeclaration::normalizePropertyName($property);
		$declaration = $this->style->getDeclarationsRaw()[$normalized] ?? null;
		if ($declaration === null) {
			return null;
		}
		$value = $declaration['value'];
		if ($value === '') {
			return new CSSUnparsedValue([]);
		}

		$options = CSSParserOptions::default();
		if (str_starts_with($normalized, '--')) {
			return CSSStyleValue::parseValidated($normalized, $value, $options);
		}
		$length = strlen($value);
		$offset = 0;
		while ($offset < $length) {
			$comma = CSSSyntaxScanner::findTopLevel($value, ',', $offset, $length);
			$segmentEnd = $comma ?? $length;
			$first = trim(substr($value, $offset, $segmentEnd - $offset));
			if ($first !== '') {
				return CSSStyleValue::parseValidated($normalized, $first, $options);
			}
			$offset = $segmentEnd + 1;
		}

		return null;
	}

	/** @return list<CSSStyleValue> */
	public function getAll(string $property): array
	{
		$normalized = CSSStyleDeclaration::normalizePropertyName($property);
		$declaration = $this->style->getDeclarationsRaw()[$normalized] ?? null;
		if ($declaration === null) {
			return [];
		}

		return $declaration['value'] === ''
			? [new CSSUnparsedValue([])]
			: CSSStyleValue::parseAllValidated($normalized, $declaration['value'], CSSParserOptions::default());
	}

	public function has(string $property): bool
	{
		return $this->style->offsetExists($property);
	}

	public function count(): int
	{
		return $this->style->getLength();
	}

	public function getSize(): int
	{
		return $this->style->getLength();
	}

	public function getStyleDeclaration(): CSSStyleDeclaration
	{
		return $this->style;
	}

	/** @return list<string> */
	public function keys(): array
	{
		return array_keys($this->style->getDeclarationsRaw());
	}

	/** @return list<list<CSSStyleValue>> */
	public function values(): array
	{
		$options = CSSParserOptions::default();
		$values = [];
		foreach ($this->style->getDeclarationsRaw() as $property => $declaration) {
			$values[] = $declaration['value'] === ''
				? [new CSSUnparsedValue([])]
				: CSSStyleValue::parseAllValidated($property, $declaration['value'], $options);
		}
		return $values;
	}

	/** @return list<array{string, list<CSSStyleValue>}> */
	public function entries(): array
	{
		$options = CSSParserOptions::default();
		$entries = [];
		foreach ($this->style->getDeclarationsRaw() as $property => $declaration) {
			$parsed = $declaration['value'] === ''
				? [new CSSUnparsedValue([])]
				: CSSStyleValue::parseAllValidated($property, $declaration['value'], $options);
			$entries[] = [$property, $parsed];
		}
		return $entries;
	}

	/**
	 * Invokes $callback($values, $property, $this) for each entry.
	 */
	public function forEach(callable $callback): void
	{
		$options = CSSParserOptions::default();
		foreach ($this->style->getDeclarationsRaw() as $property => $declaration) {
			$parsed = $declaration['value'] === ''
				? [new CSSUnparsedValue([])]
				: CSSStyleValue::parseAllValidated($property, $declaration['value'], $options);
			$callback($parsed, $property, $this);
		}
	}

	/** @return Traversable<string, list<CSSStyleValue>> */
	public function getIterator(): Traversable
	{
		return (function (): Generator {
			$options = CSSParserOptions::default();
			foreach ($this->style->getDeclarationsRaw() as $property => $declaration) {
				yield $property => $declaration['value'] === ''
					? [new CSSUnparsedValue([])]
					: CSSStyleValue::parseAllValidated($property, $declaration['value'], $options);
			}
		})();
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
}
