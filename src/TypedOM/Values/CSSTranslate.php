<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TransformComponentTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

/**
 * Represents the translate() function of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSTranslate
 */
class CSSTranslate extends CSSTransformComponent
{
    use TransformComponentTrait;
    use MagicPropertyAccessTrait;

    public function __construct(CSSNumericValue $x, CSSNumericValue $y, ?CSSNumericValue $z = null)
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
        return 'translate';
    }

    public function toString(): string
    {
        if ($this->is2D()) {
            return $this->toTransformString('translate', ['x', 'y']);
        } else {
            return $this->toTransformString('translate3d', ['x', 'y', 'z']);
        }
    }

    public function toMatrix(): DOMMatrix
    {
        $matrix = new DOMMatrix();
        
        $x = $this->getValue('x');
        $y = $this->getValue('y');
        $z = $this->is2D() ? null : $this->getValue('z');
        
        $xPx = $x instanceof CSSUnitValue ? ($x->to('px')->value ?? 0) : 0;
        $yPx = $y instanceof CSSUnitValue ? ($y->to('px')->value ?? 0) : 0;
        $zPx = $z instanceof CSSUnitValue ? ($z->to('px')->value ?? 0) : 0;

        $matrix->translateSelf($xPx, $yPx, $zPx);

        return $matrix;
    }

    public function clone(): self
    {
        $values = $this->cloneValues();
        return new self($values['x'], $values['y'], $this->is2D() ? null : $values['z']);
    }
}
