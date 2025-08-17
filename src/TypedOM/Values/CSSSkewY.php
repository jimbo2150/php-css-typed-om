<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

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
        return 'skewY(' . $this->ay->toString() . ')';
    }
}
