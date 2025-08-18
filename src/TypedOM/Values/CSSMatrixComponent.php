<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

/**
 * Represents the matrix() and matrix3d() functions of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMatrixComponent
 */
class CSSMatrixComponent extends CSSTransformComponent
{
	public DOMMatrix $matrix;

	public function __construct(DOMMatrix $matrix, array $options = [])
	{
		$this->matrix = $matrix;
		$this->is2D = $options['is2D'] ?? $matrix->is2D;
	}

	public function setMatrix(DOMMatrix $matrix): void
	{
		$this->matrix = $matrix;
	}

	public function toString(): string
	{
		return $this->matrix->toString();
	}

	public function toMatrix(): DOMMatrix
	{
		return $this->matrix;
	}

	public function clone(): self
	{
		return new self(clone $this->matrix, ['is2D' => $this->is2D]);
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'matrix' => $this->matrix,
			'is2D' => $this->is2D,
			default => throw new \Error(sprintf('Undefined property: %s::$%s', self::class, $name)),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		throw new \Error(sprintf('Cannot set property %s::$%s', self::class, $name));
	}
}
