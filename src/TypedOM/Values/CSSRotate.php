<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

class CSSRotate extends CSSTransformComponent
{
    public $angle;
    public $x;
    public $y;
    public $z;

    public function __construct($p1, $p2 = null, $p3 = null, $p4 = null)
    {
        if ($p4 !== null) {
            $this->x = $p1;
            $this->y = $p2;
            $this->z = $p3;
            $this->angle = $p4;
            $this->is2D = false;
        } else {
            $this->angle = $p1;
            $this->x = new CSSUnitValue(0, 'number');
            $this->y = new CSSUnitValue(0, 'number');
            $this->z = new CSSUnitValue(1, 'number'); // rotate() is rotateZ()
            $this->is2D = true;
        }
    }

    public function toString(): string
    {
        if ($this->is2D) {
            return 'rotate(' . $this->angle->toString() . ')';
        } else {
            return 'rotate3d(' .
                $this->x->toString() . ', ' .
                $this->y->toString() . ', ' .
                $this->z->toString() . ', ' .
                $this->angle->toString() .
            ')';
        }
    }
}
