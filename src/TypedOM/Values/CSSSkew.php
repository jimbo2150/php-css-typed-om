<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

/**
 * Represents the skew() function of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSSkew
 */
class CSSSkew extends CSSTransformComponent
{
	private CSSNumericValue $ax;
	private CSSNumericValue $ay;

	public function __construct(CSSNumericValue $ax, CSSNumericValue $ay)
	{
		$this->ax = $ax;
		$this->ay = $ay;
		$this->is2D = true;
	}

	public function toString(): string
	{
		return 'skew('.$this->ax->toString().', '.$this->ay->toString().')';
	}

	public function toMatrix(): DOMMatrix
	{
		$matrix = new DOMMatrix();
		$axRad = deg2rad($this->ax->getNumericValue()); // Assuming angle is in degrees
		$ayRad = deg2rad($this->ay->getNumericValue()); // Assuming angle is in degrees
		$matrix->skewSelf($axRad, $ayRad);

		return $matrix;
	}

	public function clone(): self
	{
		return new self(clone $this->ax, clone $this->ay);
	}
}
