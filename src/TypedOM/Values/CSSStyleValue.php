<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\SimpleValueTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Base class for CSS Typed OM values
 * Represents a CSS value that can be manipulated through the Typed OM API.
 */
abstract class CSSStyleValue
{

	public function __construct()
	{
		
	}

	/**
	 * Parse a CSS value and return appropriate CSSStyleValue instance.
	 * This is a simplified parser and should be replaced with a more robust solution.
	 */
	private static function createFromCssText(string $cssText): CSSStyleValue
	{
		$cssText = trim($cssText);

		try {
			return CSSColorValue::parse($cssText);
		} catch (\InvalidArgumentException $e) {
			// Not a color, try next
		}

		try {
			return CSSNumericValue::parse($cssText);
		} catch (\InvalidArgumentException $e) {
			// Not a numeric value, try next
		}

		// If it's not a color or a numeric value, it's a keyword
		// This assumes that any remaining string is a keyword.
		// More robust parsing would involve checking for valid keyword characters.
		return new CSSKeywordValue($cssText);
	}

	public static function parse(string $cssText): CSSStyleValue
	{
		return self::createFromCssText($cssText);
	}

	/**
	 * Parses a string containing one or more CSS values and returns an array of CSSStyleValue objects.
	 * This is a simplified implementation and may not handle all complex CSS value strings.
	 *
	 * @param string $cssText the CSS value string to parse
	 *
	 * @return CSSStyleValue[] an array of CSSStyleValue objects
	 */
	public static function parseAll(string $cssText): array
	{
		$cssText = trim($cssText);
		if (empty($cssText)) {
			return [];
		}

		$values = [];
		// Simple split by space. This will need to be more robust for complex values.
		$parts = preg_split('/\s+/', $cssText, -1, PREG_SPLIT_NO_EMPTY);

		foreach ($parts as $part) {
			$values[] = self::createFromCssText($part);
		}

		return $values;
	}

	/**
	 * Clone this value.
	 */
	abstract public function clone(): static;
}
