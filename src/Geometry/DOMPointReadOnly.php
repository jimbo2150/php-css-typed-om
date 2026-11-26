<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Geometry;

use ArrayAccess;
use Error;
use InvalidArgumentException;
use ReflectionClass;

/**
 * Read-only 3D homogeneous point (W3C Geometry Interfaces Level 1).
 *
 * Implements ArrayAccess over `'x'|'y'|'z'|'w'` for backward-compatible dictionary reads.
 *
 * @phpstan-consistent-constructor
 * @implements ArrayAccess<string, float>
 * @property-read float $x
 * @property-read float $y
 * @property-read float $z
 * @property-read float $w
 * @see https://developer.mozilla.org/en-US/docs/Web/API/DOMPointReadOnly
 */
class DOMPointReadOnly implements ArrayAccess
{
	protected float $xValue;
	protected float $yValue;
	protected float $zValue;
	protected float $wValue;

	/**
	 * @throws InvalidArgumentException When any coordinate is non-finite
	 */
	public function __construct(
		float $x = 0.0,
		float $y = 0.0,
		float $z = 0.0,
		float $w = 1.0,
	) {
		$this->xValue = DOMMatrixReadOnly::assertFiniteFloat($x, 'point.x');
		$this->yValue = DOMMatrixReadOnly::assertFiniteFloat($y, 'point.y');
		$this->zValue = DOMMatrixReadOnly::assertFiniteFloat($z, 'point.z');
		$this->wValue = DOMMatrixReadOnly::assertFiniteFloat($w, 'point.w');
	}

	/**
	 * @param DOMPointReadOnly|array{x?: int|float, y?: int|float, z?: int|float, w?: int|float} $other
	 */
	public static function fromPoint(self|array $other = []): static
	{
		if ($other instanceof self) {
			static $reflections = [];
			$cls = static::class;
			$refl = $reflections[$cls] ??= new ReflectionClass($cls);
			/** @var static $copy */
			$copy = $refl->newInstanceWithoutConstructor();
			$copy->xValue = $other->xValue;
			$copy->yValue = $other->yValue;
			$copy->zValue = $other->zValue;
			$copy->wValue = $other->wValue;

			return $copy;
		}

		$x = DOMMatrixReadOnly::assertFiniteInternal($other['x'] ?? 0.0, 'point.x');
		$y = DOMMatrixReadOnly::assertFiniteInternal($other['y'] ?? 0.0, 'point.y');
		$z = DOMMatrixReadOnly::assertFiniteInternal($other['z'] ?? 0.0, 'point.z');
		$w = DOMMatrixReadOnly::assertFiniteInternal($other['w'] ?? 1.0, 'point.w');

		return new static($x, $y, $z, $w);
	}

	public function getX(): float
	{
		return $this->xValue;
	}

	public function getY(): float
	{
		return $this->yValue;
	}

	public function getZ(): float
	{
		return $this->zValue;
	}

	public function getW(): float
	{
		return $this->wValue;
	}

	/**
	 * @param DOMMatrixReadOnly|list<int|float>|array<string, mixed>|null $matrix
	 */
	public function matrixTransform(DOMMatrixReadOnly|array|null $matrix = null): DOMPoint
	{
		$resolved = $matrix instanceof DOMMatrixReadOnly
			? $matrix
			: DOMMatrixReadOnly::fromMatrix($matrix ?? []);

		return $resolved->transformPoint($this);
	}

	/** @return array{x: float, y: float, z: float, w: float} */
	public function toJSON(): array
	{
		return [
			'x' => $this->xValue,
			'y' => $this->yValue,
			'z' => $this->zValue,
			'w' => $this->wValue,
		];
	}

	public function offsetExists(mixed $offset): bool
	{
		return $offset === 'x' || $offset === 'y' || $offset === 'z' || $offset === 'w';
	}

	public function offsetGet(mixed $offset): ?float
	{
		return match ($offset) {
			'x' => $this->xValue,
			'y' => $this->yValue,
			'z' => $this->zValue,
			'w' => $this->wValue,
			default => null,
		};
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new InvalidArgumentException('DOMPointReadOnly is immutable.');
	}

	public function offsetUnset(mixed $offset): void
	{
		throw new InvalidArgumentException('DOMPointReadOnly is immutable.');
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'x' => $this->xValue,
			'y' => $this->yValue,
			'z' => $this->zValue,
			'w' => $this->wValue,
			default => throw new Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	public function __isset(string $name): bool
	{
		return $name === 'x' || $name === 'y' || $name === 'z' || $name === 'w';
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
