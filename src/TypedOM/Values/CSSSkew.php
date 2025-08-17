<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

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
        return 'skew(' . $this->ax->toString() . ', ' . $this->ay->toString() . ')';
    }
}
