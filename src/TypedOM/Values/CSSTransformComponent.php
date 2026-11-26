<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathValue;
use LogicException;

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

	/** @internal Mutates $matrix in place for CSSTransformValue::toMatrix(). */
	public function applyToMatrixInternal(DOMMatrix $matrix): void
	{
		$matrix->multiplySelf($this->toMatrix());
	}

	protected static function assertStrict2D(bool $is2D, string $className): void
	{
		if (!$is2D) {
			throw new InvalidArgumentException($className . ' cannot be 3D.');
		}
	}

	protected static function finiteTan(CSSNumericValue $angleValue): float
	{
		$tan = tan(self::scalar($angleValue, 'angle'));
		if (!is_finite($tan)) {
			throw new InvalidArgumentException('Skew angle produces a non-finite tangent.');
		}

		return $tan;
	}

	protected function promoteFromSingleAxis(string $otherAxisFn, string $zFn, string $fn2D, string $fn3D): void
	{
		if ($this->functionName === $otherAxisFn) {
			$this->functionName = $fn2D;
		} elseif ($this->functionName === $zFn) {
			$this->functionName = $fn3D;
			$this->twoDimensional = false;
		}
	}

	protected function applyAxisIs2D(bool $is2D, string $zFn, string $fn2D, string $fn3D): void
	{
		$this->twoDimensional = $is2D;
		if ($is2D) {
			if ($this->functionName === $fn3D || $this->functionName === $zFn) {
				$this->functionName = $fn2D;
			}
		} elseif ($this->functionName !== $zFn) {
			$this->functionName = $fn3D;
		}
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

	protected function prepareForSerialization(): void
	{
	}

	/**
	 * @internal
	 * @return list<CSSStyleValue|string>
	 */
	public function getComponentValuesInternal(): array
	{
		$this->prepareForSerialization();
		return $this->values;
	}

	/** @internal */
	public function toBoundedCssTextInternal(?int $maxBytes): string
	{
		$this->prepareForSerialization();
		$used = strlen($this->functionName) + 2;
		if ($maxBytes !== null && $used > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS transform component exceeds maximum size of %d bytes.', $maxBytes));
		}
		$parts = [];
		foreach ($this->values as $i => $value) {
			$sep = $i === 0 ? '' : ', ';
			$used += strlen($sep);
			if ($maxBytes !== null && $used > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS transform component exceeds maximum size of %d bytes.', $maxBytes));
			}
			$remaining = $maxBytes !== null ? $maxBytes - $used : null;
			if ($value instanceof CSSMathValue || $value instanceof self) {
				$part = $value->toBoundedCssTextInternal($remaining);
			} else {
				$part = (string) $value;
			}
			$used += strlen($part);
			if ($maxBytes !== null && $used > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS transform component exceeds maximum size of %d bytes.', $maxBytes));
			}
			$parts[] = $sep . $part;
		}

		return $this->functionName . '(' . implode('', $parts) . ')';
	}

	public function __toString(): string
	{
		return $this->toBoundedCssTextInternal(CSSParserOptions::DEFAULT_MAX_BYTES);
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
			if ($numeric instanceof CSSUnitValue) {
				if (!in_array($numeric->getNumericCategory(), $expectedCategories, true)) {
					throw new InvalidArgumentException('Transform component argument has an incompatible numeric type.');
				}

				return $numeric;
			}
			$type = $numeric->getNumericTypeInternal();
			$category = $numeric->getNumericCategory();
			$categoryValid = in_array($category, $expectedCategories, true);
			$hint = $type->getPercentHintInternal();
			$hintValid = $hint === null
				|| (in_array('percent', $expectedCategories, true) && in_array($hint, $expectedCategories, true));
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
		$unitObj = $value->getUnitObjInternal();
		if ($unitObj === null) {
			throw new LogicException('No unit set.');
		}
		$type = $unitObj->type()?->value;
		$compatible = $expectedType === 'number'
			? $unitObj === CSSUnitEnum::NUMBER
			: $type === $expectedType;
		if (!$compatible) {
			throw new InvalidArgumentException('Transform component value has an incompatible unit.');
		}

		if ($expectedType === 'length') {
			$factor = $unitObj->absoluteToPxFactor();
			if ($factor === null) {
				throw new InvalidArgumentException('Matrix conversion requires absolute length units; relative unit given: ' . $unitObj->toUnitName());
			}
			$result = $value->value * $factor;
		} elseif ($expectedType === 'angle') {
			$factor = $unitObj->angleToRadiansFactor();
			if ($factor === null) {
				throw new InvalidArgumentException('Expected an angle unit.');
			}
			$result = $value->value * $factor;
		} else {
			$result = $value->value;
		}

		if (!is_finite($result)) {
			throw new InvalidArgumentException('Transform component value must be finite.');
		}

		return $result === 0.0 ? 0.0 : $result;
	}
}
