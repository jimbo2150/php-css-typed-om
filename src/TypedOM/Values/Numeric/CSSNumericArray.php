<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Countable;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MultiValueArrayTrait;

/**
 * Represents a CSS value that can be expressed as a number, or a number and a unit.
 * It is the base class for CSSUnitValue and other future math-related CSS classes.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericValue
 */
class CSSNumericArray extends CSSNumericValue implements Countable
{
	use MultiValueArrayTrait;

	/**
	 * Count the number of values.
	 *
	 * @return int The number of values
	 */
	public function count(): int {
		return $this->length;
	}
}
