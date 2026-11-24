<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents CSS `rotate()`, `rotateX()`, `rotateY()`, `rotateZ()`, and `rotate3d()`.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSRotate
 */
final class CSSRotate extends CSSTransformComponent
{
	private CSSNumericValue $xValue;
	private CSSNumericValue $yValue;
	private CSSNumericValue $zValue;
	private CSSNumericValue $angleValue;

	public CSSNumericValue $angle {
		get => $this->angleValue;
		set (CSSNumericValue|int|float $value) {
			$this->setAngle($value);
		}
	}

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
	 * @param CSSNumericValue|int|float|string $angleOrFunction
	 * @param list<CSSStyleValue|string>|CSSNumericValue|int|float|string|null $valuesOrY
	 */
	public function __construct(
		CSSNumericValue|int|float|string $angleOrFunction,
		array|CSSNumericValue|int|float|string|null $valuesOrY = null,
		CSSNumericValue|int|float|string|null $z = null,
		CSSNumericValue|int|float|string|null $angle = null,
	) {
		if (is_string($angleOrFunction) && is_array($valuesOrY)) {
			$function = strtolower($angleOrFunction);
			[$this->xValue, $this->yValue, $this->zValue, $this->angleValue, $serialized, $is2D] = self::parseFunction($function, array_values($valuesOrY));
			self::assertNonZeroAxis($this->xValue, $this->yValue, $this->zValue);
			parent::__construct($function, $serialized, $is2D);
			return;
		}

		if ($angle !== null) {
			$this->xValue = self::numericValue($angleOrFunction, 'number', ['number']);
			$this->yValue = self::numericValue($valuesOrY ?? 0, 'number', ['number']);
			$this->zValue = self::numericValue($z ?? 0, 'number', ['number']);
			$this->angleValue = self::numericValue($angle, 'deg', ['angle']);
			self::assertNonZeroAxis($this->xValue, $this->yValue, $this->zValue);
			parent::__construct('rotate3d', [$this->xValue, $this->yValue, $this->zValue, $this->angleValue], false);
			return;
		}

		if ($valuesOrY !== null || $z !== null) {
			throw new InvalidArgumentException('CSSRotate requires either 1 angle or 4 axis+angle arguments.');
		}

		$this->xValue = new CSSUnitValue(0, 'number');
		$this->yValue = new CSSUnitValue(0, 'number');
		$this->zValue = new CSSUnitValue(1, 'number');
		$this->angleValue = self::numericValue($angleOrFunction, 'deg', ['angle']);
		parent::__construct('rotate', [$this->angleValue], true);
	}

	public function getAngle(): CSSNumericValue { return $this->angleValue; }
	public function getX(): CSSNumericValue { return $this->xValue; }
	public function getY(): CSSNumericValue { return $this->yValue; }
	public function getZ(): CSSNumericValue { return $this->zValue; }

	public function setAngle(CSSNumericValue|int|float $angle): void
	{
		$this->angleValue = self::numericValue($angle, 'deg', ['angle']);
		$this->syncSerializedValues();
	}

	public function setX(CSSNumericValue|int|float $x): void
	{
		$next = self::numericValue($x, 'number', ['number']);
		self::assertNonZeroAxis($next, $this->yValue, $this->zValue);
		$this->xValue = $next;
		$this->promoteAxisFunction();
		$this->syncSerializedValues();
	}

	public function setY(CSSNumericValue|int|float $y): void
	{
		$next = self::numericValue($y, 'number', ['number']);
		self::assertNonZeroAxis($this->xValue, $next, $this->zValue);
		$this->yValue = $next;
		$this->promoteAxisFunction();
		$this->syncSerializedValues();
	}

	public function setZ(CSSNumericValue|int|float $z): void
	{
		$next = self::numericValue($z, 'number', ['number']);
		self::assertNonZeroAxis($this->xValue, $this->yValue, $next);
		$this->zValue = $next;
		$this->promoteAxisFunction();
		$this->syncSerializedValues();
	}

	public function setIs2D(bool $is2D): void
	{
		if (!$is2D) {
			self::assertNonZeroAxis($this->xValue, $this->yValue, $this->zValue);
		}
		parent::setIs2D($is2D);
		if ($is2D) {
			if (
				$this->functionName === 'rotate3d'
				|| $this->functionName === 'rotatez'
				|| $this->functionName === 'rotatex'
				|| $this->functionName === 'rotatey'
			) {
				$this->functionName = 'rotate';
			}
		} elseif ($this->functionName === 'rotate') {
			$this->functionName = 'rotate3d';
		}
		$this->syncSerializedValues();
	}

	public function toMatrix(): DOMMatrix
	{
		$angleRad = self::scalar($this->angleValue, 'angle');
		if ($this->twoDimensional) {
			$cos = cos($angleRad);
			$sin = sin($angleRad);
			$cos = $cos === 0.0 ? 0.0 : $cos;
			$sin = $sin === 0.0 ? 0.0 : $sin;
			$negSin = $sin === 0.0 ? 0.0 : -$sin;
			return DOMMatrix::fromTrustedElements(self::matrix2d($cos, $sin, $negSin, $cos, 0.0, 0.0), true);
		}

		$x = self::scalar($this->xValue, 'number');
		$y = self::scalar($this->yValue, 'number');
		$z = self::scalar($this->zValue, 'number');
		$maxAxis = max(abs($x), abs($y), abs($z));
		if ($maxAxis === 0.0) {
			throw new InvalidArgumentException('A rotation axis must be finite and non-zero.');
		}
		$nx = $x / $maxAxis;
		$ny = $y / $maxAxis;
		$nz = $z / $maxAxis;

		$matrix = (new DOMMatrix())->rotateAxisAngleSelf($nx, $ny, $nz, rad2deg($angleRad));
		return DOMMatrix::fromTrustedElements($matrix->toFloat64Array(), false);
	}

