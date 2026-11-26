<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Geometry;

use Error;
use InvalidArgumentException;

/**
 * Four-corner quadrilateral with live mutable DOMPoint corners (W3C Geometry Interfaces Level 1).
 *
 * @property-read DOMPoint $p1
 * @property-read DOMPoint $p2
 * @property-read DOMPoint $p3
 * @property-read DOMPoint $p4
 * @see https://developer.mozilla.org/en-US/docs/Web/API/DOMQuad
 */
class DOMQuad
{
	private DOMPoint $p1Point;
	private DOMPoint $p2Point;
	private DOMPoint $p3Point;
	private DOMPoint $p4Point;

	/**
	 * @param DOMPointReadOnly|array{x?: int|float, y?: int|float, z?: int|float, w?: int|float} $p1
	 * @param DOMPointReadOnly|array{x?: int|float, y?: int|float, z?: int|float, w?: int|float} $p2
	 * @param DOMPointReadOnly|array{x?: int|float, y?: int|float, z?: int|float, w?: int|float} $p3
	 * @param DOMPointReadOnly|array{x?: int|float, y?: int|float, z?: int|float, w?: int|float} $p4
	 */
	public function __construct(
		DOMPointReadOnly|array $p1 = [],
		DOMPointReadOnly|array $p2 = [],
		DOMPointReadOnly|array $p3 = [],
		DOMPointReadOnly|array $p4 = [],
	) {
		$this->p1Point = DOMPoint::fromPoint($p1);
		$this->p2Point = DOMPoint::fromPoint($p2);
		$this->p3Point = DOMPoint::fromPoint($p3);
		$this->p4Point = DOMPoint::fromPoint($p4);
	}

	/**
	 * @param DOMRectReadOnly|array{x?: int|float, y?: int|float, width?: int|float, height?: int|float} $other
	 */
	public static function fromRect(DOMRectReadOnly|array $other = []): self
	{
		$rect = $other instanceof DOMRectReadOnly ? $other : DOMRectReadOnly::fromRect($other);
		$x = $rect->getX();
		$y = $rect->getY();
		$xw = DOMMatrixReadOnly::assertFiniteInternal($x + $rect->getWidth(), 'quad.right');
		$yh = DOMMatrixReadOnly::assertFiniteInternal($y + $rect->getHeight(), 'quad.bottom');

		return new self(
			new DOMPoint($x, $y),
			new DOMPoint($xw, $y),
			new DOMPoint($xw, $yh),
			new DOMPoint($x, $yh),
		);
	}

	/**
	 * @param DOMQuad|array{
	 *   p1?: DOMPointReadOnly|array{x?: int|float, y?: int|float, z?: int|float, w?: int|float},
	 *   p2?: DOMPointReadOnly|array{x?: int|float, y?: int|float, z?: int|float, w?: int|float},
	 *   p3?: DOMPointReadOnly|array{x?: int|float, y?: int|float, z?: int|float, w?: int|float},
	 *   p4?: DOMPointReadOnly|array{x?: int|float, y?: int|float, z?: int|float, w?: int|float}
	 * } $other
	 */
	public static function fromQuad(self|array $other = []): self
	{
		if ($other instanceof self) {
			return clone $other;
		}

		$corners = [];
		foreach (['p1', 'p2', 'p3', 'p4'] as $key) {
			$raw = $other[$key] ?? [];
			if (!$raw instanceof DOMPointReadOnly && !is_array($raw)) {
				throw new InvalidArgumentException(sprintf('DOMQuadInit::%s must be a DOMPointReadOnly or array.', $key));
			}
			$corners[] = $raw;
		}

		return new self($corners[0], $corners[1], $corners[2], $corners[3]);
	}

	public function getP1(): DOMPoint
	{
		return $this->p1Point;
	}

	public function getP2(): DOMPoint
	{
		return $this->p2Point;
	}

	public function getP3(): DOMPoint
	{
		return $this->p3Point;
	}

	public function getP4(): DOMPoint
	{
		return $this->p4Point;
	}

	public function getBounds(): DOMRect
	{
		$xs = [$this->p1Point->getX(), $this->p2Point->getX(), $this->p3Point->getX(), $this->p4Point->getX()];
		$ys = [$this->p1Point->getY(), $this->p2Point->getY(), $this->p3Point->getY(), $this->p4Point->getY()];
		$left = min($xs);
		$top = min($ys);
		$right = max($xs);
		$bottom = max($ys);
		$width = DOMMatrixReadOnly::assertFiniteInternal($right - $left, 'bounds.width');
		$height = DOMMatrixReadOnly::assertFiniteInternal($bottom - $top, 'bounds.height');

		return new DOMRect($left, $top, $width, $height);
	}

	/**
	 * @return array{
	 *   p1: array{x: float, y: float, z: float, w: float},
	 *   p2: array{x: float, y: float, z: float, w: float},
	 *   p3: array{x: float, y: float, z: float, w: float},
	 *   p4: array{x: float, y: float, z: float, w: float}
	 * }
	 */
	public function toJSON(): array
	{
		return [
			'p1' => $this->p1Point->toJSON(),
			'p2' => $this->p2Point->toJSON(),
			'p3' => $this->p3Point->toJSON(),
			'p4' => $this->p4Point->toJSON(),
		];
	}

	public function __clone(): void
	{
		$this->p1Point = clone $this->p1Point;
		$this->p2Point = clone $this->p2Point;
		$this->p3Point = clone $this->p3Point;
		$this->p4Point = clone $this->p4Point;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'p1' => $this->p1Point,
			'p2' => $this->p2Point,
			'p3' => $this->p3Point,
			'p4' => $this->p4Point,
			default => throw new Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	public function __isset(string $name): bool
	{
		return $name === 'p1' || $name === 'p2' || $name === 'p3' || $name === 'p4';
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
