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

	/** @var array<string, int|string> */
	private array $dimensions = [];

	/** @param array<string, int> $dimensions */
	public function __construct(array $dimensions = [])
	{
		foreach ($dimensions as $dimension => $exponent) {
			$this->__set($dimension, $exponent);
		}
	}

	/** @param array<string, int> $dimensions */
	public static function fromDimensions(array $dimensions): self
	{
		return new self($dimensions);
	}

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
		$this->dimensions[$type->value] = $value;
	}

	public function __get(string $key): int|string
	{
		$type = CSSUnitTypeEnum::from($key);
		return $this->dimensions[$type->value] ?? 0;
	}

	/** @return array<string, int|string> */
	public function toArray(): array
	{
		return $this->dimensions;
	}

	public function multiply(self $other): self
	{
		$dimensions = $this->dimensions;
		foreach ($other->dimensions as $dimension => $exponent) {
			$dimensions[$dimension] = (int) ($dimensions[$dimension] ?? 0) + (int) $exponent;
		}
		return new self(array_filter($dimensions, static fn (int|string $exponent): bool => (int) $exponent !== 0));
	}

	public function invert(): self
	{
		$dimensions = [];
		foreach ($this->dimensions as $dimension => $exponent) {
			$dimensions[$dimension] = -(int) $exponent;
		}
		return new self($dimensions);
	}

	public function equals(self $other): bool
	{
		$left = $this->dimensions;
		$right = $other->dimensions;
		foreach (self::allowedProperties as $dimension) {
			if ((int) ($left[$dimension] ?? 0) !== (int) ($right[$dimension] ?? 0)) {
				return false;
			}
		}
		return true;
	}
}
