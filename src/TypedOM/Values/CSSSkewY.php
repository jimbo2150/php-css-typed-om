<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

/**
 * Represents the skewY() function of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSSkewY
 */
class CSSSkewY extends CSSTransformComponent
{
	private CSSNumericValue $ay;

	public function __construct(CSSNumericValue $ay)
	{
		$this->ay = $ay;
		$this->is2D = true;
	}

	public function toString(): string
	{
		return 'skewY('.$this->ay->toString().')';
	}

	public function toMatrix(): DOMMatrix
	{
		$matrix = new DOMMatrix();
		$angleRad = deg2rad($this->ay->getNumericValue()); // Assuming angle is in degrees
		$matrix->skewYSelf($angleRad);

		return $matrix;
	}
}
