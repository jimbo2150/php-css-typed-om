<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents CSS `translate()`, `translateX()`, `translateY()`, `translateZ()`, and `translate3d()`.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSTranslate
 */
final class CSSTranslate extends CSSTransformComponent
{
	private CSSNumericValue $xValue;
	private CSSNumericValue $yValue;
	private CSSNumericValue $zValue;

	public CSSNumericValue $x {
		get => $this->xValue;
		set (CSSNumericValue|int|float $value) {
			$this->setX($value);
		}
	}

	public CSSNumericValue $y {
		get => $this->yValue;
		set (CSSNumericValue|int|float $value) {
			$this->setY($value);
		}
	}

	public CSSNumericValue $z {
		get => $this->zValue;
		set (CSSNumericValue|int|float $value) {
			$this->setZ($value);
		}
	}

	/**
	 * @param CSSNumericValue|int|float|string $xOrFunction
	 * @param list<CSSStyleValue|string>|CSSNumericValue|int|float|string|null $yOrValues
	 */
	public function __construct(
		CSSNumericValue|int|float|string $xOrFunction,
		array|CSSNumericValue|int|float|string|null $yOrValues = null,
		CSSNumericValue|int|float|string|bool|null $z = null,
		?bool $is2D = null,
	) {
		if (is_bool($z)) {
			if ($is2D !== null && $is2D !== $z) {
				throw new InvalidArgumentException('Conflicting $z boolean and $is2D arguments for CSSTranslate.');
			}
			$is2D = $z;
			$z = null;
		}

		if (is_string($xOrFunction) && is_array($yOrValues)) {
			if ($z !== null) {
				throw new InvalidArgumentException('CSSTranslate function form does not accept a third coordinate argument.');
			}
			$function = strtolower($xOrFunction);
			$values = array_values($yOrValues);
			[$this->xValue, $this->yValue, $this->zValue, $serialized, $normalizedIs2D] = self::fromFunctionArguments($function, $values);
			$resolvedIs2D = $is2D ?? $normalizedIs2D;
			if ($resolvedIs2D !== $normalizedIs2D) {
				if ($resolvedIs2D) {
					if ($function === 'translate3d' || $function === 'translatez') {
						$function = 'translate';
					}
					$serialized = [$this->xValue, $this->yValue];
				} else {
					if ($function !== 'translatez') {
						$function = 'translate3d';
					}
					$serialized = $function === 'translatez' ? [$this->zValue] : [$this->xValue, $this->yValue, $this->zValue];
				}
			}
			parent::__construct($function, $serialized, $resolvedIs2D);
			return;
		}

		$this->xValue = self::numericValue($xOrFunction, 'px', ['length', 'percent']);
		$this->yValue = $yOrValues === null ? new CSSUnitValue(0, 'px') : self::numericValue($yOrValues, 'px', ['length', 'percent']);
		$hasZ = $z !== null;
		$this->zValue = $hasZ ? self::numericValue($z, 'px', ['length']) : new CSSUnitValue(0, 'px');
		$resolvedIs2D = $is2D ?? !$hasZ;
		$serializedValues = $resolvedIs2D ? [$this->xValue, $this->yValue] : [$this->xValue, $this->yValue, $this->zValue];
		parent::__construct($resolvedIs2D ? 'translate' : 'translate3d', $serializedValues, $resolvedIs2D);
	}

	public function getX(): CSSNumericValue
	{
		return $this->xValue;
	}

	public function setX(CSSNumericValue|int|float $x): void
	{
		$this->xValue = self::numericValue($x, 'px', ['length', 'percent']);
		$this->promoteFromSingleAxis('translatey', 'translatez', 'translate', 'translate3d');
		$this->syncSerializedValues();
	}

	public function getY(): CSSNumericValue
	{
		return $this->yValue;
	}

	public function setY(CSSNumericValue|int|float $y): void
	{
		$this->yValue = self::numericValue($y, 'px', ['length', 'percent']);
		$this->promoteFromSingleAxis('translatex', 'translatez', 'translate', 'translate3d');
		$this->syncSerializedValues();
	}

	public function getZ(): CSSNumericValue
	{
		return $this->zValue;
	}

	public function setZ(CSSNumericValue|int|float $z): void
	{
		$this->zValue = self::numericValue($z, 'px', ['length']);
		if (!($this->zValue instanceof CSSUnitValue && $this->zValue->value === 0.0)) {
			$this->twoDimensional = false;
			if ($this->functionName !== 'translatez') {
				$this->functionName = 'translate3d';
			}
		}
		$this->syncSerializedValues();
	}

	public function setIs2D(bool $is2D): void
	{
		$this->applyAxisIs2D($is2D, 'translatez', 'translate', 'translate3d');
		$this->syncSerializedValues();
	}

