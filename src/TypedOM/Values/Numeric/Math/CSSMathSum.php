<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS sum expression (`+` / `-` inside `calc()`).
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathSum
 */
class CSSMathSum extends CSSMathValue
{
	public const ?string operator = 'sum';
	public const ?string sign = '+';

	/** @param CSSNumericValue|CSSNumericArray|list<CSSNumericValue> $values */
	public function __construct(CSSNumericValue|CSSNumericArray|array $values)
	{
		parent::__construct($values);
		$this->requireOperandCount(1);
		$this->validateAdditiveTypes();
	}
}
