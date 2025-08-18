<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

/**
 * Represents the transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSTransformValue
 */
class CSSTransformValue extends CSSStyleValue implements \ArrayAccess
{
	/**
	 * @var CSSTransformComponent[]
	 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSTransformValue/length
	 */
	private array $components;

	public function __construct(array $components)
	{
		$this->components = $components;
		parent::__construct('transform');
	}

	public function toString(): string
	{
		return implode(' ', array_map(fn ($c) => $c->toString(), $this->components));
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'length' => count($this->components),
			'is2D' => (function (): bool {
				foreach ($this->components as $component) {
					if (!$component->is2D) {
						return false;
					}
				}

				return true;
			})(),
			default => throw new \Error("Undefined property: {$name}"),
		};
	}

	public function toMatrix(): DOMMatrix
	{
		$matrix = new DOMMatrix();
		foreach ($this->components as $component) {
			$matrix->multiplySelf($component->toMatrix());
		}

		return $matrix;
	}

	public function clone(): CSSStyleValue
	{
		$clonedComponents = [];
		foreach ($this->components as $component) {
			$clonedComponents[] = clone $component;
		}

		return new self($clonedComponents);
	}

	public function offsetExists($offset): bool
	{
		return isset($this->components[$offset]);
	}

	public function offsetGet($offset): ?CSSTransformComponent
	{
		return $this->components[$offset] ?? null;
	}

	public function offsetSet($offset, $value): void
	{
		if (!$value instanceof CSSTransformComponent) {
			throw new \InvalidArgumentException('Value must be a CSSTransformComponent.');
		}

		if (is_null($offset)) {
			$this->components[] = $value;
		} else {
			$this->components[$offset] = $value;
		}
	}

	public function offsetUnset($offset): void
	{
		unset($this->components[$offset]);
	}

	/**
	 * @param callable(CSSTransformComponent, int, CSSTransformComponent[]): void $callback
	 */
	public function forEach(callable $callback): void
	{
		foreach ($this->components as $key => $value) {
			$callback($value, $key, $this->components);
		}
	}

	/**
	 * @return \Generator<int>
	 */
	public function keys(): \Generator
	{
		foreach ($this->components as $key => $value) {
			yield $key;
		}
	}

	/**
	 * @return \Generator<CSSTransformComponent>
	 */
	public function values(): \Generator
	{
		foreach ($this->components as $value) {
			yield $value;
		}
	}
}
