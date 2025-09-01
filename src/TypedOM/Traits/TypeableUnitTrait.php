<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use Exception;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;

/**
 * Trait for simple value classes (color, keyword, image).
 * 
 * Provides common functionality for simple CSS values that don't need complex operations.
 */
trait TypeableUnitTrait
{

	/** @var CSSUnitEnum|null The unit enum object */
	protected ?CSSUnitEnum $unitObj = null;
    public string $unit {
		get {
			return $this->unitObj ? $this->unitObj->toString() : '';
		}
	}

	/**
	 * Set the unit.
	 *
	 * @param string|CSSUnitEnum $unit The unit to set
	 */
	protected function setUnit(string|CSSUnitEnum $unit) {
		if(is_string($unit)) {
			$unit = CSSUnitEnum::from(CSS::translateUnit($unit));
		}
		$this->unitObj = $unit;
	}

	/**
	 * Get the type of the unit.
	 *
	 * @return string The unit type
	 * @throws Exception If no unit is set or unknown type
	 */
	public function type(): string {
		if (!$this->unitObj) {
			throw new Exception('No unit set.');
		}
		$type = $this->unitObj->type();
		if(!$type) {
			throw new Exception('Unknown unit type.');
		}
		return $type->value;
	}
}