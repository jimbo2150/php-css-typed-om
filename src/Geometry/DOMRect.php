<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Geometry;

use Error;

/**
 * Mutable rectangle with read-only computed edge coordinates (W3C Geometry Interfaces Level 1).
 *
 * @property float $x
 * @property float $y
 * @property float $width
 * @property float $height
 * @see https://developer.mozilla.org/en-US/docs/Web/API/DOMRect
 */
class DOMRect extends DOMRectReadOnly
{
	public function setX(float $value): void
	{
		$this->xValue = DOMMatrixReadOnly::assertFiniteInternal($value, 'rect.x');
	}

	public function setY(float $value): void
	{
		$this->yValue = DOMMatrixReadOnly::assertFiniteInternal($value, 'rect.y');
	}

	public function setWidth(float $value): void
	{
		$this->widthValue = DOMMatrixReadOnly::assertFiniteInternal($value, 'rect.width');
	}

	public function setHeight(float $value): void
	{
		$this->heightValue = DOMMatrixReadOnly::assertFiniteInternal($value, 'rect.height');
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
				case 'width':
					$this->setWidth((float) $value);
					return;
				case 'height':
					$this->setHeight((float) $value);
					return;
			}
		}

		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