	public function toMatrix(): DOMMatrix
	{
		$matrix = self::IDENTITY_MATRIX;
		$matrix[12] = self::scalar($this->xValue, 'length');
		$matrix[13] = self::scalar($this->yValue, 'length');
		$z = $this->twoDimensional ? 0.0 : self::scalar($this->zValue, 'length');
		$matrix[14] = $z;

		return DOMMatrix::fromTrustedElements($matrix, $this->twoDimensional && $z === 0.0);
	}

	public function applyToMatrixInternal(DOMMatrix $matrix): void
	{
		$matrix->translateSelf(
			self::scalar($this->xValue, 'length'),
			self::scalar($this->yValue, 'length'),
			$this->twoDimensional ? 0.0 : self::scalar($this->zValue, 'length'),
		);
	}

	public function __clone(): void
	{
		$preserveSingleTranslate = $this->functionName === 'translate' && count($this->values) === 1;
		$this->xValue = $this->xValue->clone();
		$this->yValue = $this->yValue->clone();
		$this->zValue = $this->zValue->clone();
		if ($preserveSingleTranslate) {
			$this->values = [$this->xValue];
			return;
		}
		$this->syncSerializedValues();
	}

	public function getFunctionName(): string
	{
		$this->syncLiveState();
		return parent::getFunctionName();
	}

	public function getValues(): array
	{
		$this->syncLiveState();
		return parent::getValues();
	}

	protected function prepareForSerialization(): void
	{
		$this->syncLiveState();
	}

	private function syncLiveState(): void
	{
		if ($this->functionName === 'translatex' && !self::isZeroNumeric($this->yValue)) {
			$this->functionName = 'translate';
		} elseif ($this->functionName === 'translatey' && !self::isZeroNumeric($this->xValue)) {
			$this->functionName = 'translate';
		} elseif (!$this->twoDimensional && $this->functionName === 'translatez' && (!self::isZeroNumeric($this->xValue) || !self::isZeroNumeric($this->yValue))) {
			$this->functionName = 'translate3d';
		} elseif ($this->functionName === 'translate' && count($this->values) === 1) {
			if (!self::isZeroNumeric($this->yValue)) {
				$this->values = [$this->xValue, $this->yValue];
			}
			return;
		}
		$this->syncSerializedValues();
	}

	private static function isZeroNumeric(CSSNumericValue $value): bool
	{
		return $value instanceof CSSUnitValue && $value->value === 0.0;
	}

	private function syncSerializedValues(): void
	{
		$this->values = match ($this->functionName) {
			'translatex' => [$this->xValue],
			'translatey' => [$this->yValue],
			'translatez' => [$this->zValue],
			'translate3d' => [$this->xValue, $this->yValue, $this->zValue],
			default => [$this->xValue, $this->yValue],
		};
	}

	/**
	 * @param list<CSSStyleValue|string> $values
	 * @return array{CSSNumericValue, CSSNumericValue, CSSNumericValue, list<CSSNumericValue>, bool}
	 */
	private static function fromFunctionArguments(string $function, array $values): array
	{
		$assertCount = static function (int $min, int $max) use ($values, $function): void {
			if (count($values) < $min || count($values) > $max) {
				throw new InvalidArgumentException('Invalid argument count for ' . $function . '().');
			}
		};

		$x = null;
		$y = null;
		$z = null;
		$serialized = [];
		$is2D = true;

		switch ($function) {
			case 'translate':
				$assertCount(1, 2);
				$x = self::numericValue($values[0], 'px', ['length', 'percent']);
				if (isset($values[1])) {
					$y = self::numericValue($values[1], 'px', ['length', 'percent']);
					$serialized = [$x, $y];
				} else {
					$serialized = [$x];
				}
				break;
			case 'translatex':
				$assertCount(1, 1);
				$x = self::numericValue($values[0], 'px', ['length', 'percent']);
				$serialized = [$x];
				break;
			case 'translatey':
				$assertCount(1, 1);
				$y = self::numericValue($values[0], 'px', ['length', 'percent']);
				$serialized = [$y];
				break;
			case 'translatez':
				$assertCount(1, 1);
				$z = self::numericValue($values[0], 'px', ['length']);
				$serialized = [$z];
				$is2D = false;
				break;
			case 'translate3d':
				$assertCount(3, 3);
				$x = self::numericValue($values[0], 'px', ['length', 'percent']);
				$y = self::numericValue($values[1], 'px', ['length', 'percent']);
				$z = self::numericValue($values[2], 'px', ['length']);
				$serialized = [$x, $y, $z];
				$is2D = false;
				break;
			default:
				throw new InvalidArgumentException('Unsupported translation function: ' . $function);
		}

		return [
			$x ?? new CSSUnitValue(0, 'px'),
			$y ?? new CSSUnitValue(0, 'px'),
			$z ?? new CSSUnitValue(0, 'px'),
			$serialized,
			$is2D,
		];
	}
}
