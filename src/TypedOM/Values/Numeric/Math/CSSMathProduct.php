<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS product expression (`*` / `/` inside `calc()`).
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathProduct
 */
class CSSMathProduct extends CSSMathValue
{
	public const ?string operator = 'product';
	public const ?string sign = '*';

	/** @param CSSNumericValue|CSSNumericArray|list<CSSNumericValue> $values */
	public function __construct(CSSNumericValue|CSSNumericArray|array $values)
	{
		parent::__construct($values);
		$this->requireOperandCount(1);
		$this->cachedNumericType = $this->getNumericTypeInternal();
	}
}
