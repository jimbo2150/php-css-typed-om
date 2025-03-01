<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\wtf\CheckedArithmetic;

use Jimbo2150\PhpCssTypedOm\WTF\wtf\SingleThreadIntegralWrapper\IntegralType;

/**
 * @template T
 */
class Checked
{
	/** @var IntegralType<T> */
	private IntegralType $integralType;

	/**
	 * @param IntegralType<T> $integralType
	 */
	public function __construct(IntegralType $integralType)
	{
		$this->integralType = $integralType;
	}

	public function value(): int|float
	{
		return $this->integralType->value();
	}

	public function hasOverflowed(): bool
	{
		return $this->integralType->hasOverflowed();
	}

	/**
	 * @param T $other
	 */
	public function add(int|float $other): self
	{
		return new self($this->integralType->add($other));
	}

	/**
	 * @param T $other
	 */
	public function subtract(int|float $other): self
	{
		return new self($this->integralType->subtract($other));
	}

	/**
	 * @param T $other
	 */
	public function multiply(int|float $other): self
	{
		return new self($this->integralType->multiply($other));
	}

	/**
	 * @param T $other
	 */
	public function divide(int|float $other): self
	{
		return new self($this->integralType->divide($other));
	}
}
