<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Geometry;

use Error;
use InvalidArgumentException;

/**
 * Mutable 3D homogeneous point (W3C Geometry Interfaces Level 1).
 *
 * @property float $x
 * @property float $y
 * @property float $z
 * @property float $w
 * @see https://developer.mozilla.org/en-US/docs/Web/API/DOMPoint
 */
class DOMPoint extends DOMPointReadOnly
{
	public function setX(float $value): void
	{
		$this->xValue = DOMMatrixReadOnly::assertFiniteInternal($value, 'point.x');
	}

	public function setY(float $value): void
	{
		$this->yValue = DOMMatrixReadOnly::assertFiniteInternal($value, 'point.y');
	}

	public function setZ(float $value): void
	{
		$this->zValue = DOMMatrixReadOnly::assertFiniteInternal($value, 'point.z');
	}

	public function setW(float $value): void
	{
		$this->wValue = DOMMatrixReadOnly::assertFiniteInternal($value, 'point.w');
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		match ($offset) {
			'x' => $this->xValue = DOMMatrixReadOnly::assertFiniteInternal($value, 'point.x'),
			'y' => $this->yValue = DOMMatrixReadOnly::assertFiniteInternal($value, 'point.y'),
			'z' => $this->zValue = DOMMatrixReadOnly::assertFiniteInternal($value, 'point.z'),
			'w' => $this->wValue = DOMMatrixReadOnly::assertFiniteInternal($value, 'point.w'),
			default => throw new InvalidArgumentException('DOMPoint offset must be one of "x", "y", "z", or "w".'),
		};
	}

	public function offsetUnset(mixed $offset): void
	{
		throw new InvalidArgumentException('Cannot unset DOMPoint coordinates.');
	}

	public function __set(string $name, mixed $value): void
	{
		if (is_int($value) || is_float($value)) {
			switch ($name) {
				case 'x':
					$this->setX((float) $value);
					return;
				case 'y':
					$this->setY((float) $value);
					return;
				case 'z':
					$this->setZ((float) $value);
					return;
				case 'w':
					$this->setW((float) $value);
					return;
			}
		}

		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
