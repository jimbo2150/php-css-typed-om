<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\wtf\SingleThreadIntegralWrapper;

use Jimbo2150\PhpCssTypedOm\WTF\wtf\CheckedArithmetic\RecordOverflow;

/**
 * @template T of int|float
 */
class IntegralType
{
	/** @var T */
	private int|float $value;
	private RecordOverflow $recordOverflow;

	/**
	 * @param T $value
	 */
	public function __construct(int|float $value, ?RecordOverflow $recordOverflow = null)
	{
		$this->value = $value;
		$this->recordOverflow = $recordOverflow ?? new RecordOverflow();
	}

	/**
	 * @return T
	 */
	public function value(): int|float
	{
		return $this->value;
	}

	public function hasOverflowed(): bool
	{
		return $this->recordOverflow->hasOverflowed();
	}

	public function setOverflowed(): void
	{
		$this->recordOverflow->setOverflowed();
	}

	/**
	 * @param T $other
	 *
	 * @return static
	 */
	public function add(int|float $other): self
	{
		$newValue = $this->value + $other;
		if ($this->didOverflow($this->value, $other, $newValue, '+')) {
			$this->recordOverflow->setOverflowed();
		}

		return new self($newValue, $this->recordOverflow);
	}

	/**
	 * @param T $other
	 *
	 * @return static
	 */
	public function subtract(int|float $other): self
	{
		$newValue = $this->value - $other;

		if ($this->didOverflow($this->value, $other, $newValue, '-')) {
			$this->recordOverflow->setOverflowed();
		}

		return new self($newValue, $this->recordOverflow);
	}

	/**
	 * @param T $other
	 *
	 * @return static
	 */
	public function multiply(int|float $other): self
	{
		$newValue = $this->value * $other;

		if ($this->didOverflow($this->value, $other, $newValue, '*')) {
			$this->recordOverflow->setOverflowed();
		}

		return new self($newValue, $this->recordOverflow);
	}

	/**
	 * @param T $other
	 *
	 * @return static
	 */
	public function divide(int|float $other): self
	{
		if (0 == $other) {
			$this->recordOverflow->setOverflowed();

			return new self(0, $this->recordOverflow);
		}
		$newValue = $this->value / $other;

		return new self($newValue, $this->recordOverflow);
	}

	public function isNegative(): bool
	{
		return $this->value < 0;
	}

	/**
	 * @param T $a
	 * @param T $b
	 * @param T $result
	 */
	private function didOverflow(int|float $a, int|float $b, int|float $result, string $operator): bool
	{
		if (is_int($a) && is_int($b) && is_int($result)) {
			switch ($operator) {
				case '+':
					return (($a > 0) && ($b > 0) && ($result <= 0)) || (($a < 0) && ($b < 0) && ($result >= 0));
				case '-':
					return (($a >= 0) && ($b < 0) && ($result < 0)) || (($a <= 0) && ($b > 0) && ($result > 0));
				case '*':
					return 0 != $a && 0 != $b && ($result / $a != $b);
				default:
					return false;
			}
		}

		if (is_float($result) && (is_infinite($result) || is_nan($result))) {
			return true;
		}

		return false;
	}
}
