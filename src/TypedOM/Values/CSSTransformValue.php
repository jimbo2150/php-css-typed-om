<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
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
	public const int MAX_COMPONENTS = 1_024;

	/** @var list<CSSTransformComponent> */
	private array $components;

	public int $length {
		get => count($this->components);
	}

	public bool $is2D {
		get => $this->is2D();
	}

	/**
	 * @param list<CSSTransformComponent> $components
	 * @throws InvalidArgumentException When the list is empty or contains non-component items
	 */
	public function __construct(array $components)
	{
		if ($components === []) {
			throw new InvalidArgumentException('A transform list must contain at least one component.');
		}
		if (count($components) > self::MAX_COMPONENTS) {
			throw new CSSParserLimitException(sprintf('Transform list exceeds maximum limit of %d components.', self::MAX_COMPONENTS));
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

	public static function fromCssText(string $cssText, ?CSSParserOptions $options = null): self
	{
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('Transform list exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if ($cssText !== '') {
			CSSSyntaxScanner::assertValidFragment($cssText, 'CSS transform', [';', '{', '}']);
		}
		$cssText = CSSSyntaxScanner::trimCssWhitespace(str_contains($cssText, '/*') ? CSSSyntaxScanner::removeComments($cssText) : $cssText);
		if ($cssText === '' || strtolower($cssText) === 'none') {
			throw new InvalidArgumentException('A transform list must contain at least one function.');
		}

		$components = [];
		$offset = 0;
		$length = strlen($cssText);
		while ($offset < $length) {
			$offset += strspn($cssText, " \t\n\r\f", $offset);
			if ($offset >= $length) {
				break;
			}

			if (!preg_match('/\G([a-zA-Z][a-zA-Z0-9-]*)\(/', $cssText, $match, 0, $offset)) {
				throw new InvalidArgumentException('Invalid transform function at offset ' . $offset . '.');
			}
			$name = strtolower($match[1]);
			if (count($components) >= self::MAX_COMPONENTS) {
				throw new CSSParserLimitException(sprintf('Transform list exceeds maximum limit of %d components.', self::MAX_COMPONENTS));
			}
			$open = $offset + strlen($match[0]) - 1;
			$close = CSSSyntaxScanner::findMatchingDelimiter($cssText, $open, $length);
			if ($close === null) {
				throw new InvalidArgumentException('Unclosed transform function: ' . $name);
			}

			$argumentsText = CSSSyntaxScanner::trimCssWhitespace(substr($cssText, $open + 1, $close - $open - 1));
			$arguments = self::parseArguments($argumentsText, $options);
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
			if (count($this->components) >= self::MAX_COMPONENTS) {
				throw new CSSParserLimitException(sprintf('Transform list exceeds maximum limit of %d components.', self::MAX_COMPONENTS));
			}
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

		return DOMMatrix::fromTrustedElements($matrix->toFloat64Array(), $is2D);
	}

	public function __clone(): void
	{
		foreach ($this->components as $i => $component) {
			$this->components[$i] = $component->clone();
		}
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		$parts = [];
		$totalBytes = 0;
		$maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES;
		foreach ($this->components as $i => $component) {
			$part = (string) $component;
			$totalBytes += strlen($part) + ($i > 0 ? 1 : 0);
			if ($totalBytes > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS transform list exceeds maximum size of %d bytes.', $maxBytes));
			}
			$parts[] = $part;
		}

		return implode(' ', $parts);
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

	/** @return list<CSSStyleValue|string> */
	private static function parseArguments(string $arguments, CSSParserOptions $options): array
	{
		if ($arguments === '') {
			return [];
		}

		$parts = str_contains($arguments, ',')
			? CSSSyntaxScanner::splitTopLevel($arguments, ',')
			: [$arguments];
		if (count($parts) === 1) {
			$parts = CSSSyntaxScanner::splitTopLevelWhitespace(CSSSyntaxScanner::trimCssWhitespace($arguments));
		}

		$parsed = [];
		foreach ($parts as $part) {
			$part = CSSSyntaxScanner::trimCssWhitespace($part);
			if (strcasecmp($part, 'none') === 0) {
				$parsed[] = new CSSKeywordValue('none');
				continue;
			}
			try {
				$parsed[] = CSSStyleValue::parse($part, null, $options);
			} catch (CSSParserLimitException $e) {
				throw $e;
			} catch (InvalidArgumentException) {
				$parsed[] = $part;
			}
		}

		return $parsed;
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
