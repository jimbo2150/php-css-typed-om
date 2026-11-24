<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS `clamp(lower, value, upper)` calculation.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathClamp
 */
final class CSSMathClamp extends CSSMathValue
{
	public const ?string operator = 'clamp';

	public CSSNumericValue $lower {
		get => $this->values->values[0];
	}

	public CSSNumericValue $upper {
		get => $this->values->values[2];
	}

	/**
	 * Accepts either three positional arguments `(lower, value, upper)` per MDN
	 * or a 3-element `CSSNumericArray|list<CSSNumericValue>`.
	 *
	 * @param CSSNumericValue|CSSNumericArray|list<CSSNumericValue> $lowerOrValues
	 */
	public function __construct(
		CSSNumericValue|CSSNumericArray|array $lowerOrValues,
		?CSSNumericValue $value = null,
		?CSSNumericValue $upper = null,
	) {
		if ($value !== null && $upper !== null && $lowerOrValues instanceof CSSNumericValue) {
			parent::__construct([$lowerOrValues, $value, $upper]);
		} else {
			parent::__construct($lowerOrValues);
		}
		$this->requireOperandCount(3, 3);
		$this->validateAdditiveTypes();
		$this->value = $this->values->values[1];
	}

	public function getLower(): CSSNumericValue
	{
		return $this->values->values[0];
	}

	public function getValue(): CSSNumericValue
	{
		return $this->values->values[1];
	}

	public function getUpper(): CSSNumericValue
	{
		return $this->values->values[2];
	}
}
