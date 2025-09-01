<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

/**
 * Represents the type of a CSS numeric value.
 */
class CSSNumericType
{
	/**
	 * Properties:
	 * length, angle, time, frequency, resolution, flex, percent, or percentHint
	 *
	 * @var array<string> The allowed properties
	 */

	protected const allowedProperties = [
		'length',
		'angle',
		'time',
		'frequency',
		'resolution',
		'flex',
		'percent',
		'percentHint'
	];

	/**
	 * Set a property value.
	 *
	 * @param string $key The property name
	 * @param string|int $value The property value
	 * @throws \InvalidArgumentException If property is not allowed or value is invalid
	 */
	public function __set(string $key, string|int $value) {
		if(!in_array($key, static::allowedProperties, true)) {
			throw new \InvalidArgumentException("Property '{$key}' is not allowed.");
		} else {
			if($key !== 'percentHint' && !is_int($value)) {
				throw new \InvalidArgumentException("Value for '{$key}' must be an integer.");
			} else if(!is_string($value)) {
				throw new \InvalidArgumentException("Value for '{$key}' must be a string.");
			}
		}
		$this->{$key} = $value;
	}
}
