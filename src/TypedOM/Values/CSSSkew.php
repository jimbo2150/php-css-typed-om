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
        $axValue = $this->getValue('ax');
        $ayValue = $this->getValue('ay');
        
        $axDeg = $axValue ? $axValue->getNumericValue() : 0;
        $ayDeg = $ayValue ? $ayValue->getNumericValue() : 0;
        
        // CSS skew(ax, ay) creates a matrix where:
        // [1, tan(ay), 0, 0]
        // [tan(ax), 1, 0, 0]
        // We need to apply both skews in the correct order
        $axRad = deg2rad($axDeg);
        $ayRad = deg2rad($ayDeg);
        
        // Create the combined skew matrix directly
        $skewMatrix = new DOMMatrix([
            1.0, tan($ayRad), 0.0, 0.0,
            tan($axRad), 1.0, 0.0, 0.0,
            0.0, 0.0, 1.0, 0.0,
            0.0, 0.0, 0.0, 1.0,
        ]);
        
        $matrix->multiplySelf($skewMatrix);

        return $matrix;
    }

    public function clone(): self
    {
        $values = $this->cloneValues();
        return new self($values['ax'], $values['ay']);
    }
}
