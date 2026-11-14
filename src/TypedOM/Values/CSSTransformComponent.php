<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

abstract class CSSTransformComponent extends CSSStyleValue
{
	/** @param list<CSSStyleValue|string> $values */
	public function __construct(
		protected readonly string $functionName,
		protected readonly array $values,
		protected readonly bool $twoDimensional = true,
	) {
	}

	public function getValues(): array
	{
		return $this->values;
	}

	public function getFunctionName(): string
	{
		return $this->functionName;
	}

	public function is2D(): bool
	{
		return $this->twoDimensional;
	}

	/** @return list<float> 4x4 column-major transformation matrix */
	public function toMatrix(): array
	{
		return self::identityMatrix();
	}

	public function clone(): static
	{
		$values = array_map(
			static fn (CSSStyleValue|string $value): CSSStyleValue|string => $value instanceof CSSStyleValue ? $value->clone() : $value,
			$this->values,
		);
		return new static($this->functionName, $values, $this->twoDimensional);
	}

	public function __toString(): string
	{
		$values = array_map(static fn (CSSStyleValue|string $value): string => (string) $value, $this->values);
		return $this->functionName . '(' . implode(', ', $values) . ')';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'is2D' => $this->is2D(),
			default => throw new \Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	protected static function numericValue(mixed $value, string $defaultUnit = 'number', array $expectedCategories = []): CSSNumericValue
	{
		if ($value instanceof CSSNumericValue) {
			$numeric = $value;
		} elseif ($value instanceof CSSStyleValue) {
			throw new InvalidArgumentException('Transform component arguments must be numeric values.');
		} elseif (is_int($value) || is_float($value)) {
			$numeric = new CSSUnitValue((float) $value, $defaultUnit);
		} elseif (is_string($value)) {
			$numeric = CSSNumericValue::parse($value);
		} else {
			throw new InvalidArgumentException('Transform component arguments must be numeric values.');
		}
		if ($expectedCategories !== [] && !in_array($numeric->getNumericCategory(), $expectedCategories, true)) {
			throw new InvalidArgumentException('Transform component argument has an incompatible numeric type.');
		}
		return $numeric;
	}

	protected static function scalar(CSSNumericValue $value, string $expectedType): float
	{
		if (!$value instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Matrix conversion requires concrete numeric values.');
		}
		$unit = $value->unit;
		$type = $value->type();
		$compatible = $expectedType === 'number'
			? $type === null && in_array($unit, ['', 'number'], true)
			: $type === $expectedType;
		if (!$compatible) {
			throw new InvalidArgumentException('Transform component value has an incompatible unit.');
		}

		if ($expectedType === 'length' && $unit !== 'px') {
			return $value->to('px')->value;
		}
		if ($expectedType === 'angle') {
			return $value->value * match (strtolower($unit)) {
				'rad' => 1.0,
				'grad' => M_PI / 200,
				'turn' => 2 * M_PI,
				'deg' => M_PI / 180,
				default => throw new InvalidArgumentException('Expected an angle unit.'),
			};
		}
		return $value->value;
	}

	/** @return list<float> */
	public static function identityMatrix(): array
	{
		return [1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0];
	}

	/** @return list<float> */
	protected static function matrix2d(float $a, float $b, float $c, float $d, float $e, float $f): array
	{
		return [$a, $b, 0.0, 0.0, $c, $d, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, $e, $f, 0.0, 1.0];
	}

	/** @param list<float> $left @param list<float> $right @return list<float> */
	public static function multiplyMatrices(array $left, array $right): array
	{
		$result = array_fill(0, 16, 0.0);
		for ($column = 0; $column < 4; $column++) {
			for ($row = 0; $row < 4; $row++) {
				for ($inner = 0; $inner < 4; $inner++) {
					$result[$column * 4 + $row] += $left[$inner * 4 + $row] * $right[$column * 4 + $inner];
				}
			}
		}
		return $result;
	}
}
