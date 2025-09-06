<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use AllowDynamicProperties;
use Exception;
use TypeError;

/**
 * Represents the type of a CSS numeric value.
 */
#[AllowDynamicProperties()]
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
		$type = CSSUnitTypeEnum::from($key);
		$typeStr = $type->verifyValue($value);
		if($typeStr instanceof Exception) {
			throw $typeStr;
		}
		if(!settype($value, $typeStr)) {
			 // @codeCoverageIgnoreStart
			throw new TypeError('Could not change type of value to ' . (string) $typeStr);
			 // @codeCoverageIgnoreEnd
		}
		$this->{$type->value} = $value;
	}
}
