<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TransformComponentTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

/**
 * Represents the skewX() function of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSSkewX
 */
class CSSSkewX extends CSSTransformComponent
{
    use TransformComponentTrait;
    use MagicPropertyAccessTrait;

    public function __construct(CSSNumericValue $ax)
    {
        $values = ['ax' => $ax];
        $this->initializeTransformComponent($values, true);
    }

    public function getTransformType(): string
    {
        return 'skewX';
    }

    public function toString(): string
    {
        return $this->toTransformString('skewX', ['ax']);
    }

    public function toMatrix(): DOMMatrix
    {
        $matrix = new DOMMatrix();
        $angleRad = deg2rad($this->getValue('ax')->getNumericValue());
        $matrix->skewXSelf($angleRad);

        return $matrix;
    }

    public function clone(): self
    {
        $values = $this->cloneValues();
        return new self($values['ax']);
    }
}
