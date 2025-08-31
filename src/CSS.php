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

	public static function escape(string|int|float|null $value): string {
		if($value === null) {
			$value = "\x00";
		}
		$value = (string) $value;
		$output = '';
		$charArray = preg_split('//u', $value, -1, PREG_SPLIT_NO_EMPTY);
		foreach($charArray as $index => $char) {
			if(self::__charIsNull($char)) {
				$output = preg_replace('/\x00/u', "\u{FFFD}", $char);
			} else if(
				(preg_match('/[\x01-\x1f\x7f]/u', $char) === 1) ||
				($index === 0 && preg_match('/[0-9]/u', $char) === 1) ||
				(
					$index === 1 &&
					preg_match('/[0-9]/u', $char) === 1 &&
					$charArray[0] === '-'
				)
			) {
				$output .= '\\' . dechex(mb_ord($char)) . ' ';
			} else if(
				($index === 1 && $char === '-' && count($charArray) < 3)
			) {
				$output .= '\\' . $char;
			} else if(
				(preg_match('/([\x80-\x{10FFFF}]|[\-\_0-9a-zA-Z])/u', $char) === 1)
			) {
				$output .= $char;
			} else {
				$output .= '\\' . $char;
			}
		}
		// Escape escape character
		return preg_replace('/\\\\(\\\\+)?/u', '\\\\\\\${1}', $output);
	}

	private static function __charIsNull(string|null $char): bool {
		return $char === null || preg_match('/\x00/u', $char) === 1;
	}
}
