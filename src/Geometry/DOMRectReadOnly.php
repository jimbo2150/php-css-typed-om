<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Geometry;

use Error;
use InvalidArgumentException;
use ReflectionClass;

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
		$this->xValue = DOMMatrixReadOnly::assertFiniteFloat($x, 'rect.x');
		$this->yValue = DOMMatrixReadOnly::assertFiniteFloat($y, 'rect.y');
		$this->widthValue = DOMMatrixReadOnly::assertFiniteFloat($width, 'rect.width');
		$this->heightValue = DOMMatrixReadOnly::assertFiniteFloat($height, 'rect.height');
	}

	/**
	 * @param DOMRectReadOnly|array{x?: int|float, y?: int|float, width?: int|float, height?: int|float} $other
	 */
	public static function fromRect(self|array $other = []): static
	{
		if ($other instanceof self) {
			static $reflections = [];
			$cls = static::class;
			$refl = $reflections[$cls] ??= new ReflectionClass($cls);
			/** @var static $copy */
			$copy = $refl->newInstanceWithoutConstructor();
			$copy->xValue = $other->xValue;
			$copy->yValue = $other->yValue;
			$copy->widthValue = $other->widthValue;
			$copy->heightValue = $other->heightValue;

			return $copy;
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
		$opposite = DOMMatrixReadOnly::assertFiniteFloat($this->yValue + $this->heightValue, 'rect.top');
		return min($this->yValue, $opposite);
	}

	public function getRight(): float
	{
		$opposite = DOMMatrixReadOnly::assertFiniteFloat($this->xValue + $this->widthValue, 'rect.right');
		return max($this->xValue, $opposite);
	}

	public function getBottom(): float
	{
		$opposite = DOMMatrixReadOnly::assertFiniteFloat($this->yValue + $this->heightValue, 'rect.bottom');
		return max($this->yValue, $opposite);
	}

	public function getLeft(): float
	{
		$opposite = DOMMatrixReadOnly::assertFiniteFloat($this->xValue + $this->widthValue, 'rect.left');
		return min($this->xValue, $opposite);
	}

	/** @return array{x: float, y: float, width: float, height: float, top: float, right: float, bottom: float, left: float} */
	public function toJSON(): array
	{
		$xOpposite = DOMMatrixReadOnly::assertFiniteFloat($this->xValue + $this->widthValue, 'rect.right');
		$yOpposite = DOMMatrixReadOnly::assertFiniteFloat($this->yValue + $this->heightValue, 'rect.bottom');

		return [
			'x' => $this->xValue,
			'y' => $this->yValue,
			'width' => $this->widthValue,
			'height' => $this->heightValue,
			'top' => min($this->yValue, $yOpposite),
			'right' => max($this->xValue, $xOpposite),
			'bottom' => max($this->yValue, $yOpposite),
			'left' => min($this->xValue, $xOpposite),
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
