<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS arithmetic negation node (`-x`).
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathNegate
 */
class CSSMathNegate extends CSSMathValue
{
	public const ?string operator = 'negate';
	public const ?string sign = '-';

	public protected(set) ?CSSNumericValue $value = null;

	/** @param CSSNumericValue|CSSNumericArray|list<CSSNumericValue> $values */
	public function __construct(CSSNumericValue|CSSNumericArray|array $values)
	{
		parent::__construct($values);
		$this->requireOperandCount(1, 1);
		$this->value = $this->values->values[0];
	}
}
