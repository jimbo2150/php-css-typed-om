<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

/**
 * Represents a CSS position value.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPositionValue
 */
class CSSPositionValue extends CSSStyleValue
{
	private CSSNumericValue $x;
	private CSSNumericValue $y;

	public function __construct(CSSNumericValue $x, CSSNumericValue $y)
	{
		$this->x = $x;
		$this->y = $y;
		parent::__construct('position');
	}

	public function getX(): CSSNumericValue
	{
		return $this->x;
	}

	public function getY(): CSSNumericValue
	{
		return $this->y;
	}

	public function toString(): string
	{
		return $this->x->toString().' '.$this->y->toString();
	}

	public function isValid(): bool
	{
		return $this->x->isValid() && $this->y->isValid();
	}

	public function clone(): CSSStyleValue
	{
		return new self($this->x, $this->y);
	}
}
