<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents CSS `scale()`, `scaleX()`, `scaleY()`, `scaleZ()`, and `scale3d()`.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSScale
 */
final class CSSScale extends CSSTransformComponent
{
	private CSSNumericValue $x;
	private CSSNumericValue $y;
	private CSSNumericValue $z;

	public function __construct(
		CSSNumericValue|int|float|string $xOrFunction,
		array|CSSNumericValue|int|float|string|null $yOrValues = null,
		CSSNumericValue|int|float|string|null $z = null,
	) {
		if (is_string($xOrFunction) && is_array($yOrValues)) {
			$function = strtolower($xOrFunction);
			[$this->x, $this->y, $this->z, $serialized, $is2D] = self::parseFunction($function, array_values($yOrValues));
			parent::__construct($function, $serialized, $is2D);
			return;
		}

		$this->x = self::numericValue($xOrFunction, 'number', ['number']);
		$this->y = $yOrValues === null ? $this->x : self::numericValue($yOrValues, 'number', ['number']);
		$this->z = $z === null ? new CSSUnitValue(1, 'number') : self::numericValue($z, 'number', ['number']);
		$hasZ = $z !== null;
		$is2D = !$hasZ || ($this->z instanceof CSSUnitValue && $this->z->value === 1.0);
		parent::__construct($hasZ ? 'scale3d' : 'scale', $hasZ ? [$this->x, $this->y, $this->z] : [$this->x, $this->y], $is2D);
	}

	public function getX(): CSSNumericValue { return $this->x; }
	public function getY(): CSSNumericValue { return $this->y; }
	public function getZ(): CSSNumericValue { return $this->z; }

	public function setX(CSSNumericValue|int|float $x): void
	{
		$this->x = self::numericValue($x, 'number', ['number']);
		$this->syncSerializedValues();
	}

	public function setY(CSSNumericValue|int|float $y): void
	{
		$this->y = self::numericValue($y, 'number', ['number']);
		$this->syncSerializedValues();
	}

	public function setZ(CSSNumericValue|int|float $z): void
	{
		$this->z = self::numericValue($z, 'number', ['number']);
		$this->syncSerializedValues();
	}

	public function toMatrix(): array
	{
		$matrix = self::identityMatrix();
		$matrix[0] = self::scalar($this->x, 'number');
		$matrix[5] = self::scalar($this->y, 'number');
		$matrix[10] = self::scalar($this->z, 'number');
		return $matrix;
	}

	public function clone(): static
	{
		return new static($this->functionName, $this->values);
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'x' => $this->x,
			'y' => $this->y,
			'z' => $this->z,
			default => parent::__get($name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'x' && ($value instanceof CSSNumericValue || is_int($value) || is_float($value))) {
			$this->setX($value);
			return;
		}
		if ($name === 'y' && ($value instanceof CSSNumericValue || is_int($value) || is_float($value))) {
			$this->setY($value);
			return;
		}
		if ($name === 'z' && ($value instanceof CSSNumericValue || is_int($value) || is_float($value))) {
			$this->setZ($value);
			return;
		}
		parent::__set($name, $value);
	}

	private function syncSerializedValues(): void
	{
		$this->values = match ($this->functionName) {
			'scalex' => [$this->x],
			'scaley' => [$this->y],
			'scalez' => [$this->z],
			'scale3d' => [$this->x, $this->y, $this->z],
			default => [$this->x, $this->y],
		};
	}

	/** @param list<CSSStyleValue|string> $arguments @return array{CSSNumericValue, CSSNumericValue, CSSNumericValue, list<CSSNumericValue>, bool} */
	private static function parseFunction(string $function, array $arguments): array
	{
		$one = new CSSUnitValue(1, 'number');
		$x = $one;
		$y = $one;
		$z = $one;
		$is2D = true;
		switch ($function) {
			case 'scale':
				if (count($arguments) < 1 || count($arguments) > 2) {
					throw new InvalidArgumentException('scale() requires one or two values.');
				}
				$x = self::numericValue($arguments[0], 'number', ['number']);
				$y = isset($arguments[1]) ? self::numericValue($arguments[1], 'number', ['number']) : $x;
				$serialized = isset($arguments[1]) ? [$x, $y] : [$x];
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
				}
				if ($function === 'scaley') {
					$y = $value;
				}
				if ($function === 'scalez') {
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
		return [$x, $y, $z, $serialized, $is2D];
	}
}
