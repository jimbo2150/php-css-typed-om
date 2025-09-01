<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Trait for CSS math operators.
 */
trait CSSMathOperatorTrait
{
	use CSSMathOperationTrait;

	/** @var string|null The sign for the operator */
	public const ?string sign = null;
	/** @var string|null The operator name */
	public const ?string operator = null;

	/**
	 * Convert to string representation.
	 *
	 * @return string The string representation
	 */
	public function toString(): string
	{
		return implode(' ' . $this->operator . ' ', $this->values->values);
	}
}