<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Base class for CSS Typed OM transform component functions.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSTransformComponent
 */
abstract class CSSTransformComponent extends CSSStyleValue
{
	/** @var list<float> */
	protected const array IDENTITY_MATRIX = [
		1.0, 0.0, 0.0, 0.0,
		0.0, 1.0, 0.0, 0.0,
		0.0, 0.0, 1.0, 0.0,
		0.0, 0.0, 0.0, 1.0,
	];

	public bool $is2D {
		get => $this->twoDimensional;
		set (bool $value) {
			$this->setIs2D($value);
		}
	}

	/** @param list<CSSStyleValue|string> $values */
	public function __construct(
		protected string $functionName,
		protected array $values,
		protected bool $twoDimensional = true,
	) {
	}

	/** @return list<CSSStyleValue|string> */
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

	public function setIs2D(bool $is2D): void
	{
		$this->twoDimensional = $is2D;
	}

	public function toMatrix(): DOMMatrix
	{
		return DOMMatrix::fromTrustedElements(self::IDENTITY_MATRIX, $this->twoDimensional);
	}

	public function __clone(): void
	{
		$cloned = [];
		foreach ($this->values as $value) {
			$cloned[] = $value instanceof CSSStyleValue ? $value->clone() : $value;
		}
		$this->values = $cloned;
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		$parts = [];
		foreach ($this->values as $value) {
			$parts[] = (string) $value;
		}
		return $this->functionName . '(' . implode(', ', $parts) . ')';
	}

	public function __get(string $name): mixed
	{
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	public function __isset(string $name): bool
	{
		return false;
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	/** @return list<float> */
	public static function identityMatrix(): array
	{
		return self::IDENTITY_MATRIX;
	}

	/** @return list<float> */
	protected static function matrix2d(float $a, float $b, float $c, float $d, float $e, float $f): array
	{
		return [$a, $b, 0.0, 0.0, $c, $d, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, $e, $f, 0.0, 1.0];
	}

	/**
	 * @param list<float> $left
	 * @param list<float> $right
	 * @return list<float>
	 */
	public static function multiplyMatrices(array $left, array $right): array
	{
		return DOMMatrixReadOnly::multiplyElements($left, $right);
	}

	/** @param list<string> $expectedCategories */
	protected static function numericValue(
		mixed $value,
		string $defaultUnit = 'number',
		array $expectedCategories = [],
		?CSSParserOptions $options = null,
	): CSSNumericValue {
		if ($value instanceof CSSNumericValue) {
			$numeric = $value;
		} elseif ($value instanceof CSSStyleValue) {
			throw new InvalidArgumentException('Transform component arguments must be numeric values.');
		} elseif (is_int($value) || is_float($value)) {
			$numeric = new CSSUnitValue((float) $value, $defaultUnit);
		} elseif (is_string($value)) {
			$numeric = CSSNumericValue::parse($value, null, $options);
		} else {
			throw new InvalidArgumentException('Transform component arguments must be numeric values.');
		}

		if ($expectedCategories !== []) {
			$type = $numeric->getNumericTypeInternal();
			$category = $numeric->getNumericCategory();
			$categoryValid = in_array($category, $expectedCategories, true);
			$hintValid = $type->percentHint === null
				|| (in_array('percent', $expectedCategories, true) && in_array($type->percentHint, $expectedCategories, true));
			if (!$categoryValid || !$hintValid) {
				throw new InvalidArgumentException('Transform component argument has an incompatible numeric type.');
			}
		}

		return $numeric;
	}

	protected static function scalar(CSSNumericValue $value, string $expectedType): float
	{
		if (!$value instanceof CSSUnitValue) {
			throw new InvalidArgumentException('Matrix conversion requires concrete numeric values.');
		}
		$unit = $value->unit;
		$type = $value->unitType();
		$compatible = $expectedType === 'number'
			? $type === null && ($unit === '' || $unit === 'number')
			: $type === $expectedType;
		if (!$compatible) {
			throw new InvalidArgumentException('Transform component value has an incompatible unit.');
		}

		if ($expectedType === 'length' && $unit !== 'px') {
			$result = $value->to('px')->value;
		} elseif ($expectedType === 'angle') {
			$result = $value->value * match (strtolower($unit)) {
				'rad' => 1.0,
				'grad' => M_PI / 200.0,
				'turn' => 2.0 * M_PI,
				'deg' => M_PI / 180.0,
				default => throw new InvalidArgumentException('Expected an angle unit.'),
			};
		} else {
			$result = $value->value;
		}

		if (!is_finite($result)) {
			throw new InvalidArgumentException('Transform component value must be finite.');
		}

		return $result;
	}
}
