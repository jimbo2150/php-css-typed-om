<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Traits;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use LogicException;

/**
 * Trait providing unit storage, normalization, and category lookup via {@see CSSUnitEnum}.
 */
trait TypeableUnitTrait
{

	/** @var CSSUnitEnum|null The unit enum object */
	protected ?CSSUnitEnum $unitObj = null;
	public string $unit {
		get {
			return $this->unitObj ? $this->unitObj->toUnitName() : '';
		}
	}

	/** @internal */
	public function getUnitObjInternal(): ?CSSUnitEnum
	{
		return $this->unitObj;
	}

	/**
	 * Set the unit.
	 *
	 * @param string|CSSUnitEnum $unit The unit to set
	 */
	protected function setUnit(string|CSSUnitEnum $unit): void
	{
		if (is_string($unit)) {
			$resolved = CSSUnitEnum::tryFromUnitName($unit);
			if ($resolved === null) {
				$symbol = CSS::translateUnit(strtolower($unit));
				throw new InvalidArgumentException('Unsupported CSS unit: ' . $symbol);
			}
			$unit = $resolved;
		}
		$this->unitObj = $unit;
	}

	/**
	 * Get the category name of the unit.
	 *
	 * @return string|null The unit type
	 * @throws LogicException If no unit is set
	 */
	public function type(): ?string
	{
		if (!$this->unitObj) {
			throw new LogicException('No unit set.');
		}
		$type = $this->unitObj->type();
		return $type?->value;
	}
}
