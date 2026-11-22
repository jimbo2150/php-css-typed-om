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
	private CSSNumericValue $x;
	private CSSNumericValue $y;
	private CSSNumericValue $z;

	public function __construct(
		CSSNumericValue|int|float|string $xOrFunction,
		array|CSSNumericValue|int|float|string|null $yOrValues = null,
		CSSNumericValue|int|float|string|bool|null $z = null,
		?bool $is2D = null,
	) {
		if (is_string($xOrFunction) && is_array($yOrValues)) {
			$function = strtolower($xOrFunction);
			$values = array_values($yOrValues);
			[$this->x, $this->y, $this->z, $normalizedIs2D] = self::fromFunctionArguments($function, $values);
			parent::__construct($function, $values, $is2D ?? $normalizedIs2D);
			return;
		}

		$this->x = self::numericValue($xOrFunction, 'px', ['length', 'percent']);
		$this->y = $yOrValues === null ? new CSSUnitValue(0, 'px') : self::numericValue($yOrValues, 'px', ['length', 'percent']);
		$this->z = $z === null || is_bool($z) ? new CSSUnitValue(0, 'px') : self::numericValue($z, 'px', ['length']);
		$hasZ = $z !== null && !is_bool($z);
		$serializedValues = $hasZ ? [$this->x, $this->y, $this->z] : [$this->x, $this->y];
		$normalizedIs2D = !$hasZ || ($this->z instanceof CSSUnitValue && $this->z->value === 0.0);
		parent::__construct($hasZ ? 'translate3d' : 'translate', $serializedValues, $is2D ?? $normalizedIs2D);
	}

	public function getX(): CSSNumericValue
	{
		return $this->x;
	}

	public function setX(CSSNumericValue $x): void
	{
		$this->x = self::numericValue($x, 'px', ['length', 'percent']);
		$this->syncSerializedValues();
	}

	public function getY(): CSSNumericValue
	{
		return $this->y;
	}

	public function setY(CSSNumericValue $y): void
	{
		$this->y = self::numericValue($y, 'px', ['length', 'percent']);
		$this->syncSerializedValues();
	}

	public function getZ(): CSSNumericValue
	{
		return $this->z;
	}

	public function setZ(CSSNumericValue $z): void
	{
		$this->z = self::numericValue($z, 'px', ['length']);
		$this->syncSerializedValues();
	}

	public function toMatrix(): DOMMatrix
	{
		$matrix = self::identityMatrix();
		$matrix[12] = self::scalar($this->x, 'length');
		$matrix[13] = self::scalar($this->y, 'length');
		$z = $this->twoDimensional ? 0.0 : self::scalar($this->z, 'length');
		$matrix[14] = $z;

		return DOMMatrix::fromElements($matrix, $this->twoDimensional && $z === 0.0);
	}

	public function clone(): static
	{
		return new static($this->x->clone(), $this->y->clone(), $this->twoDimensional ? null : $this->z->clone(), $this->twoDimensional);
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'x' => $this->getX(),
			'y' => $this->getY(),
			'z' => $this->getZ(),
			default => parent::__get($name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
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
		$this->values = match ($this->functionName) {
			'translatex' => [$this->x],
			'translatey' => [$this->y],
			'translatez' => [$this->z],
			'translate3d' => [$this->x, $this->y, $this->z],
			default => [$this->x, $this->y],
		};
	}

	/** @param list<CSSStyleValue|string> $values @return array{CSSNumericValue, CSSNumericValue, CSSNumericValue, bool} */
	private static function fromFunctionArguments(string $function, array $values): array
	{
		$zero = new CSSUnitValue(0, 'px');
		$oneOrTwo = static function (int $min, int $max) use ($values, $function): void {
			if (count($values) < $min || count($values) > $max) {
				throw new InvalidArgumentException('Invalid argument count for ' . $function . '().');
			}
		};
		$x = $zero;
		$y = $zero;
		$z = $zero;
		$is2D = true;
		switch ($function) {
			case 'translate':
				$oneOrTwo(1, 2);
				$x = self::numericValue($values[0], 'px', ['length', 'percent']);
				$y = isset($values[1]) ? self::numericValue($values[1], 'px', ['length', 'percent']) : $zero;
				break;
			case 'translatex':
				$oneOrTwo(1, 1);
				$x = self::numericValue($values[0], 'px', ['length', 'percent']);
				break;
			case 'translatey':
				$oneOrTwo(1, 1);
				$y = self::numericValue($values[0], 'px', ['length', 'percent']);
				break;
			case 'translatez':
				$oneOrTwo(1, 1);
				$z = self::numericValue($values[0], 'px', ['length']);
				$is2D = false;
				break;
			case 'translate3d':
				$oneOrTwo(3, 3);
				$x = self::numericValue($values[0], 'px', ['length', 'percent']);
				$y = self::numericValue($values[1], 'px', ['length', 'percent']);
				$z = self::numericValue($values[2], 'px', ['length']);
				$is2D = false;
				break;
			default:
				throw new InvalidArgumentException('Unsupported translation function: ' . $function);
		}
		return [$x, $y, $z, $is2D];
	}
}
