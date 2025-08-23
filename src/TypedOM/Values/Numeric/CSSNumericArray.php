<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Countable;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MultiValueTrait;

/**
 * Represents a CSS value that can be expressed as a number, or a number and a unit.
 * It is the base class for CSSUnitValue and other future math-related CSS classes.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericValue
 */
class CSSNumericArray extends CSSNumericValue implements Countable
{
	use MultiValueTrait;

	public function count(): int {
		return $this->length;
	}
}
