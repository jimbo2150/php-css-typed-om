<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric;

use Exception;
use InvalidArgumentException;
use TypeError;

/**
 * Represents the dimensional exponent map of a CSS numeric value.
 *
 * @property int $length
 * @property int $angle
 * @property int $time
 * @property int $frequency
 * @property int $resolution
 * @property int $flex
 * @property int $percent
 * @property ?string $percentHint
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNumericType
 */
class CSSNumericType
{
	/** @var list<string> */
	protected const array allowedProperties = [
		'length',
		'angle',
		'time',
		'frequency',
		'resolution',
		'flex',
		'percent',
		'percentHint',
	];

	/** @var array<string, int|string> */
	private array $dimensions = [];

	/**
	 * @param array<string, int|string> $dimensions
	 * @throws InvalidArgumentException When any dimension name or value is invalid
	 */
	public function __construct(array $dimensions = [])
	{
		foreach ($dimensions as $dimension => $exponent) {
			$this->__set($dimension, $exponent);
		}
	}

	/** @param array<string, int|string> $dimensions */
	public static function fromDimensions(array $dimensions): self
	{
		foreach ($dimensions as $dimension => $exponent) {
			if ($dimension !== 'percentHint' && !is_int($exponent)) {
				throw new InvalidArgumentException(sprintf('Dimension exponent for "%s" must be an integer.', $dimension));
			}
		}
		return new self($dimensions);
	}

	/**
	 * @internal Fast path for already-validated dimension maps.
	 * @param array<string, int|string> $dimensions
	 */
	public static function fromValidatedDimensions(array $dimensions): self
	{
		$type = new self();
		$type->dimensions = $dimensions;

		return $type;
	}

	/**
	 * Sets a numeric type dimension exponent or percentHint.
	 *
	 * @throws \ValueError When $key is not a valid numeric dimension
	 * @throws InvalidArgumentException When $value is invalid for $key
	 */
	public function __set(string $key, string|int $value): void
	{
		$type = CSSUnitTypeEnum::from($key);
		$typeStr = $type->verifyValue($value);
		if ($typeStr instanceof Exception) {
			throw $typeStr;
		}
		if (!settype($value, $typeStr)) {
			// @codeCoverageIgnoreStart
			throw new TypeError('Could not change type of value to ' . $typeStr);
			// @codeCoverageIgnoreEnd
		}
		$this->dimensions[$type->value] = $value;
	}

	public function __get(string $key): int|string|null
	{
		$type = CSSUnitTypeEnum::from($key);
		if ($type === CSSUnitTypeEnum::PERCENT_HINT) {
			return $this->dimensions['percentHint'] ?? null;
		}

		return $this->dimensions[$type->value] ?? 0;
	}

	public function __isset(string $key): bool
	{
		$type = CSSUnitTypeEnum::tryFrom($key);
		return $type !== null && isset($this->dimensions[$type->value]);
	}

	/** @return array<string, int|string> */
	public function toArray(): array
	{
		return $this->dimensions;
	}

	public function applyPercentHint(string $hint): self
	{
		$hintType = CSSUnitTypeEnum::tryFrom($hint);
		if ($hintType === null || $hintType === CSSUnitTypeEnum::PERCENT_HINT) {
			throw new InvalidArgumentException(sprintf('Invalid percent hint dimension "%s".', $hint));
		}

		$dimensions = $this->dimensions;
		if ($hintType !== CSSUnitTypeEnum::PERCENT && isset($dimensions['percent'])) {
			$newExp = (int) ($dimensions[$hint] ?? 0) + (int) $dimensions['percent'];
			unset($dimensions['percent']);
			if ($newExp === 0) {
				unset($dimensions[$hint]);
			} else {
				$dimensions[$hint] = $newExp;
			}
		}
		$dimensions['percentHint'] = $hint;

		return self::fromValidatedDimensions($dimensions);
	}

	public function multiply(self $other): self
	{
		$leftHint = $this->dimensions['percentHint'] ?? null;
		$rightHint = $other->dimensions['percentHint'] ?? null;
		if ($leftHint !== null && $rightHint !== null && $leftHint !== $rightHint) {
			throw new InvalidArgumentException('Cannot multiply numeric types with conflicting percent hints.');
		}
		$hint = $leftHint ?? $rightHint;
		$left = ($leftHint === null && $hint !== null) ? $this->applyPercentHint((string) $hint) : $this;
		$right = ($rightHint === null && $hint !== null) ? $other->applyPercentHint((string) $hint) : $other;

		$dimensions = [];
		foreach (self::allowedProperties as $dimension) {
			if ($dimension === 'percentHint') {
				continue;
			}
			$sum = (int) ($left->dimensions[$dimension] ?? 0) + (int) ($right->dimensions[$dimension] ?? 0);
			if ($sum !== 0) {
				$dimensions[$dimension] = $sum;
			}
		}

		if ($hint !== null) {
			$dimensions['percentHint'] = $hint;
		}

		return self::fromValidatedDimensions($dimensions);
	}

	public function invert(): self
	{
		$dimensions = [];
		foreach ($this->dimensions as $dimension => $exponent) {
			if ($dimension === 'percentHint') {
				$dimensions['percentHint'] = $exponent;
				continue;
			}
			$intVal = -(int) $exponent;
			if ($intVal !== 0) {
				$dimensions[$dimension] = $intVal;
			}
		}

		return self::fromValidatedDimensions($dimensions);
	}

	public function equals(self $other): bool
	{
		if ($this === $other) {
			return true;
		}
		foreach (self::allowedProperties as $dimension) {
			if ($dimension === 'percentHint') {
				if (($this->dimensions['percentHint'] ?? null) !== ($other->dimensions['percentHint'] ?? null)) {
					return false;
				}
				continue;
			}
			if ((int) ($this->dimensions[$dimension] ?? 0) !== (int) ($other->dimensions[$dimension] ?? 0)) {
				return false;
			}
		}

		return true;
	}
}
