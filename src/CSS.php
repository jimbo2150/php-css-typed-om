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

	public static function escape(string $value): string {
		if (str_starts_with($value, '--')) {
			$result = '--';
			$len = mb_strlen($value);
			for ($i = 2; $i < $len; $i++) {
				$c = mb_substr($value, $i, 1);
				$code = mb_ord($c);
				if ($code >= 0x80) {
					$hex = strtolower(dechex($code));
					$result .= '\\' . $hex . ' ';
				} elseif ($code == 0) {
					$result .= '\\0';
				} elseif ($code >= 1 && $code <= 0x1F || $code == 0x7F) {
					$hex = strtolower(dechex($code));
					$result .= '\\' . $hex . ' ';
				} elseif (!preg_match('/[a-zA-Z0-9_-]/', $c)) {
					$result .= '\\' . $c;
				} else {
					$result .= $c;
				}
			}
			return $result;
		} else {
			$result = '';
			$first = true;
			$len = mb_strlen($value);
			for ($i = 0; $i < $len; $i++) {
				$c = mb_substr($value, $i, 1);
				if (preg_match('/\p{L}/u', $c) || $c === '_' || $c === '-' || (!$first && preg_match('/\p{N}/u', $c))) {
					$result .= $c;
					$first = false;
				} else {
					$code = mb_ord($c);
					if ($code == 0) {
						$result .= '\\0';
					} elseif ($code >= 1 && $code <= 0x1F || $code >= 0x7F || $code >= 0x20 && $code <= 0x7E) {
						$hex = strtolower(dechex($code));
						$result .= '\\' . $hex . ' ';
					} else {
						$result .= '\\' . $c;
					}
					$first = false;
				}
			}
			return $result;
		}
	}
}
