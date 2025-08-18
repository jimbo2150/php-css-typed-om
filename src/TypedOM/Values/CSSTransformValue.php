<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

/**
 * Represents the transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSTransformValue
 */
class CSSTransformValue extends CSSStyleValue
{
	/** @var CSSTransformComponent[] */
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

	public function __get(string $name)
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
			default => throw new \Exception('Undefined property: '.$name),
		};
	}

	public function toMatrix(): DOMMatrix
	{
		$matrix = new DOMMatrix();
		foreach ($this->components as $component) {
			$matrix = $matrix->multiply($component->toMatrix());
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

	/**
	 * @return \Generator<array{int, CSSTransformComponent}>
	 */
	public function entries(): \Generator
	{
		foreach ($this->components as $key => $value) {
			yield [$key, $value];
		}
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
