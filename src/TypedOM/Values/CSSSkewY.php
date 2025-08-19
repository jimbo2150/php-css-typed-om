<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TransformComponentTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

/**
 * Represents the skewY() function of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSSkewY
 */
class CSSSkewY extends CSSTransformComponent
{
    use TransformComponentTrait;

    public function __construct(CSSNumericValue $ay)
    {
        $values = ['ay' => $ay];
        $this->initializeTransformComponent($values, true);
    }

    public function getTransformType(): string
    {
        return 'skewY';
    }

    public function toString(): string
    {
        return $this->toTransformString('skewY', ['ay']);
    }

    public function toMatrix(): DOMMatrix
    {
        $matrix = new DOMMatrix();
        $ayValue = $this->getValue('ay');
        $angleRad = $ayValue ? deg2rad($ayValue->getNumericValue()) : 0;
        $matrix->skewYSelf($angleRad);

        return $matrix;
    }

    public function clone(): self
    {
        $values = $this->cloneValues();
        return new self($values['ay']);
    }
}
