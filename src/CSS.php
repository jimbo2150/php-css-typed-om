<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm;

use BadMethodCallException;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

abstract class CSS {
	public static function __callStatic(string $name, array $arguments): CSSUnitValue
    {
		$lowerName = self::_translateUnitMethod(strtolower($name));
		$unit = CSSUnitEnum::tryFrom($lowerName) ?? CSSUnitEnum::tryFrom($name);

		if ($unit === null) {
			throw new BadMethodCallException('Static method ' . $name . ' does not exist.');
		}
		if (!isset($arguments[0]) || !is_numeric($arguments[0])) {
            throw new InvalidArgumentException('Static method ' . $name . ' requires 1 numeric parameter.');
        }
        return new CSSUnitValue($arguments[0], $unit);
    }

	private static function _translateUnitMethod(string $name): string {
		return match($name) {
			'percent' => '%',
			'number' => '',
			default => $name
		};
	}
}
