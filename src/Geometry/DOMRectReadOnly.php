<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Geometry;

use Error;
use InvalidArgumentException;

/**
 * Read-only rectangle with computed edge coordinates (W3C Geometry Interfaces Level 1).
 *
 * @phpstan-consistent-constructor
 * @property-read float $x
 * @property-read float $y
 * @property-read float $width
 * @property-read float $height
 * @property-read float $top
 * @property-read float $right
 * @property-read float $bottom
 * @property-read float $left
 * @see https://developer.mozilla.org/en-US/docs/Web/API/DOMRectReadOnly
 */
class DOMRectReadOnly
{
	protected float $xValue;
	protected float $yValue;
	protected float $widthValue;
	protected float $heightValue;

	/**
	 * @throws InvalidArgumentException When any coordinate or dimension is non-finite
	 */
	public function __construct(
		float $x = 0.0,
		float $y = 0.0,
		float $width = 0.0,
		float $height = 0.0,
	) {
		$this->xValue = DOMMatrixReadOnly::assertFiniteInternal($x, 'rect.x');
		$this->yValue = DOMMatrixReadOnly::assertFiniteInternal($y, 'rect.y');
		$this->widthValue = DOMMatrixReadOnly::assertFiniteInternal($width, 'rect.width');
		$this->heightValue = DOMMatrixReadOnly::assertFiniteInternal($height, 'rect.height');
	}

	/**
	 * @param DOMRectReadOnly|array{x?: int|float, y?: int|float, width?: int|float, height?: int|float} $other
	 */
	public static function fromRect(self|array $other = []): static
	{
		if ($other instanceof self) {
			return new static($other->xValue, $other->yValue, $other->widthValue, $other->heightValue);
		}

		$x = DOMMatrixReadOnly::assertFiniteInternal($other['x'] ?? 0.0, 'rect.x');
		$y = DOMMatrixReadOnly::assertFiniteInternal($other['y'] ?? 0.0, 'rect.y');
		$width = DOMMatrixReadOnly::assertFiniteInternal($other['width'] ?? 0.0, 'rect.width');
		$height = DOMMatrixReadOnly::assertFiniteInternal($other['height'] ?? 0.0, 'rect.height');

		return new static($x, $y, $width, $height);
	}

	public function getX(): float
	{
		return $this->xValue;
	}

	public function getY(): float
	{
		return $this->yValue;
	}

	public function getWidth(): float
	{
		return $this->widthValue;
	}

	public function getHeight(): float
	{
		return $this->heightValue;
	}

	public function getTop(): float
	{
		$opposite = DOMMatrixReadOnly::assertFiniteInternal($this->yValue + $this->heightValue, 'rect.top');
		return min($this->yValue, $opposite);
	}

	public function getRight(): float
	{
		$opposite = DOMMatrixReadOnly::assertFiniteInternal($this->xValue + $this->widthValue, 'rect.right');
		return max($this->xValue, $opposite);
	}

	public function getBottom(): float
	{
		$opposite = DOMMatrixReadOnly::assertFiniteInternal($this->yValue + $this->heightValue, 'rect.bottom');
		return max($this->yValue, $opposite);
	}

	public function getLeft(): float
	{
		$opposite = DOMMatrixReadOnly::assertFiniteInternal($this->xValue + $this->widthValue, 'rect.left');
		return min($this->xValue, $opposite);
	}

	/** @return array{x: float, y: float, width: float, height: float, top: float, right: float, bottom: float, left: float} */
	public function toJSON(): array
	{
		return [
			'x' => $this->xValue,
			'y' => $this->yValue,
			'width' => $this->widthValue,
			'height' => $this->heightValue,
			'top' => $this->getTop(),
			'right' => $this->getRight(),
			'bottom' => $this->getBottom(),
			'left' => $this->getLeft(),
		];
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'x' => $this->xValue,
			'y' => $this->yValue,
			'width' => $this->widthValue,
			'height' => $this->heightValue,
			'top' => $this->getTop(),
			'right' => $this->getRight(),
			'bottom' => $this->getBottom(),
			'left' => $this->getLeft(),
			default => throw new Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'x', 'y', 'width', 'height', 'top', 'right', 'bottom', 'left' => true,
			default => false,
		};
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
