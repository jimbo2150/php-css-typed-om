<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

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

    public function toMatrix(): DOMMatrix
    {
        $matrix = new DOMMatrix();
        $angleRad = deg2rad($this->angle->getNumericValue()); // Assuming angle is in degrees

        if ($this->is2D) {
            $matrix->rotateSelf($angleRad);
        }
        else {
            $x = $this->x instanceof CSSUnitValue ? $this->x->getNumericValue() : $this->x;
            $y = $this->y instanceof CSSUnitValue ? $this->y->getNumericValue() : $this->y;
            $z = $this->z instanceof CSSUnitValue ? $this->z->getNumericValue() : $this->z;
            $matrix->rotateAxisAngleSelf($x, $y, $z, $angleRad);
        }
        return $matrix;
    }
}