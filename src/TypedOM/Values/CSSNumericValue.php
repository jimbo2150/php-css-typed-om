<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\Process\CSSCalcParser;

/**
 * Represents a CSS value that can be expressed as a number, or a number and a unit.
 * It is the base class for CSSUnitValue and other future math-related CSS classes.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericValue
 */
abstract class CSSNumericValue extends CSSStyleValue
{
	/**
	 * The parse() method of the CSSNumericValue interface creates a new CSSUnitValue object from a CSS numeric value.
	 *
	 * @param string $cssText the CSS text to parse
	 *
	 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericValue/parse
	 */
	public static function parse(string $cssText): self
	{
		$cssText = trim($cssText);

		// Handle calc() expressions
		if (str_starts_with($cssText, 'calc(') && str_ends_with($cssText, ')')) {
			return CSSCalcParser::parse($cssText);
		}

		// This is a simplified parser. A real one would need a proper tokenizer
		// and handle calc() expressions.
		if (preg_match('/^(-?\d*\.?\d+)([a-zA-Z%]*)$/', $cssText, $matches)) {
			$value = (float) $matches[1];
			$unit = $matches[2];

			if (empty($unit)) {
				$unit = 'number';
			}

			return new CSSUnitValue($value, $unit);
		}

		throw new \InvalidArgumentException('Invalid CSS numeric value: '.$cssText);
	}

	/**
	 * Creates a new CSSUnitValue object from a CSS numeric value.
	 *
	 * @param string $cssText the CSS text to parse
	 */
	public static function from(string $cssText): self
	{
		return self::parse($cssText);
	}

	/**
	 * Adds all the values in the values list and returns the result.
	 *
	 * @param CSSNumericValue[] $values
	 */
	public static function toSum(...$values): CSSNumericValue
	{
		if (empty($values)) {
			return new CSSUnitValue(0, 'number');
		}

		$sum = 0;
		$unit = null;
		
		foreach ($values as $value) {
			if ($value instanceof CSSUnitValue) {
				if ($unit === null) {
					$unit = $value->unit;
				} elseif ($unit !== $value->unit) {
					throw new \InvalidArgumentException('All values must have the same unit');
				}
				$sum += $value->value;
			}
		}

		return new CSSUnitValue($sum, $unit ?? 'number');
	}

	/**
	 * Magic getter for property access.
	 */
	public function __get(string $name): mixed
	{
		return match ($name) {
			'type' => $this->type,
			default => throw new \Error("Undefined property: {$name}"),
		};
	}
}
