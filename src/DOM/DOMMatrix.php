<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\DOM;

/**
 * A placeholder for the DOMMatrix interface.
 * @see https://developer.mozilla.org/en-US/docs/Web/API/DOMMatrix
 */
class DOMMatrix
{
    public bool $is2D;
    private array $values;

    public function __construct(array $values, bool $is2D = true)
    {
        $this->values = $values;
        $this->is2D = $is2D;
    }

    public function toString(): string
    {
        if ($this->is2D) {
            return 'matrix(' . implode(', ', $this->values) . ')';
        } else {
            return 'matrix3d(' . implode(', ', $this->values) . ')';
        }
    }
}
