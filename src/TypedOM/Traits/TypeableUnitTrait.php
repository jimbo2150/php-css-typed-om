<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use Exception;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;

/**
 * Trait for simple value classes (color, keyword, image).
 * 
 * Provides common functionality for simple CSS values that don't need complex operations.
 */
trait TypeableUnitTrait
{
	protected CSSUnitEnum $unitObj;

    public string $unit {
		get {
			return $this->unitObj->toString();
		}
	}

	protected function setUnit(string|CSSUnitEnum $unit) {
		if(is_string($unit)) {
			$unit = CSSUnitEnum::from($unit);
		}
		$this->unitObj = $unit;
	}

	public function type(): string {
		$type = $this->unitObj->type();
		if(!$type) {
			throw new Exception('Unknown unit type.');
		}
		return $type->value;
	}
}