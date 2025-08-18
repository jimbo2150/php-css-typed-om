<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;

/**
 * Trait for CSS transform components with 2D/3D support.
 * 
 * Provides common functionality for transform components like rotate, scale, translate, etc.
 */
trait TransformComponentTrait
{
    /** @var array<string, CSSUnitValue> Component values */
    private array $values = [];

    /**
     * Initialize transform component with values and 2D/3D flag.
     *
     * @param array<string, CSSUnitValue> $values Component values
     * @param bool $is2D Whether this is a 2D transform
     */
    private function initializeTransformComponent(array $values, bool $is2D = true): void
    {
        $this->values = $values;
        $this->is2D = $is2D;
    }

    /**
     * Check if this is a 2D transform.
     */
    public function is2D(): bool
    {
        return $this->is2D;
    }

    /**
     * Get the transform type (e.g., 'rotate', 'scale', 'translate').
     */
    abstract public function getTransformType(): string;

    /**
     * Get component values.
     * 
     * @return array<string, CSSUnitValue>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Get a specific component value.
     * 
     * @param string $name Component name
     * @return CSSUnitValue|null The value or null if not found
     */
    public function getValue(string $name): ?CSSUnitValue
    {
        return $this->values[$name] ?? null;
    }

    /**
     * Set a component value.
     * 
     * @param string $name Component name
     * @param CSSUnitValue $value The value to set
     */
    public function setValue(string $name, CSSUnitValue $value): void
    {
        $this->values[$name] = $value;
    }

    /**
     * Clone all component values.
     * 
     * @return array<string, CSSUnitValue> Cloned values
     */
    protected function cloneValues(): array
    {
        $cloned = [];
        foreach ($this->values as $name => $value) {
            $cloned[$name] = $value->clone();
        }
        return $cloned;
    }

    /**
     * Generate CSS string representation for the transform.
     * 
     * @param string $functionName CSS function name (e.g., 'rotate', 'scale')
     * @param array<string> $valueOrder Order of values in the function
     * @return string CSS string
     */
    protected function toTransformString(string $functionName, array $valueOrder): string
    {
        $values = [];
        foreach ($valueOrder as $name) {
            if (isset($this->values[$name])) {
                $values[] = $this->values[$name]->toString();
            }
        }

        // For 2D transforms, only include 2D values
        if ($this->is2D) {
            $values = array_slice($values, 0, 2);
        }

        return $functionName . '(' . implode(', ', $values) . ')';
    }

    /**
     * Validate that all required values are present.
     * 
     * @param array<string> $required Required value names
     * @throws \InvalidArgumentException When required values are missing
     */
    protected function validateRequiredValues(array $required): void
    {
        foreach ($required as $name) {
            if (!isset($this->values[$name])) {
                throw new \InvalidArgumentException(sprintf(
                    'Required value "%s" is missing for %s transform',
                    $name,
                    $this->getTransformType()
                ));
            }
        }
    }

    /**
     * Check if this transform is valid.
     */
    public function isValid(): bool
    {
        foreach ($this->values as $value) {
            if (!$value->isValid()) {
                return false;
            }
        }
        return true;
    }
}