<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Traversable;

/**
 * Represents a sequence of CSS text fragments and `CSSVariableReferenceValue` objects.
 *
 * @implements ArrayAccess<int, string|CSSVariableReferenceValue>
 * @implements IteratorAggregate<int, string|CSSVariableReferenceValue>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSUnparsedValue
 */
final class CSSUnparsedValue extends CSSStyleValue implements ArrayAccess, Countable, IteratorAggregate
{
	/** @var list<string|CSSVariableReferenceValue> */
	private array $values;

	/**
	 * @param list<string|CSSVariableReferenceValue>|string $value
	 * @throws InvalidArgumentException When an array contains non-string/non-variable fragments
	 */
	public function __construct(array|string $value)
	{
		if (is_string($value)) {
			$this->values = self::parseFragments($value);
			return;
		}

		$normalized = [];
		foreach ($value as $fragment) {
			if (!is_string($fragment) && !$fragment instanceof CSSVariableReferenceValue) {
				throw new InvalidArgumentException('Unparsed values contain only strings and variable references.');
			}
			$normalized[] = $fragment;
		}
		$this->values = $normalized;
	}

	public static function fromCssText(string $cssText): self
	{
		return new self($cssText);
	}

	/** @return list<string|CSSVariableReferenceValue> */
	public function getValues(): array
	{
		return $this->values;
	}

	public function count(): int
	{
		return count($this->values);
	}

	public function offsetExists(mixed $offset): bool
	{
		return is_int($offset) && array_key_exists($offset, $this->values);
	}

	public function offsetGet(mixed $offset): string|CSSVariableReferenceValue|null
	{
		return is_int($offset) ? ($this->values[$offset] ?? null) : null;
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (!is_string($value) && !$value instanceof CSSVariableReferenceValue) {
			throw new InvalidArgumentException('Unparsed values contain only strings and variable references.');
		}
		if ($offset === null) {
			$this->values[] = $value;
			return;
		}
		if (!is_int($offset) || !array_key_exists($offset, $this->values)) {
			throw new InvalidArgumentException('Unparsed value index is out of range.');
		}
		$this->values[$offset] = $value;
	}

	public function offsetUnset(mixed $offset): void
	{
		if (!is_int($offset) || !array_key_exists($offset, $this->values)) {
			throw new InvalidArgumentException('Unparsed value index is out of range.');
		}
		array_splice($this->values, $offset, 1);
	}

	/** @return Traversable<int, string|CSSVariableReferenceValue> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->values);
	}

	public function clone(): static
	{
		return new static(array_map(
			static fn (string|CSSVariableReferenceValue $value): string|CSSVariableReferenceValue =>
				$value instanceof CSSVariableReferenceValue ? $value->clone() : $value,
			$this->values,
		));
	}

	public function __toString(): string
	{
		return implode('', array_map(static fn (string|CSSVariableReferenceValue $value): string => (string) $value, $this->values));
	}

	public function __get(string $name): mixed
	{
		if ($name === 'length') {
			return count($this->values);
		}
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	/** @return list<string|CSSVariableReferenceValue> */
	private static function parseFragments(string $source): array
	{
		$values = [];
		$fragmentStart = 0;
		$quote = null;
		$length = strlen($source);

		for ($offset = 0; $offset < $length; $offset++) {
			$character = $source[$offset];
			if ($quote !== null) {
				if ($character === '\\') {
					$offset++;
				} elseif ($character === $quote) {
					$quote = null;
				}
				continue;
			}
			if ($character === '/' && ($offset + 1 < $length) && $source[$offset + 1] === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false) {
					break;
				}
				$offset = $commentEnd + 1;
				continue;
			}
			if ($character === '\\') {
				$offset++;
				continue;
			}
			if ($character === '"' || $character === "'") {
				$quote = $character;
				continue;
			}
			if (($character !== 'v' && $character !== 'V')
				|| $offset + 4 > $length
				|| substr_compare($source, 'var(', $offset, 4, true) !== 0
			) {
				continue;
			}
			if ($offset > 0 && preg_match('/[a-zA-Z0-9_-]/', $source[$offset - 1]) === 1) {
				continue;
			}

			$open = $offset + 3;
			$close = CSSSyntaxScanner::findMatchingDelimiter($source, $open, $length);
			if ($close === null) {
				continue;
			}
			$contents = substr($source, $open + 1, $close - $open - 1);
			$parts = CSSSyntaxScanner::splitTopLevel($contents, ',');
			$variable = trim(array_shift($parts) ?? '');
			try {
				$normalizedVariable = CSSStyleDeclaration::normalizePropertyName($variable);
			} catch (InvalidArgumentException) {
				continue;
			}
			if (!str_starts_with($normalizedVariable, '--')) {
				continue;
			}

			$fallback = $parts === [] ? null : trim(implode(',', $parts));
			try {
				$varRef = new CSSVariableReferenceValue($normalizedVariable, $fallback);
			} catch (InvalidArgumentException) {
				continue;
			}

			if ($offset > $fragmentStart) {
				$values[] = substr($source, $fragmentStart, $offset - $fragmentStart);
			}
			$values[] = $varRef;
			$offset = $close;
			$fragmentStart = $close + 1;
		}

		if ($fragmentStart < $length || $values === []) {
			$values[] = substr($source, $fragmentStart);
		}

		return $values;
	}
}
