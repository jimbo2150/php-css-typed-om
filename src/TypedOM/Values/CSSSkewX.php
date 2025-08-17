<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

/**
 * Represents the skewX() function of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSSkewX
 */
class CSSSkewX extends CSSTransformComponent
{
	private CSSNumericValue $ax;

	public function __construct(CSSNumericValue $ax)
	{
		$this->ax = $ax;
		$this->is2D = true;
	}

	public function toString(): string
	{
		return 'skewX('.$this->ax->toString().')';
	}

	public function toMatrix(): DOMMatrix
	{
		$matrix = new DOMMatrix();
		$angleRad = deg2rad($this->ax->getNumericValue()); // Assuming angle is in degrees
		$matrix->skewXSelf($angleRad);

		return $matrix;
	}
}
