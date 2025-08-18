<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MathOperationsTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

/**
 * CSS Unit Value for Typed OM
 * Represents a CSS value with a unit (e.g., 10px, 50%, 2em).
 */
class CSSUnitValue extends CSSNumericValue
{
	use MathOperationsTrait;
	use MagicPropertyAccessTrait;

	private float $value;
	private string $unit;

	public function __construct(float $value, string $unit)
	{
		parent::__construct('unit'); // Call CSSStyleValue constructor directly
		$this->value = $value;
		$this->unit = $unit;
		$this->initializeMathOperations($value, $unit);
	}

	/**
	 * Get the numeric value.
	 */
	public function getNumericValue(): float
	{
		return $this->value;
	}

	/**
	 * Set the numeric value.
	 */
	public function setNumericValue(float $value): void
	{
		$this->value = $value;
		$this->initializeMathOperations($value, $this->unit);
	}

	/**
	 * Get the unit.
	 */
	public function getUnit(): string
	{
		return $this->unit;
	}

	/**
	 * Set the unit.
	 */
	public function setUnit(string $unit): void
	{
		$this->unit = $unit;
		$this->initializeMathOperations($this->value, $unit);
	}

	/**
	 * Magic getter for property access.
	 */
	public function __get(string $name): mixed
	{
		return match ($name) {
			'value' => $this->value,
			'unit' => $this->unit,
			'type' => $this->type,
			default => throw new \Error("Undefined property: {$name}"),
		};
	}

	/**
	 * Magic setter for property access.
	 */
	public function __set(string $name, mixed $value): void
	{
		match ($name) {
			'value' => $this->setNumericValue((float) $value),
			'unit' => $this->setUnit((string) $value),
			default => throw new \Error("Undefined property: {$name}"),
		};
	}

	/**
	 * Convert to string representation.
	 */
	public function toString(): string
	{
		if ('number' === $this->unit) {
			return (string) $this->value;
		}

		return $this->value.$this->unit;
	}

	/**
	 * Check if this value is valid.
	 */
	public function isValid(): bool
	{
		return is_finite($this->value) && !empty($this->unit);
	}

	/**
	 * Clone this value.
	 */
	public function clone(): CSSStyleValue
	{
		return new CSSUnitValue($this->value, $this->unit);
	}

	/**
	 * Convert to a different unit.
	 */
	public function to(string $targetUnit): ?CSSUnitValue
	{
		// Basic unit conversion (can be enhanced with proper conversion factors)
		$conversionFactors = [
			'px' => ['em' => 0.0625, 'rem' => 0.0625, '%' => 6.25],
			'em' => ['px' => 16, 'rem' => 1, '%' => 100],
			'rem' => ['px' => 16, 'em' => 1, '%' => 100],
			'%' => ['px' => 0.16, 'em' => 0.01, 'rem' => 0.01],
		];

		if ($this->unit === $targetUnit) {
			return $this->clone();
		}

		if (isset($conversionFactors[$this->unit][$targetUnit])) {
			$newValue = $this->value * $conversionFactors[$this->unit][$targetUnit];

			return new CSSUnitValue($newValue, $targetUnit);
		}

		return null;
	}

	/**
	 * Add another CSSUnitValue.
	 */
	public function add(CSSUnitValue $other): ?CSSUnitValue
	{
		if ($this->unit !== $other->getUnit()) {
			return null;
		}

		return new CSSUnitValue($this->value + $other->getValue(), $this->unit);
	}

	/**
	 * Subtract another CSSUnitValue.
	 */
	public function subtract(CSSUnitValue $other): ?CSSUnitValue
	{
		if ($this->unit !== $other->getUnit()) {
			return null;
		}

		return new CSSUnitValue($this->value - $other->getValue(), $this->unit);
	}

	/**
	 * Multiply by a scalar.
	 */
	public function multiply(float $factor): CSSUnitValue
	{
		return new CSSUnitValue($this->value * $factor, $this->unit);
	}

	/**
	 * Divide by a scalar.
	 */
	public function divide(float $divisor): CSSUnitValue
	{
		if (0.0 === $divisor) {
			throw new \InvalidArgumentException('Cannot divide by zero');
		}

		return new CSSUnitValue($this->value / $divisor, $this->unit);
	}
}
