<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM;

use Countable;
use Error;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
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

	protected function resolveSheetParserOptions(): CSSParserOptions
	{
		$sheet = $this->style->getParentRule()?->getParentStyleSheet();

		return $sheet !== null ? $sheet->resolveParserOptionsInternal(null) : CSSParserOptions::default();
	}

	protected function normalizeBoundedPropertyName(string $property, CSSParserOptions $options): string
	{
		if ($options->maxBytes !== null && strlen($property) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property name exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		return CSSStyleDeclaration::normalizePropertyName($property);
	}

	public function get(string $property): ?CSSStyleValue
	{
		$options = $this->resolveSheetParserOptions();
		$normalized = $this->normalizeBoundedPropertyName($property, $options);
		$declaration = $this->style->getDeclarationsRaw()[$normalized] ?? null;
		if ($declaration === null) {
			return null;
		}
		$value = $declaration['value'];
		if ($value === '') {
			return new CSSUnparsedValue([], $options);
		}

		if (str_starts_with($normalized, '--')) {
			return CSSStyleValue::parseValidated($normalized, $value, $options);
		}
		$length = strlen($value);
		$offset = 0;
		while ($offset < $length) {
			$comma = CSSSyntaxScanner::findTopLevel($value, ',', $offset, $length);
			$segmentEnd = $comma ?? $length;
			$first = CSSSyntaxScanner::trimCssWhitespace(substr($value, $offset, $segmentEnd - $offset));
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
		$options = $this->resolveSheetParserOptions();
		$normalized = $this->normalizeBoundedPropertyName($property, $options);
		$declaration = $this->style->getDeclarationsRaw()[$normalized] ?? null;
		if ($declaration === null) {
			return [];
		}

		return $this->parseAllDeclarationValues($normalized, $declaration['value'], $options);
	}

	public function has(string $property): bool
	{
		$options = $this->resolveSheetParserOptions();
		$normalized = $this->normalizeBoundedPropertyName($property, $options);

		return isset($this->style->getDeclarationsRaw()[$normalized]);
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
		$options = $this->resolveSheetParserOptions();
		$values = [];
		foreach ($this->style->getDeclarationsRaw() as $property => $declaration) {
			$values[] = $this->parseAllDeclarationValues($property, $declaration['value'], $options);
		}
		return $values;
	}

	/** @return list<array{string, list<CSSStyleValue>}> */
	public function entries(): array
	{
		$options = $this->resolveSheetParserOptions();
		$entries = [];
		foreach ($this->style->getDeclarationsRaw() as $property => $declaration) {
			$entries[] = [$property, $this->parseAllDeclarationValues($property, $declaration['value'], $options)];
		}
		return $entries;
	}

	/**
	 * Invokes $callback($values, $property, $this) for each entry.
	 */
	public function forEach(callable $callback): void
	{
		$options = $this->resolveSheetParserOptions();
		foreach ($this->style->getDeclarationsRaw() as $property => $declaration) {
			$callback($this->parseAllDeclarationValues($property, $declaration['value'], $options), $property, $this);
		}
	}

	/** @return Traversable<string, list<CSSStyleValue>> */
	public function getIterator(): Traversable
	{
		return (function (): Generator {
			$options = $this->resolveSheetParserOptions();
			foreach ($this->style->getDeclarationsRaw() as $property => $declaration) {
				yield $property => $this->parseAllDeclarationValues($property, $declaration['value'], $options);
			}
		})();
	}

	/** @return list<CSSStyleValue> */
	private function parseAllDeclarationValues(string $property, string $value, CSSParserOptions $options): array
	{
		if ($value === '') {
			return [new CSSUnparsedValue([], $options)];
		}
		if (!str_starts_with($property, '--') && preg_match('/^[,\s\t\n\r\f]+$/D', $value) === 1) {
			return [];
		}

		try {
			return CSSStyleValue::parseAllValidated($property, $value, $options);
		} catch (CSSParserLimitException $e) {
			throw $e;
		} catch (InvalidArgumentException $e) {
			if (!str_starts_with($property, '--') && $e->getMessage() === 'CSS value list cannot be empty.') {
				return [];
			}
			throw $e;
		}
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
