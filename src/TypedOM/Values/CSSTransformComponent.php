<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

/**
 * Base class for components of a CSSTransformValue.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSTransformComponent
 */
abstract class CSSTransformComponent
{
	abstract public function toString(): string;

	public bool $is2D;

	abstract public function toMatrix(): DOMMatrix;

	abstract public function clone(): self;

	public function __get(string $name)
	{
		return match ($name) {
			'is2D' => $this->is2D,
			default => throw new \Exception('Undefined property: '.$name),
		};
	}

	public function __set(string $name, $value): void
	{
		match ($name) {
			'is2D' => $this->is2D = $value,
			default => throw new \Exception('Undefined property: '.$name),
		};
	}
}
