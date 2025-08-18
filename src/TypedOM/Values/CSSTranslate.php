<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

/**
 * Represents the translate() function of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSTranslate
 */
class CSSTranslate extends CSSTransformComponent
{
	private CSSNumericValue $x;
	private CSSNumericValue $y;
	private ?CSSNumericValue $z;

	public function __construct(CSSNumericValue $x, CSSNumericValue $y, ?CSSNumericValue $z = null)
	{
		$this->x = $x;
		$this->y = $y;
		$this->z = $z;
		$this->is2D = null === $z;
	}

	public function toString(): string
	{
		$str = 'translate';
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
		$xPx = $this->x instanceof CSSUnitValue ? $this->x->to('px')->getNumericValue() : $this->x->getNumericValue();
		$yPx = $this->y instanceof CSSUnitValue ? $this->y->to('px')->getNumericValue() : $this->y->getNumericValue();
		$zPx = $this->z instanceof CSSUnitValue ? $this->z->to('px')->getNumericValue() : ($this->z ? $this->z->getNumericValue() : 0);

		$matrix->translateSelf($xPx, $yPx, $zPx);

		return $matrix;
	}

	public function clone(): self
	{
		$clonedX = clone $this->x;
		$clonedY = clone $this->y;
		$clonedZ = $this->z ? clone $this->z : null;

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
