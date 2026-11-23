<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents CSS `scale()`, `scaleX()`, `scaleY()`, `scaleZ()`, and `scale3d()`.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSScale
 */
final class CSSScale extends CSSTransformComponent
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

	public function __construct(
		CSSNumericValue|int|float|string $xOrFunction,
		array|CSSNumericValue|int|float|string|null $yOrValues = null,
		CSSNumericValue|int|float|string|null $z = null,
	) {
		if (is_string($xOrFunction) && is_array($yOrValues)) {
			$function = strtolower($xOrFunction);
			[$this->xValue, $this->yValue, $this->zValue, $serialized, $is2D] = self::parseFunction($function, array_values($yOrValues));
			parent::__construct($function, $serialized, $is2D);
			return;
		}

		$this->xValue = self::numericValue($xOrFunction, 'number', ['number']);
		$this->yValue = $yOrValues === null ? $this->xValue->clone() : self::numericValue($yOrValues, 'number', ['number']);
		$this->zValue = $z === null ? new CSSUnitValue(1, 'number') : self::numericValue($z, 'number', ['number']);
		$hasZ = $z !== null;
		$is2D = !$hasZ;
		parent::__construct($hasZ ? 'scale3d' : 'scale', $hasZ ? [$this->xValue, $this->yValue, $this->zValue] : [$this->xValue, $this->yValue], $is2D);
	}

	public function getX(): CSSNumericValue { return $this->xValue; }
	public function getY(): CSSNumericValue { return $this->yValue; }
	public function getZ(): CSSNumericValue { return $this->zValue; }

	public function setX(CSSNumericValue|int|float $x): void
	{
		$this->xValue = self::numericValue($x, 'number', ['number']);
		if ($this->functionName === 'scaley') {
			$this->functionName = 'scale';
		} elseif ($this->functionName === 'scalez') {
			$this->functionName = 'scale3d';
		}
		$this->syncSerializedValues();
	}

	public function setY(CSSNumericValue|int|float $y): void
	{
		$this->yValue = self::numericValue($y, 'number', ['number']);
		if ($this->functionName === 'scalex') {
			$this->functionName = 'scale';
		} elseif ($this->functionName === 'scalez') {
			$this->functionName = 'scale3d';
		}
		$this->syncSerializedValues();
	}

	public function setZ(CSSNumericValue|int|float $z): void
	{
		$this->zValue = self::numericValue($z, 'number', ['number']);
		if (!($this->zValue instanceof CSSUnitValue && $this->zValue->value === 1.0)) {
			$this->twoDimensional = false;
			if ($this->functionName !== 'scalez') {
				$this->functionName = 'scale3d';
			}
		}
		$this->syncSerializedValues();
	}

	public function setIs2D(bool $is2D): void
	{
		parent::setIs2D($is2D);
		if ($is2D) {
			if ($this->functionName === 'scale3d' || $this->functionName === 'scalez') {
				$this->functionName = 'scale';
			}
		} elseif ($this->functionName !== 'scalez') {
			$this->functionName = 'scale3d';
		}
		$this->syncSerializedValues();
	}

	public function toMatrix(): DOMMatrix
	{
		$matrix = self::IDENTITY_MATRIX;
		$matrix[0] = self::scalar($this->xValue, 'number');
		$matrix[5] = self::scalar($this->yValue, 'number');
		$z = $this->twoDimensional ? 1.0 : self::scalar($this->zValue, 'number');
		$matrix[10] = $z;

		return DOMMatrix::fromTrustedElements($matrix, $this->twoDimensional && $z === 1.0);
	}

	public function __clone(): void
	{
		$preserveSingleScale = $this->functionName === 'scale' && count($this->values) === 1;
		$this->xValue = $this->xValue->clone();
		$this->yValue = $this->yValue->clone();
		$this->zValue = $this->zValue->clone();
		if ($preserveSingleScale) {
			$this->values = [$this->xValue];
			return;
		}
		$this->syncSerializedValues();
	}

	public function clone(): static
	{
		return clone $this;
	}

	private function syncSerializedValues(): void
	{
		$this->values = match ($this->functionName) {
			'scalex' => [$this->xValue],
			'scaley' => [$this->yValue],
			'scalez' => [$this->zValue],
			'scale3d' => [$this->xValue, $this->yValue, $this->zValue],
			default => [$this->xValue, $this->yValue],
		};
	}

	/** @param list<CSSStyleValue|string> $arguments @return array{CSSNumericValue, CSSNumericValue, CSSNumericValue, list<CSSNumericValue>, bool} */
	private static function parseFunction(string $function, array $arguments): array
	{
		$x = null;
		$y = null;
		$z = null;
		$is2D = true;

		switch ($function) {
			case 'scale':
				if (count($arguments) < 1 || count($arguments) > 2) {
					throw new InvalidArgumentException('scale() requires one or two values.');
				}
				$x = self::numericValue($arguments[0], 'number', ['number']);
				if (isset($arguments[1])) {
					$y = self::numericValue($arguments[1], 'number', ['number']);
					$serialized = [$x, $y];
				} else {
					$y = $x->clone();
					$serialized = [$x];
				}
				break;
			case 'scalex':
			case 'scaley':
			case 'scalez':
				if (count($arguments) !== 1) {
					throw new InvalidArgumentException($function . '() requires one value.');
				}
				$value = self::numericValue($arguments[0], 'number', ['number']);
				if ($function === 'scalex') {
					$x = $value;
				} elseif ($function === 'scaley') {
					$y = $value;
				} else {
					$z = $value;
					$is2D = false;
				}
				$serialized = [$value];
				break;
			case 'scale3d':
				if (count($arguments) !== 3) {
					throw new InvalidArgumentException('scale3d() requires three values.');
				}
				$x = self::numericValue($arguments[0], 'number', ['number']);
				$y = self::numericValue($arguments[1], 'number', ['number']);
				$z = self::numericValue($arguments[2], 'number', ['number']);
				$is2D = false;
				$serialized = [$x, $y, $z];
				break;
			default:
				throw new InvalidArgumentException('Unsupported scale function: ' . $function);
		}

		return [
			$x ?? new CSSUnitValue(1, 'number'),
			$y ?? new CSSUnitValue(1, 'number'),
			$z ?? new CSSUnitValue(1, 'number'),
			$serialized,
			$is2D,
		];
	}
}
