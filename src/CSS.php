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
 * @method static CSSUnitValue cap(string|int|float $value) Create a cap unit value
 * @method static CSSUnitValue rcap(string|int|float $value) Create a rcap unit value
 * @method static CSSUnitValue cqb(string|int|float $value) Create a cqb unit value
 * @method static CSSUnitValue cqh(string|int|float $value) Create a cqh unit value
 * @method static CSSUnitValue cqw(string|int|float $value) Create a cqw unit value
 * @method static CSSUnitValue cqi(string|int|float $value) Create a cqi unit value
 * @method static CSSUnitValue cqmax(string|int|float $value) Create a cqmax unit value
 * @method static CSSUnitValue cqmin(string|int|float $value) Create a cqmin unit value
 * @method static CSSUnitValue dvb(string|int|float $value) Create a dvb unit value
 * @method static CSSUnitValue dvh(string|int|float $value) Create a dvh unit value
 * @method static CSSUnitValue dvi(string|int|float $value) Create a dvi unit value
 * @method static CSSUnitValue dvmax(string|int|float $value) Create a dvmax unit value
 * @method static CSSUnitValue dvmin(string|int|float $value) Create a dvmin unit value
 * @method static CSSUnitValue dvw(string|int|float $value) Create a dvw unit value
 * @method static CSSUnitValue lvb(string|int|float $value) Create a lvb unit value
 * @method static CSSUnitValue lvh(string|int|float $value) Create a lvh unit value
 * @method static CSSUnitValue lvi(string|int|float $value) Create a lvi unit value
 * @method static CSSUnitValue lvmax(string|int|float $value) Create a lvmax unit value
 * @method static CSSUnitValue lvmin(string|int|float $value) Create a lvmin unit value
 * @method static CSSUnitValue lvw(string|int|float $value) Create a lvw unit value
 * @method static CSSUnitValue lch(string|int|float $value) Create a lch unit value
 * @method static CSSUnitValue rex(string|int|float $value) Create a rex unit value
 * @method static CSSUnitValue ric(string|int|float $value) Create a ric unit value
 * @method static CSSUnitValue svb(string|int|float $value) Create a svb unit value
 * @method static CSSUnitValue svh(string|int|float $value) Create a svh unit value
 * @method static CSSUnitValue svi(string|int|float $value) Create a svi unit value
 * @method static CSSUnitValue svmax(string|int|float $value) Create a svmax unit value
 * @method static CSSUnitValue svmin(string|int|float $value) Create a svmin unit value
 * @method static CSSUnitValue svw(string|int|float $value) Create a svw unit value
 */
abstract class CSS {

	/** @var array<string, string> Map of unit names to symbols */
	const array UNIT_MAP = [
		'percent'			=> '%',
		'number'			=> '',
		'flex'				=> 'fr'
	];

	/**
	 * Magic method for static calls to create CSS unit values.
	 *
	 * @param string $name The unit name
	 * @param array $arguments The value
	 * @return CSSUnitValue The created unit value
	 * @throws BadMethodCallException If the unit is not supported
	 * @throws InvalidArgumentException If the value is invalid
	 */
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

	/**
	 * Translates a unit name to its corresponding symbol if mapped, otherwise returns the name unchanged.
	 *
	 * @param string $name The unit name to translate
	 * @return string The translated unit symbol or the original name
	 */
	public static function translateUnit(string $name): string {
		return self::UNIT_MAP[$name] ?? $name;
	}

	/**
	 * Escapes a value for safe inclusion in CSS strings, handling special characters and null bytes.
	 *
	 * @param string|int|float|null $value The value to escape
	 * @return string The escaped value
	 */
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

	/**
	 * Checks if a character is null or contains a null byte.
	 *
	 * @param string|null $char The character to check
	 * @return bool True if the character is null or contains null byte, false otherwise
	 */
	private static function __charIsNull(string|null $char): bool {
		return $char === null || preg_match('/\x00/u', $char) === 1;
	}
}
