<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

/**
 * Represents the matrix() and matrix3d() functions of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMatrixComponent
 */
class CSSMatrixComponent extends CSSTransformComponent
{
    private DOMMatrix $matrix;

    public function __construct(DOMMatrix $matrix, array $options = [])
    {
        $this->matrix = $matrix;
        $this->is2D = $options['is2D'] ?? $matrix->is2D;
    }

    public function toString(): string
    {
        return $this->matrix->toString();
    }

    public function toMatrix(): DOMMatrix
    {
        return $this->matrix;
    }
}