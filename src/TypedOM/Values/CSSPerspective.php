<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

/**
 * Represents the perspective() function of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPerspective
 */
class CSSPerspective extends CSSTransformComponent
{
    private CSSNumericValue $length;

    public function __construct(CSSNumericValue $length)
    {
        $this->length = $length;
        $this->is2D = false;
    }

    public function toString(): string
    {
        return 'perspective(' . $this->length->toString() . ')';
    }

    public function toMatrix(): DOMMatrix
    {
        $matrix = new DOMMatrix();
        $lengthPx = $this->length instanceof CSSUnitValue ? $this->length->to('px')->getNumericValue() : $this->length->getNumericValue();
        $matrix->setPerspective($lengthPx);
        return $matrix;
    }
}