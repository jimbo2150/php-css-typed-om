<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;

/**
 * Base class for components of a CSSTransformValue.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSTransformComponent
 */
abstract class CSSTransformComponent
{
	abstract public function toString(): string;

	/**
	 * @var bool gets and sets whether the component is a 2D or 3D transform
	 */
	public bool $is2D = true;

	abstract public function toMatrix(): DOMMatrix;

	abstract public function clone(): self;
}