	public function __clone(): void
	{
		$this->xValue = $this->xValue->clone();
		$this->yValue = $this->yValue->clone();
		$this->zValue = $this->zValue->clone();
		$this->angleValue = $this->angleValue->clone();
		$this->syncSerializedValues();
	}

	public function clone(): static
	{
		return clone $this;
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

	public function __toString(): string
	{
		$this->syncLiveState();
		return parent::__toString();
	}

	private function syncLiveState(): void
	{
		if (!$this->twoDimensional) {
			self::assertNonZeroAxis($this->xValue, $this->yValue, $this->zValue);
			$this->promoteAxisFunction();
		}
		$this->syncSerializedValues();
	}

	private function syncSerializedValues(): void
	{
		$this->values = $this->functionName === 'rotate3d'
			? [$this->xValue, $this->yValue, $this->zValue, $this->angleValue]
			: [$this->angleValue];
	}

	private function promoteAxisFunction(): void
	{
		$matchesCanonical = match ($this->functionName) {
			'rotate', 'rotatez' => self::isZeroNumeric($this->xValue) && self::isZeroNumeric($this->yValue) && self::isPositiveNumeric($this->zValue),
			'rotatex' => self::isPositiveNumeric($this->xValue) && self::isZeroNumeric($this->yValue) && self::isZeroNumeric($this->zValue),
			'rotatey' => self::isZeroNumeric($this->xValue) && self::isPositiveNumeric($this->yValue) && self::isZeroNumeric($this->zValue),
			default => true,
		};
		if (!$matchesCanonical) {
			$this->functionName = 'rotate3d';
			$this->twoDimensional = false;
		}
	}

	private static function isZeroNumeric(CSSNumericValue $value): bool
	{
		return $value instanceof CSSUnitValue && $value->value === 0.0;
	}

	private static function isPositiveNumeric(CSSNumericValue $value): bool
	{
		return $value instanceof CSSUnitValue && $value->value > 0.0;
	}

	private static function assertNonZeroAxis(CSSNumericValue $x, CSSNumericValue $y, CSSNumericValue $z): void
	{
		if ($x instanceof CSSUnitValue && $y instanceof CSSUnitValue && $z instanceof CSSUnitValue) {
			if ($x->value === 0.0 && $y->value === 0.0 && $z->value === 0.0) {
				throw new InvalidArgumentException('A rotation axis cannot be zero.');
			}
		}
	}

	/**
	 * @param list<CSSStyleValue|string> $arguments
	 * @return array{CSSNumericValue, CSSNumericValue, CSSNumericValue, CSSNumericValue, list<CSSNumericValue>, bool}
	 */
	private static function parseFunction(string $function, array $arguments): array
	{
		$x = null;
		$y = null;
		$z = null;
		$is2D = false;

		if ($function === 'rotate') {
			if (count($arguments) !== 1) {
				throw new InvalidArgumentException('rotate() requires one angle.');
			}
			$angle = self::numericValue($arguments[0], 'deg', ['angle']);
			$serialized = [$angle];
			$is2D = true;
		} elseif ($function === 'rotatez') {
			if (count($arguments) !== 1) {
				throw new InvalidArgumentException('rotateZ() requires one angle.');
			}
			$angle = self::numericValue($arguments[0], 'deg', ['angle']);
			$serialized = [$angle];
		} elseif ($function === 'rotatex') {
			if (count($arguments) !== 1) {
				throw new InvalidArgumentException('rotateX() requires one angle.');
			}
			$angle = self::numericValue($arguments[0], 'deg', ['angle']);
			$x = new CSSUnitValue(1, 'number');
			$z = new CSSUnitValue(0, 'number');
			$serialized = [$angle];
		} elseif ($function === 'rotatey') {
			if (count($arguments) !== 1) {
				throw new InvalidArgumentException('rotateY() requires one angle.');
			}
			$angle = self::numericValue($arguments[0], 'deg', ['angle']);
			$y = new CSSUnitValue(1, 'number');
			$z = new CSSUnitValue(0, 'number');
			$serialized = [$angle];
		} elseif ($function === 'rotate3d') {
			if (count($arguments) !== 4) {
				throw new InvalidArgumentException('rotate3d() requires four values.');
			}
			$x = self::numericValue($arguments[0], 'number', ['number']);
			$y = self::numericValue($arguments[1], 'number', ['number']);
			$z = self::numericValue($arguments[2], 'number', ['number']);
			$angle = self::numericValue($arguments[3], 'deg', ['angle']);
			$serialized = [$x, $y, $z, $angle];
		} else {
			throw new InvalidArgumentException('Unsupported rotation function: ' . $function);
		}

		return [
			$x ?? new CSSUnitValue(0, 'number'),
			$y ?? new CSSUnitValue(0, 'number'),
			$z ?? new CSSUnitValue(1, 'number'),
			$angle,
			$serialized,
			$is2D,
		];
	}
}
