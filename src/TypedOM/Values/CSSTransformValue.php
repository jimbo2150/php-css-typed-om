<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Traversable;

/**
 * Ordered, mutable list of CSS transform components.
 *
 * @implements ArrayAccess<int, CSSTransformComponent>
 * @implements IteratorAggregate<int, CSSTransformComponent>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSTransformValue
 */
final class CSSTransformValue extends CSSStyleValue implements ArrayAccess, Countable, IteratorAggregate
{
	/** @var list<CSSTransformComponent> */
	private array $components;

	/**
	 * @param list<CSSTransformComponent> $components
	 * @throws InvalidArgumentException When the list is empty or contains non-component items
	 */
	public function __construct(array $components)
	{
		if ($components === []) {
			throw new InvalidArgumentException('A transform list must contain at least one component.');
		}
		$normalized = [];
		foreach ($components as $component) {
			if (!$component instanceof CSSTransformComponent) {
				throw new InvalidArgumentException('Transform values contain transform components.');
			}
			$normalized[] = $component;
		}
		$this->components = $normalized;
	}

	public static function fromCssText(string $cssText): self
	{
		$cssText = trim($cssText);
		if ($cssText === '' || strtolower($cssText) === 'none') {
			throw new InvalidArgumentException('A transform list must contain at least one function.');
		}

		$components = [];
		$offset = 0;
		$length = strlen($cssText);
		while ($offset < $length) {
			while ($offset < $length && ctype_space($cssText[$offset])) {
				$offset++;
			}
			if ($offset >= $length) {
				break;
			}

			if (!preg_match('/\G([a-zA-Z][a-zA-Z0-9-]*)\s*\(/', $cssText, $match, 0, $offset)) {
				throw new InvalidArgumentException('Invalid transform function at offset ' . $offset . '.');
			}
			$name = strtolower($match[1]);
			$open = $offset + strlen($match[0]) - 1;
			$close = CSSSyntaxScanner::findMatchingDelimiter($cssText, $open, $length);
			if ($close === null) {
				throw new InvalidArgumentException('Unclosed transform function: ' . $name);
			}

			$argumentsText = trim(substr($cssText, $open + 1, $close - $open - 1));
			$arguments = self::parseArguments($argumentsText);
			$class = self::componentClass($name);
			$components[] = new $class($name, $arguments);
			$offset = $close + 1;
		}

		if ($components === []) {
			throw new InvalidArgumentException('A transform list must contain at least one function.');
		}

		return new self($components);
	}

	public function count(): int
	{
		return count($this->components);
	}

	public function getLength(): int
	{
		return count($this->components);
	}

	public function offsetExists(mixed $offset): bool
	{
		return is_int($offset) && isset($this->components[$offset]);
	}

	public function offsetGet(mixed $offset): ?CSSTransformComponent
	{
		return is_int($offset) ? ($this->components[$offset] ?? null) : null;
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (!$value instanceof CSSTransformComponent) {
			throw new InvalidArgumentException('Transform components must be CSSTransformComponent objects.');
		}
		if ($offset === null) {
			$this->components[] = $value;
			return;
		}
		if (!is_int($offset) || !isset($this->components[$offset])) {
			throw new InvalidArgumentException('Transform component index is out of range.');
		}
		$this->components[$offset] = $value;
	}

	public function offsetUnset(mixed $offset): void
	{
		if (!is_int($offset) || !isset($this->components[$offset])) {
			throw new InvalidArgumentException('Transform component index is out of range.');
		}
		if (count($this->components) === 1) {
			throw new InvalidArgumentException('A transform list cannot be empty.');
		}
		array_splice($this->components, $offset, 1);
	}

	/** @return Traversable<int, CSSTransformComponent> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->components);
	}

	/** @return list<CSSTransformComponent> */
	public function getComponents(): array
	{
		return $this->components;
	}

	/** @return list<int> */
	public function keys(): array
	{
		return array_keys($this->components);
	}

	/** @return list<CSSTransformComponent> */
	public function values(): array
	{
		return array_values($this->components);
	}

	/** @return list<array{int, CSSTransformComponent}> */
	public function entries(): array
	{
		$entries = [];
		foreach ($this->components as $index => $component) {
			$entries[] = [$index, $component];
		}

		return $entries;
	}

	/**
	 * Invokes $callback($component, $index, $this) for each transform component in order.
	 */
	public function forEach(callable $callback): void
	{
		foreach ($this->components as $index => $component) {
			$callback($component, $index, $this);
		}
	}

	public function is2D(): bool
	{
		foreach ($this->components as $component) {
			if (!$component->is2D()) {
				return false;
			}
		}

		return true;
	}

	public function toMatrix(): DOMMatrix
	{
		$matrix = new DOMMatrix();
		$is2D = true;
		foreach ($this->components as $component) {
			$componentMatrix = $component->toMatrix();
			$matrix->multiplySelf($componentMatrix);
			if (!$component->is2D() || !$componentMatrix->is2D()) {
				$is2D = false;
			}
		}

		return DOMMatrix::fromElements($matrix->toFloat64Array(), $is2D);
	}

	public function clone(): static
	{
		return new static(array_map(static fn (CSSTransformComponent $component): CSSTransformComponent => $component->clone(), $this->components));
	}

	public function __toString(): string
	{
		return implode(' ', array_map(static fn (CSSTransformComponent $component): string => (string) $component, $this->components));
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'length' => $this->getLength(),
			'is2D' => $this->is2D(),
			default => throw new Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	/** @return list<CSSStyleValue|string> */
	private static function parseArguments(string $arguments): array
	{
		if ($arguments === '') {
			return [];
		}

		$parts = CSSSyntaxScanner::splitTopLevel($arguments, ',');
		if (count($parts) === 1) {
			$parts = CSSSyntaxScanner::splitTopLevelWhitespace(trim($arguments));
		}

		return array_map(static function (string $part): CSSStyleValue|string {
			$part = trim($part);
			try {
				return CSSStyleValue::parse($part);
			} catch (InvalidArgumentException) {
				return $part;
			}
		}, $parts);
	}

	/** @return class-string<CSSTransformComponent> */
	private static function componentClass(string $name): string
	{
		$class = match (true) {
			str_starts_with($name, 'matrix') => CSSMatrixComponent::class,
			str_starts_with($name, 'translate') => CSSTranslate::class,
			str_starts_with($name, 'rotate') => CSSRotate::class,
			str_starts_with($name, 'scale') => CSSScale::class,
			$name === 'skew' => CSSSkew::class,
			$name === 'skewx' => CSSSkewX::class,
			$name === 'skewy' => CSSSkewY::class,
			$name === 'perspective' => CSSPerspective::class,
			default => null,
		};
		if ($class === null) {
			throw new InvalidArgumentException('Unsupported transform function: ' . $name);
		}

		return $class;
	}
}
