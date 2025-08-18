<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TransformComponentTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

/**
 * Represents the skew() function of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSSkew
 */
class CSSSkew extends CSSTransformComponent
{
    use TransformComponentTrait;
    use MagicPropertyAccessTrait;

    public function __construct(CSSNumericValue $ax, CSSNumericValue $ay)
    {
        $values = [
            'ax' => $ax,
            'ay' => $ay
        ];
        
        $this->initializeTransformComponent($values, true);
    }

    public function getTransformType(): string
    {
        return 'skew';
    }

    public function toString(): string
    {
        return $this->toTransformString('skew', ['ax', 'ay']);
    }

    public function toMatrix(): DOMMatrix
    {
        $matrix = new DOMMatrix();
        $axRad = deg2rad($this->getValue('ax')->getNumericValue());
        $ayRad = deg2rad($this->getValue('ay')->getNumericValue());
        $matrix->skewSelf($axRad, $ayRad);

        return $matrix;
    }

    public function clone(): self
    {
        $values = $this->cloneValues();
        return new self($values['ax'], $values['ay']);
    }
}
