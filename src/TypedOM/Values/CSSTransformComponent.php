<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

/**
 * Base class for components of a CSSTransformValue.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSTransformComponent
 */
abstract class CSSTransformComponent
{
    public abstract function toString(): string;

    /**
     * @var bool Gets and sets whether the component is a 2D or 3D transform.
     */
    public bool $is2D = true;
}
