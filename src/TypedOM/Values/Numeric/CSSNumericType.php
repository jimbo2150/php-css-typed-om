<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

class CSSNumericType
{
	/**
	 * Properties:
	 * length, angle, time, frequency, resolution, flex, percent, or percentHint
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
