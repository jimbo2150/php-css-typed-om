<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

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
}
