<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;

/**
 * Trait for CSS math operations (sum, product, etc.).
 * 
 * Provides common mathematical operations for CSS numeric values.
 */
trait MathOperationsTrait
{
    /** @var float The numeric value */
    private float $value;

    /** @var string The unit */
    private string $unit;

    /**
     * Initialize math operations.
     * 
     * @param float $value The numeric value
     * @param string $unit The unit
     */
    private function initializeMathOperations(float $value, string $unit): void
    {
        $this->value = $value;
        $this->unit = $unit;
    }

    /**
     * Add another CSSUnitValue.
     * 
     * @param CSSUnitValue $other The value to add
     * @return CSSUnitValue|null New value or null if units are incompatible
     */
    public function add(CSSUnitValue $other): ?CSSUnitValue
    {
        if ($this->unit !== $other->getUnit()) {
            return null;
        }

        return new CSSUnitValue($this->value + $other->getNumericValue(), $this->unit);
    }

    /**
     * Subtract another CSSUnitValue.
     * 
     * @param CSSUnitValue $other The value to subtract
     * @return CSSUnitValue|null New value or null if units are incompatible
     */
    public function subtract(CSSUnitValue $other): ?CSSUnitValue
    {
        if ($this->unit !== $other->getUnit()) {
            return null;
        }

        return new CSSUnitValue($this->value - $other->getNumericValue(), $this->unit);
    }

    /**
     * Multiply by a scalar.
     * 
     * @param float $factor The multiplication factor
     * @return CSSUnitValue New multiplied value
     */
    public function multiply(float $factor): CSSUnitValue
    {
        return new CSSUnitValue($this->value * $factor, $this->unit);
    }

    /**
     * Divide by a scalar.
     * 
     * @param float $divisor The division divisor
     * @return CSSUnitValue New divided value
     * @throws \InvalidArgumentException When dividing by zero
     */
    public function divide(float $divisor): CSSUnitValue
    {
        if (0.0 === $divisor) {
            throw new \InvalidArgumentException('Cannot divide by zero');
        }

        return new CSSUnitValue($this->value / $divisor, $this->unit);
    }

    /**
     * Convert to a different unit.
     * 
     * @param string $targetUnit The target unit
     * @return CSSUnitValue|null New value or null if conversion is not possible
     */
    public function to(string $targetUnit): ?CSSUnitValue
    {
        // Basic unit conversion factors
        $conversionFactors = [
            'px' => [
                'em' => 0.0625, 'rem' => 0.0625, '%' => 6.25, 'cm' => 0.026458, 'mm' => 0.264583,
                'in' => 0.010417, 'pt' => 0.75, 'pc' => 0.0625
            ],
            'em' => [
                'px' => 16, 'rem' => 1, '%' => 100, 'cm' => 0.423333, 'mm' => 4.233333,
                'in' => 0.166667, 'pt' => 12, 'pc' => 1
            ],
            'rem' => [
                'px' => 16, 'em' => 1, '%' => 100, 'cm' => 0.423333, 'mm' => 4.233333,
                'in' => 0.166667, 'pt' => 12, 'pc' => 1
            ],
            '%' => [
                'px' => 0.16, 'em' => 0.01, 'rem' => 0.01, 'cm' => 0.004233, 'mm' => 0.042333,
                'in' => 0.001667, 'pt' => 0.12, 'pc' => 0.01
            ],
            'deg' => [
                'rad' => 0.017453, 'grad' => 1.111111, 'turn' => 0.002778
            ],
            'rad' => [
                'deg' => 57.29578, 'grad' => 63.661977, 'turn' => 0.159155
            ],
        ];

        if ($this->unit === $targetUnit) {
            return new CSSUnitValue($this->value, $this->unit);
        }

        if (isset($conversionFactors[$this->unit][$targetUnit])) {
            $newValue = $this->value * $conversionFactors[$this->unit][$targetUnit];
            return new CSSUnitValue($newValue, $targetUnit);
        }

        return null;
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
    }

    /**
     * Check if this value is valid.
     */
    public function isValid(): bool
    {
        return is_finite($this->value) && !empty($this->unit);
    }
}