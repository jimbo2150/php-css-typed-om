<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm;

use BadMethodCallException;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * CSS utility class for creating typed CSS unit values.
 *
 * @method static CSSUnitValue percent(string|int|float $value) Create a percentage unit value
 * @method static CSSUnitValue number(string|int|float $value) Create a dimensionless number unit value
 * @method static CSSUnitValue flex(string|int|float $value) Create a flex unit value
 * @method static CSSUnitValue em(string|int|float $value) Create an em unit value
 * @method static CSSUnitValue ex(string|int|float $value) Create an ex unit value
 * @method static CSSUnitValue ch(string|int|float $value) Create a ch unit value
 * @method static CSSUnitValue ic(string|int|float $value) Create an ic unit value
 * @method static CSSUnitValue rem(string|int|float $value) Create a rem unit value
 * @method static CSSUnitValue lh(string|int|float $value) Create an lh unit value
 * @method static CSSUnitValue rlh(string|int|float $value) Create an rlh unit value
 * @method static CSSUnitValue vw(string|int|float $value) Create a vw unit value
 * @method static CSSUnitValue vh(string|int|float $value) Create a vh unit value
 * @method static CSSUnitValue vi(string|int|float $value) Create a vi unit value
 * @method static CSSUnitValue vb(string|int|float $value) Create a vb unit value
 * @method static CSSUnitValue vmin(string|int|float $value) Create a vmin unit value
 * @method static CSSUnitValue vmax(string|int|float $value) Create a vmax unit value
 * @method static CSSUnitValue cm(string|int|float $value) Create a cm unit value
 * @method static CSSUnitValue mm(string|int|float $value) Create a mm unit value
 * @method static CSSUnitValue Q(string|int|float $value) Create a Q unit value
 * @method static CSSUnitValue in(string|int|float $value) Create an in unit value
 * @method static CSSUnitValue pt(string|int|float $value) Create a pt unit value
 * @method static CSSUnitValue pc(string|int|float $value) Create a pc unit value
 * @method static CSSUnitValue px(string|int|float $value) Create a px unit value
 * @method static CSSUnitValue deg(string|int|float $value) Create a deg unit value
 * @method static CSSUnitValue grad(string|int|float $value) Create a grad unit value
 * @method static CSSUnitValue rad(string|int|float $value) Create a rad unit value
 * @method static CSSUnitValue turn(string|int|float $value) Create a turn unit value
 * @method static CSSUnitValue s(string|int|float $value) Create an s unit value
 * @method static CSSUnitValue ms(string|int|float $value) Create an ms unit value
 * @method static CSSUnitValue hz(string|int|float $value) Create an Hz unit value
 * @method static CSSUnitValue khz(string|int|float $value) Create a kHz unit value
 * @method static CSSUnitValue dpi(string|int|float $value) Create a dpi unit value
 * @method static CSSUnitValue dpcm(string|int|float $value) Create a dpcm unit value
 * @method static CSSUnitValue dppx(string|int|float $value) Create a dppx unit value
 * @method static CSSUnitValue fr(string|int|float $value) Create a fr unit value
 */
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
