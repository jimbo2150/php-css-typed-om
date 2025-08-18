<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

/**
 * Represents the scale() function of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSScale
 */
class CSSScale extends CSSTransformComponent
{
	private $x;
	private $y;
	private $z;

	public function __construct($x, $y, $z = null)
	{
		$this->x = $x;
		$this->y = $y;
		$this->z = $z;
		$this->is2D = null === $z;
	}

	public function toString(): string
	{
		$str = 'scale';
		if (!$this->is2D) {
			$str .= '3d';
		}
		$str .= '('.$this->x->toString();
		$str .= ', '.$this->y->toString();
		if (!$this->is2D) {
			$str .= ', '.$this->z->toString();
		}
		$str .= ')';

		return $str;
	}

	public function toMatrix(): DOMMatrix
	{
		$matrix = new DOMMatrix();
		$xScale = $this->x instanceof CSSUnitValue ? $this->x->getNumericValue() : $this->x;
		$yScale = $this->y instanceof CSSUnitValue ? $this->y->getNumericValue() : $this->y;
		$zScale = $this->z instanceof CSSUnitValue ? $this->z->getNumericValue() : ($this->z ? $this->z : 1);

		$matrix->scaleSelf($xScale, $yScale, $zScale);

		return $matrix;
	}

	public function clone(): self
	{
		$clonedX = $this->x instanceof CSSUnitValue ? clone $this->x : $this->x;
		$clonedY = $this->y instanceof CSSUnitValue ? clone $this->y : $this->y;
		$clonedZ = $this->z instanceof CSSUnitValue ? clone $this->z : $this->z;

		return new self($clonedX, $clonedY, $clonedZ);
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'x' => $this->x,
			'y' => $this->y,
			'z' => $this->z,
			'is2D' => $this->is2D,
			default => throw new \Error(sprintf('Undefined property: %s::$%s', self::class, $name)),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		throw new \Error(sprintf('Cannot set property %s::$%s', self::class, $name));
	}
}
