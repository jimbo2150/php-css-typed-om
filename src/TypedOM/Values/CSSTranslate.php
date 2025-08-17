<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

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
        $this->is2D = $z === null;
    }

    public function toString(): string
    {
        $str = 'translate';
        if (!$this->is2D) {
            $str .= '3d';
        }
        $str .= '(' . $this->x->toString();
        $str .= ', ' . $this->y->toString();
        if (!$this->is2D) {
            $str .= ', ' . $this->z->toString();
        }
        $str .= ')';
        return $str;
    }
}
