<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm;

use BadMethodCallException;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

abstract class CSS {

	const array UNIT_MAP = [
		'percent'			=> '%',
		'number'			=> '',
		'flex'				=> 'fr'
	];

	public static function __callStatic(string $name, array $arguments): CSSUnitValue
    {
		$lowerName = self::translateUnit(strtolower($name));
		$unit = CSSUnitEnum::tryFrom($lowerName) ?? CSSUnitEnum::tryFrom($name);

		if ($unit === null) {
			throw new BadMethodCallException('Static method ' . $name . ' does not exist.');
		}
		if (!isset($arguments[0]) || !is_numeric($arguments[0]) || !is_string($arguments[0])) {
            throw new InvalidArgumentException('Static method ' . $name . ' requires 1 numeric string parameter.');
        }
        return new CSSUnitValue($arguments[0], $unit);
    }

	public static function translateUnit(string $name): string {
		return self::UNIT_MAP[$name] ?? $name;
	}
}
