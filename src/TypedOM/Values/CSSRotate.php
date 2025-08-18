<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TransformComponentTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

class CSSRotate extends CSSTransformComponent
{
    use TransformComponentTrait;
    use MagicPropertyAccessTrait;

    public function __construct($p1, $p2 = null, $p3 = null, $p4 = null)
    {
        if (null !== $p4) {
            // 3D rotation: rotate3d(x, y, z, angle)
            $values = [
                'x' => $p1,
                'y' => $p2,
                'z' => $p3,
                'angle' => $p4
            ];
            $is2D = false;
        } else {
            // 2D rotation: rotate(angle)
            $values = [
                'x' => new CSSUnitValue(0, 'number'),
                'y' => new CSSUnitValue(0, 'number'),
                'z' => new CSSUnitValue(1, 'number'), // rotate() is rotateZ()
                'angle' => $p1
            ];
            $is2D = true;
        }

        $this->initializeTransformComponent($values, $is2D);
    }

    public function getTransformType(): string
    {
        return 'rotate';
    }

    public function toString(): string
    {
        if ($this->is2D()) {
            return $this->toTransformString('rotate', ['angle']);
        } else {
            return $this->toTransformString('rotate3d', ['x', 'y', 'z', 'angle']);
        }
    }

    public function toMatrix(): DOMMatrix
    {
        $matrix = new DOMMatrix();
        $angle = $this->getValue('angle');
        $angleRad = deg2rad($angle->getNumericValue());

        if ($this->is2D()) {
            $matrix->rotateSelf($angleRad);
        } else {
            $x = $this->getValue('x')->getNumericValue();
            $y = $this->getValue('y')->getNumericValue();
            $z = $this->getValue('z')->getNumericValue();
            $matrix->rotateAxisAngleSelf($x, $y, $z, $angleRad);
        }

        return $matrix;
    }

    public function clone(): self
    {
        $cloned = new self($this->getValue('angle'));
        $cloned->initializeTransformComponent($this->cloneValues(), $this->is2D());
        return $cloned;
    }
}
