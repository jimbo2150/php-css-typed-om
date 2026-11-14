<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS multiplicative inverse node (`1 / x`).
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathInvert
 */
class CSSMathInvert extends CSSMathValue
{
	public const ?string operator = 'invert';
	public const ?string sign = '/';

	/** @param CSSNumericValue|CSSNumericArray|list<CSSNumericValue> $values */
	public function __construct(CSSNumericValue|CSSNumericArray|array $values)
	{
		parent::__construct($values);
		$this->requireOperandCount(1, 1);
		$this->value = $this->values->values[0];
	}

	public function __get(string $name): mixed
	{
		if ($name === 'value') {
			return $this->values->values[0];
		}
		return parent::__get($name);
	}
}
