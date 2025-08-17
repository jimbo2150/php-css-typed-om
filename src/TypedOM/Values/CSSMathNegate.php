<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

/**
 * A CSSMathNegate is a CSSNumericValue that is the result of a negation.
 * It is used to represent `calc()` expressions with negation.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMathNegate
 */
class CSSMathNegate extends CSSNumericValue
{
	private CSSNumericValue $value;

	public function __construct(CSSNumericValue $value)
	{
		$this->value = $value;
		parent::__construct('math-negate');
	}

	public function getValue(): CSSNumericValue
	{
		return $this->value;
	}

	public function toString(): string
	{
		return 'calc(-1 * '.$this->value->toString().')';
	}

	public function isValid(): bool
	{
		return $this->value->isValid();
	}

	public function clone(): CSSStyleValue
	{
		return new self($this->value);
	}

	public function to(string $unit): ?CSSUnitValue
	{
		// Not implemented
		return null;
	}
}
