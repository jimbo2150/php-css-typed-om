<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents CSS `rotate()`, `rotateX()`, `rotateY()`, `rotateZ()`, and `rotate3d()`.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSRotate
 */
final class CSSRotate extends CSSTransformComponent
{
	private CSSNumericValue $x;
	private CSSNumericValue $y;
	private CSSNumericValue $z;
	private CSSNumericValue $angle;

	public function __construct(
		CSSNumericValue|int|float|string $angleOrFunction,
		array|CSSNumericValue|int|float|string|null $valuesOrY = null,
		CSSNumericValue|int|float|string|null $z = null,
		CSSNumericValue|int|float|string|null $angle = null,
	) {
		if (is_string($angleOrFunction) && is_array($valuesOrY)) {
			$function = strtolower($angleOrFunction);
			[$this->x, $this->y, $this->z, $this->angle, $serialized, $is2D] = self::parseFunction($function, array_values($valuesOrY));
			self::assertNonZeroAxis($this->x, $this->y, $this->z);
			parent::__construct($function, $serialized, $is2D);
			return;
		}

		if ($angle !== null) {
			$this->x = self::numericValue($angleOrFunction, 'number', ['number']);
			$this->y = self::numericValue($valuesOrY ?? 0, 'number', ['number']);
			$this->z = self::numericValue($z ?? 0, 'number', ['number']);
			$this->angle = self::numericValue($angle, 'deg', ['angle']);
			self::assertNonZeroAxis($this->x, $this->y, $this->z);
			parent::__construct('rotate3d', [$this->x, $this->y, $this->z, $this->angle], false);
			return;
		}

		$this->x = new CSSUnitValue(0, 'number');
		$this->y = new CSSUnitValue(0, 'number');
		$this->z = new CSSUnitValue(1, 'number');
		$this->angle = self::numericValue($angleOrFunction, 'deg', ['angle']);
		parent::__construct('rotate', [$this->angle], true);
	}

	public function getAngle(): CSSNumericValue
	{
		return $this->angle;
	}

	public function setAngle(CSSNumericValue $angle): void
	{
		$this->angle = self::numericValue($angle, 'deg', ['angle']);
		$this->syncSerializedValues();
	}

	public function getX(): CSSNumericValue
	{
		return $this->x;
	}

	public function setX(CSSNumericValue $x): void
	{
		$next = self::numericValue($x, 'number', ['number']);
		self::assertNonZeroAxis($next, $this->y, $this->z);
		$this->x = $next;
		$this->syncSerializedValues();
	}

	public function getY(): CSSNumericValue
	{
		return $this->y;
	}

	public function setY(CSSNumericValue $y): void
	{
		$next = self::numericValue($y, 'number', ['number']);
		self::assertNonZeroAxis($this->x, $next, $this->z);
		$this->y = $next;
		$this->syncSerializedValues();
	}

	public function getZ(): CSSNumericValue
	{
		return $this->z;
	}

	public function setZ(CSSNumericValue $z): void
	{
		$next = self::numericValue($z, 'number', ['number']);
		self::assertNonZeroAxis($this->x, $this->y, $next);
		$this->z = $next;
		$this->syncSerializedValues();
	}

	public function toMatrix(): array
	{
		$angle = self::scalar($this->angle, 'angle');
		$cos = cos($angle);
		$sin = sin($angle);
		$x = self::scalar($this->x, 'number');
		$y = self::scalar($this->y, 'number');
		$z = self::scalar($this->z, 'number');
		$axisLength = sqrt($x * $x + $y * $y + $z * $z);
		if ($axisLength === 0.0) {
			throw new InvalidArgumentException('A rotation axis cannot be zero.');
		}
		$x /= $axisLength;
		$y /= $axisLength;
		$z /= $axisLength;
		$t = 1.0 - $cos;
		$matrix = self::identityMatrix();
		$matrix[0] = $t * $x * $x + $cos;
		$matrix[1] = $t * $x * $y + $sin * $z;
		$matrix[2] = $t * $x * $z - $sin * $y;
		$matrix[4] = $t * $x * $y - $sin * $z;
		$matrix[5] = $t * $y * $y + $cos;
		$matrix[6] = $t * $y * $z + $sin * $x;
		$matrix[8] = $t * $x * $z + $sin * $y;
		$matrix[9] = $t * $y * $z - $sin * $x;
		$matrix[10] = $t * $z * $z + $cos;

		return $matrix;
	}

	public function clone(): static
	{
		return new static($this->functionName, $this->values);
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'angle' => $this->angle,
			'x' => $this->x,
			'y' => $this->y,
			'z' => $this->z,
			default => parent::__get($name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'angle' && $value instanceof CSSNumericValue) {
			$this->setAngle($value);
			return;
		}
		if ($name === 'x' && $value instanceof CSSNumericValue) {
			$this->setX($value);
			return;
		}
		if ($name === 'y' && $value instanceof CSSNumericValue) {
			$this->setY($value);
			return;
		}
		if ($name === 'z' && $value instanceof CSSNumericValue) {
			$this->setZ($value);
			return;
		}
		parent::__set($name, $value);
	}

	private function syncSerializedValues(): void
	{
		$this->values = $this->functionName === 'rotate3d'
			? [$this->x, $this->y, $this->z, $this->angle]
			: [$this->angle];
	}

	private static function assertNonZeroAxis(CSSNumericValue $x, CSSNumericValue $y, CSSNumericValue $z): void
	{
		if ($x instanceof CSSUnitValue && $y instanceof CSSUnitValue && $z instanceof CSSUnitValue) {
			if (($x->value * $x->value + $y->value * $y->value + $z->value * $z->value) === 0.0) {
				throw new InvalidArgumentException('A rotation axis cannot be zero.');
			}
		}
	}

	/** @param list<CSSStyleValue|string> $arguments @return array{CSSNumericValue, CSSNumericValue, CSSNumericValue, CSSNumericValue, list<CSSNumericValue>, bool} */
	private static function parseFunction(string $function, array $arguments): array
	{
		$zero = new CSSUnitValue(0, 'number');
		$one = new CSSUnitValue(1, 'number');
		$x = $zero;
		$y = $zero;
		$z = $one;
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
			$x = $one;
			$z = $zero;
			$serialized = [$angle];
		} elseif ($function === 'rotatey') {
			if (count($arguments) !== 1) {
				throw new InvalidArgumentException('rotateY() requires one angle.');
			}
			$angle = self::numericValue($arguments[0], 'deg', ['angle']);
			$y = $one;
			$z = $zero;
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

		return [$x, $y, $z, $angle, $serialized, $is2D];
	}
}
