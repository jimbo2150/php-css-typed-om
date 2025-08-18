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
        return 'perspective('.$this->length->toString().')';
    }

    public function toMatrix(): DOMMatrix
    {
        $matrix = new DOMMatrix();
        
        if ($this->length instanceof CSSUnitValue) {
            $converted = $this->length->to('px');
            $lengthPx = $converted ? $converted->value : 0;
        } else {
            // For other CSSNumericValue types, we need to handle appropriately
            // According to spec, perspective should be a length value
            $lengthPx = 0;
        }
        
        // Apply perspective transformation by setting the appropriate matrix values
        // Perspective matrix: [1,0,0,0, 0,1,0,0, 0,0,1,-1/length, 0,0,0,1]
        if ($lengthPx > 0) {
            $matrix->m34 = -1 / $lengthPx;
        }

        return $matrix;
    }

    public function clone(): self
    {
        return new self(clone $this->length);
    }
}
