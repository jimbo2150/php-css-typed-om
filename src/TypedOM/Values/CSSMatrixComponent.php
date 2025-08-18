<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\TransformComponentTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

/**
 * Represents the matrix() and matrix3d() functions of the CSS transform property.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMatrixComponent
 */
class CSSMatrixComponent extends CSSTransformComponent
{
    use TransformComponentTrait;
    use MagicPropertyAccessTrait;

    private DOMMatrix $matrix;

    public function __construct(DOMMatrix $matrix, array $options = [])
    {
        $this->matrix = $matrix;
        $this->is2D = $options['is2D'] ?? $matrix->is2D;
    }

    public function getTransformType(): string
    {
        return 'matrix';
    }

    public function setMatrix(DOMMatrix $matrix): void
    {
        $this->matrix = $matrix;
    }

    public function toString(): string
    {
        return $this->matrix->toString();
    }

    public function toMatrix(): DOMMatrix
    {
        return $this->matrix;
    }

    public function clone(): self
    {
        return new self($this->matrix, ['is2D' => $this->is2D]);
    }

    public function getValues(): array
    {
        return [];
    }

    public function getValue(string $name): ?CSSUnitValue
    {
        return null;
    }

    public function setValue(string $name, CSSUnitValue $value): void
    {
        // Not applicable for matrix component
    }

    public function isValid(): bool
    {
        return true;
    }
}
