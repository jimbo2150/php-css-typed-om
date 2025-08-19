<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TransformComponentTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

/**
 * Represents the scale() function of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSScale
 */
class CSSScale extends CSSTransformComponent
{
    use TransformComponentTrait;

    public function __construct($x, $y, $z = null)
    {
        $values = [
            'x' => $x,
            'y' => $y,
            'z' => $z
        ];
        $is2D = null === $z;

        $this->initializeTransformComponent($values, $is2D);
    }

    public function getTransformType(): string
    {
        return 'scale';
    }

    public function toString(): string
    {
        if ($this->is2D()) {
            return $this->toTransformString('scale', ['x', 'y']);
        } else {
            return $this->toTransformString('scale3d', ['x', 'y', 'z']);
        }
    }

    public function toMatrix(): DOMMatrix
    {
        $matrix = new DOMMatrix();
        $xValue = $this->getValue('x');
        $yValue = $this->getValue('y');
        $zValue = $this->is2D() ? null : $this->getValue('z');
        
        $xScale = $xValue ? $xValue->getNumericValue() : 1;
        $yScale = $yValue ? $yValue->getNumericValue() : 1;
        $zScale = $zValue ? $zValue->getNumericValue() : 1;

        $matrix->scaleSelf($xScale, $yScale, $zScale);

        return $matrix;
    }

    public function clone(): self
    {
        $values = $this->cloneValues();
        return new self($values['x'], $values['y'], $this->is2D() ? null : $values['z']);
    }
}
