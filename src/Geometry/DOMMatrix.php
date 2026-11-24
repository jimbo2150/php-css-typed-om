<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Geometry;

use Error;
use InvalidArgumentException;

/**
 * Mutable 4x4 column-major transformation matrix (W3C Geometry Interfaces Level 1).
 *
 * @property float $a
 * @property float $b
 * @property float $c
 * @property float $d
 * @property float $e
 * @property float $f
 * @property float $m11
 * @property float $m12
 * @property float $m13
 * @property float $m14
 * @property float $m21
 * @property float $m22
 * @property float $m23
 * @property float $m24
 * @property float $m31
 * @property float $m32
 * @property float $m33
 * @property float $m34
 * @property float $m41
 * @property float $m42
 * @property float $m43
 * @property float $m44
 * @see https://developer.mozilla.org/en-US/docs/Web/API/DOMMatrix
 */
class DOMMatrix extends DOMMatrixReadOnly
{
	public function setA(float $value): void { $this->setElement('a', $value); }
	public function setB(float $value): void { $this->setElement('b', $value); }
	public function setC(float $value): void { $this->setElement('c', $value); }
	public function setD(float $value): void { $this->setElement('d', $value); }
	public function setE(float $value): void { $this->setElement('e', $value); }
	public function setF(float $value): void { $this->setElement('f', $value); }

	public function setM11(float $value): void { $this->setElement('m11', $value); }
	public function setM12(float $value): void { $this->setElement('m12', $value); }
	public function setM13(float $value): void { $this->setElement('m13', $value); }
	public function setM14(float $value): void { $this->setElement('m14', $value); }
	public function setM21(float $value): void { $this->setElement('m21', $value); }
	public function setM22(float $value): void { $this->setElement('m22', $value); }
	public function setM23(float $value): void { $this->setElement('m23', $value); }
	public function setM24(float $value): void { $this->setElement('m24', $value); }
	public function setM31(float $value): void { $this->setElement('m31', $value); }
	public function setM32(float $value): void { $this->setElement('m32', $value); }
	public function setM33(float $value): void { $this->setElement('m33', $value); }
	public function setM34(float $value): void { $this->setElement('m34', $value); }
	public function setM41(float $value): void { $this->setElement('m41', $value); }
	public function setM42(float $value): void { $this->setElement('m42', $value); }
	public function setM43(float $value): void { $this->setElement('m43', $value); }
	public function setM44(float $value): void { $this->setElement('m44', $value); }

	/** @param DOMMatrixReadOnly|list<int|float>|array<string, mixed> $other */
	public function multiplySelf(DOMMatrixReadOnly|array $other): self
	{
		$rhs = $other instanceof DOMMatrixReadOnly ? $other : new DOMMatrixReadOnly($other);
		$this->elements = self::multiplyElements($this->elements, $rhs->elements, $this->is2D, $rhs->is2D);
		if (!$rhs->is2D) {
			$this->is2D = false;
		}

		return $this;
	}

	/** @param DOMMatrixReadOnly|list<int|float>|array<string, mixed> $other */
	public function preMultiplySelf(DOMMatrixReadOnly|array $other): self
	{
		$lhs = $other instanceof DOMMatrixReadOnly ? $other : new DOMMatrixReadOnly($other);
		$this->elements = self::multiplyElements($lhs->elements, $this->elements, $lhs->is2D, $this->is2D);
		if (!$lhs->is2D) {
			$this->is2D = false;
		}

		return $this;
	}

	public function translateSelf(float $tx = 0.0, float $ty = 0.0, float $tz = 0.0): self
	{
		$tx = self::assertFinite($tx, 'tx');
		$ty = self::assertFinite($ty, 'ty');
		$tz = self::assertFinite($tz, 'tz');
		$next = $this->elements;
		$nextIs2D = $this->is2D;
		self::applyTranslateElements($next, $nextIs2D, $tx, $ty, $tz);
		$this->elements = $next;
		$this->is2D = $nextIs2D;

		return $this;
	}

	public function scaleSelf(
		float $scaleX = 1.0,
		?float $scaleY = null,
		float $scaleZ = 1.0,
		float $originX = 0.0,
		float $originY = 0.0,
		float $originZ = 0.0,
	): self {
		$scaleX = self::assertFinite($scaleX, 'scaleX');
		$scaleY = self::assertFinite($scaleY ?? $scaleX, 'scaleY');
		$scaleZ = self::assertFinite($scaleZ, 'scaleZ');
		$originX = self::assertFinite($originX, 'originX');
		$originY = self::assertFinite($originY, 'originY');
		$originZ = self::assertFinite($originZ, 'originZ');

		$next = $this->elements;
		$nextIs2D = $this->is2D;
		$hasOrigin = $originX !== 0.0 || $originY !== 0.0 || $originZ !== 0.0;
		if ($hasOrigin) {
			self::applyTranslateElements($next, $nextIs2D, $originX, $originY, $originZ);
		}

		if ($nextIs2D && $scaleZ === 1.0) {
			$next[0] = self::assertFinite($next[0] * $scaleX, 'm11');
			$next[1] = self::assertFinite($next[1] * $scaleX, 'm12');
			$next[4] = self::assertFinite($next[4] * $scaleY, 'm21');
			$next[5] = self::assertFinite($next[5] * $scaleY, 'm22');
		} else {
			for ($r = 0; $r < 4; $r++) {
				$next[$r] = self::assertFinite($next[$r] * $scaleX, 'scale');
				$next[4 + $r] = self::assertFinite($next[4 + $r] * $scaleY, 'scale');
				$next[8 + $r] = self::assertFinite($next[8 + $r] * $scaleZ, 'scale');
			}
		}

		if ($hasOrigin) {
			self::applyTranslateElements($next, $nextIs2D, -$originX, -$originY, -$originZ);
		}
		if ($scaleZ !== 1.0 || $originZ !== 0.0) {
			$nextIs2D = false;
		}

		$this->elements = $next;
		$this->is2D = $nextIs2D;

		return $this;
	}

	public function scale3dSelf(
		float $scale = 1.0,
		float $originX = 0.0,
		float $originY = 0.0,
		float $originZ = 0.0,
	): self {
		$scale = self::assertFinite($scale, 'scale');
		$originX = self::assertFinite($originX, 'originX');
		$originY = self::assertFinite($originY, 'originY');
		$originZ = self::assertFinite($originZ, 'originZ');

		$this->scaleSelf($scale, $scale, $scale, $originX, $originY, $originZ);
		if ($scale !== 1.0) {
			$this->is2D = false;
		}

		return $this;
	}

	public function rotateSelf(float $rotX = 0.0, ?float $rotY = null, ?float $rotZ = null): self
	{
		$rotX = self::assertFinite($rotX, 'rotX');
		if ($rotY === null && $rotZ === null) {
			// Per Geometry Interfaces Level 1, 1-arg rotate(angle) rotates around the Z-axis and keeps is2D.
			$rad = deg2rad($rotX);
			$cos = cos($rad);
			$sin = sin($rad);
			$next = $this->elements;
			$rows = $this->is2D ? 2 : 4;
			for ($r = 0; $r < $rows; $r++) {
				$c0 = $next[$r];
				$c1 = $next[4 + $r];
				$next[$r] = self::assertFinite($c0 * $cos + $c1 * $sin, 'rotate');
				$next[4 + $r] = self::assertFinite(-$c0 * $sin + $c1 * $cos, 'rotate');
			}
			$this->elements = $next;
			return $this;
		}

		$rotY = self::assertFinite($rotY ?? 0.0, 'rotY');
		$rotZ = self::assertFinite($rotZ ?? 0.0, 'rotZ');

		$next = $this->elements;
		$nextIs2D = $this->is2D;
		if ($rotZ !== 0.0) {
			self::applyRotateAxisAngleElements($next, $nextIs2D, 0.0, 0.0, 1.0, $rotZ);
		}
		if ($rotY !== 0.0) {
			self::applyRotateAxisAngleElements($next, $nextIs2D, 0.0, 1.0, 0.0, $rotY);
		}
		if ($rotX !== 0.0) {
			self::applyRotateAxisAngleElements($next, $nextIs2D, 1.0, 0.0, 0.0, $rotX);
		}

		$this->elements = $next;
		$this->is2D = $nextIs2D;

		return $this;
	}

	public function rotateFromVectorSelf(float $x = 0.0, float $y = 0.0): self
	{
		$x = self::assertFinite($x, 'x');
		$y = self::assertFinite($y, 'y');
		if ($x === 0.0 && $y === 0.0) {
			return $this;
		}

		return $this->rotateSelf(rad2deg(atan2($y, $x)));
	}

	public function rotateAxisAngleSelf(float $x = 0.0, float $y = 0.0, float $z = 0.0, float $angle = 0.0): self
	{
		$next = $this->elements;
		$nextIs2D = $this->is2D;
		self::applyRotateAxisAngleElements($next, $nextIs2D, $x, $y, $z, $angle);
		$this->elements = $next;
		$this->is2D = $nextIs2D;

		return $this;
	}

	public function skewXSelf(float $sx = 0.0): self
	{
		$tan = self::assertFinite(tan(deg2rad(self::assertFinite($sx, 'sx'))), 'tan(sx)');
		$next = $this->elements;
		$rows = $this->is2D ? 2 : 4;
		for ($r = 0; $r < $rows; $r++) {
			$next[4 + $r] = self::assertFinite($next[4 + $r] + $next[$r] * $tan, 'skewX');
		}
		$this->elements = $next;

		return $this;
	}

	public function skewYSelf(float $sy = 0.0): self
	{
		$tan = self::assertFinite(tan(deg2rad(self::assertFinite($sy, 'sy'))), 'tan(sy)');
		$next = $this->elements;
		$rows = $this->is2D ? 2 : 4;
		for ($r = 0; $r < $rows; $r++) {
			$next[$r] = self::assertFinite($next[$r] + $next[4 + $r] * $tan, 'skewY');
		}
		$this->elements = $next;

		return $this;
	}

	/** @param array<int, float> $elements */
	private static function applyTranslateElements(array &$elements, bool &$is2D, float $tx, float $ty, float $tz): void
	{
		if ($is2D && $tz === 0.0) {
			$elements[12] = self::assertFinite($elements[0] * $tx + $elements[4] * $ty + $elements[12], 'm41');
			$elements[13] = self::assertFinite($elements[1] * $tx + $elements[5] * $ty + $elements[13], 'm42');
			return;
		}

		$elements[12] = self::assertFinite($elements[0] * $tx + $elements[4] * $ty + $elements[8] * $tz + $elements[12], 'm41');
		$elements[13] = self::assertFinite($elements[1] * $tx + $elements[5] * $ty + $elements[9] * $tz + $elements[13], 'm42');
		$elements[14] = self::assertFinite($elements[2] * $tx + $elements[6] * $ty + $elements[10] * $tz + $elements[14], 'm43');
		$elements[15] = self::assertFinite($elements[3] * $tx + $elements[7] * $ty + $elements[11] * $tz + $elements[15], 'm44');
		if ($tz !== 0.0) {
			$is2D = false;
		}
	}

	/** @param array<int, float> $elements */
	private static function applyRotateAxisAngleElements(
		array &$elements,
		bool &$is2D,
		float $x,
		float $y,
		float $z,
		float $angle,
	): void {
		$x = self::assertFinite($x, 'x');
		$y = self::assertFinite($y, 'y');
		$z = self::assertFinite($z, 'z');
		$angle = self::assertFinite($angle, 'angle');

		$maxAxis = max(abs($x), abs($y), abs($z));
		if ($maxAxis === 0.0) {
			return;
		}
		$nx = $x / $maxAxis;
		$ny = $y / $maxAxis;
		$nz = $z / $maxAxis;
		$length = sqrt($nx * $nx + $ny * $ny + $nz * $nz);
		$x = $nx / $length;
		$y = $ny / $length;
		$z = $nz / $length;

		$rad = deg2rad($angle);
		$cos = cos($rad);
		$sin = sin($rad);
		$t = 1.0 - $cos;

		$rotation = self::identityElements();
		$rotation[0] = $t * $x * $x + $cos;
		$rotation[1] = $t * $x * $y + $sin * $z;
		$rotation[2] = $t * $x * $z - $sin * $y;
		$rotation[4] = $t * $x * $y - $sin * $z;
		$rotation[5] = $t * $y * $y + $cos;
		$rotation[6] = $t * $y * $z + $sin * $x;
		$rotation[8] = $t * $x * $z + $sin * $y;
		$rotation[9] = $t * $y * $z - $sin * $x;
		$rotation[10] = $t * $z * $z + $cos;

		$elements = self::multiplyElements($elements, $rotation);
		if ($x !== 0.0 || $y !== 0.0) {
			$is2D = false;
		}
	}

	/**
	 * Atomically inverts this matrix in place.
	 *
	 * @throws InvalidArgumentException When the matrix is singular (non-invertible)
	 */
	public function invertSelf(): self
	{
		if ($this->is2D) {
			$a = $this->elements[0];
			$b = $this->elements[1];
			$c = $this->elements[4];
			$d = $this->elements[5];
			$e = $this->elements[12];
			$f = $this->elements[13];

			$det = $a * $d - $b * $c;
			if (!is_finite($det) || $det === 0.0) {
				throw new InvalidArgumentException('Matrix is singular and cannot be inverted.');
			}

			$invDet = 1.0 / $det;
			$next = self::identityElements();
			$next[0] = self::assertFinite($d * $invDet, 'inverse.a');
			$next[1] = self::assertFinite(-$b * $invDet, 'inverse.b');
			$next[4] = self::assertFinite(-$c * $invDet, 'inverse.c');
			$next[5] = self::assertFinite($a * $invDet, 'inverse.d');
			$next[12] = self::assertFinite(($c * $f - $d * $e) * $invDet, 'inverse.e');
			$next[13] = self::assertFinite(($b * $e - $a * $f) * $invDet, 'inverse.f');

			$this->elements = $next;
			return $this;
		}

		$m = $this->elements;
		$inv = array_fill(0, 16, 0.0);

		$inv[0] = $m[5] * $m[10] * $m[15] - $m[5] * $m[11] * $m[14] - $m[9] * $m[6] * $m[15]
			+ $m[9] * $m[7] * $m[14] + $m[13] * $m[6] * $m[11] - $m[13] * $m[7] * $m[10];
		$inv[4] = -$m[4] * $m[10] * $m[15] + $m[4] * $m[11] * $m[14] + $m[8] * $m[6] * $m[15]
			- $m[8] * $m[7] * $m[14] - $m[12] * $m[6] * $m[11] + $m[12] * $m[7] * $m[10];
		$inv[8] = $m[4] * $m[9] * $m[15] - $m[4] * $m[11] * $m[13] - $m[8] * $m[5] * $m[15]
			+ $m[8] * $m[7] * $m[13] + $m[12] * $m[5] * $m[11] - $m[12] * $m[7] * $m[9];
		$inv[12] = -$m[4] * $m[9] * $m[14] + $m[4] * $m[10] * $m[13] + $m[8] * $m[5] * $m[14]
			- $m[8] * $m[6] * $m[13] - $m[12] * $m[5] * $m[10] + $m[12] * $m[6] * $m[9];

		$inv[1] = -$m[1] * $m[10] * $m[15] + $m[1] * $m[11] * $m[14] + $m[9] * $m[2] * $m[15]
			- $m[9] * $m[3] * $m[14] - $m[13] * $m[2] * $m[11] + $m[13] * $m[3] * $m[10];
		$inv[5] = $m[0] * $m[10] * $m[15] - $m[0] * $m[11] * $m[14] - $m[8] * $m[2] * $m[15]
			+ $m[8] * $m[3] * $m[14] + $m[12] * $m[2] * $m[11] - $m[12] * $m[3] * $m[10];
		$inv[9] = -$m[0] * $m[9] * $m[15] + $m[0] * $m[11] * $m[13] + $m[8] * $m[1] * $m[15]
			- $m[8] * $m[3] * $m[13] - $m[12] * $m[1] * $m[11] + $m[12] * $m[3] * $m[9];
		$inv[13] = $m[0] * $m[9] * $m[14] - $m[0] * $m[10] * $m[13] - $m[8] * $m[1] * $m[14]
			+ $m[8] * $m[2] * $m[13] + $m[12] * $m[1] * $m[10] - $m[12] * $m[2] * $m[9];

		$inv[2] = $m[1] * $m[6] * $m[15] - $m[1] * $m[7] * $m[14] - $m[5] * $m[2] * $m[15]
			+ $m[5] * $m[3] * $m[14] + $m[13] * $m[2] * $m[7] - $m[13] * $m[3] * $m[6];
		$inv[6] = -$m[0] * $m[6] * $m[15] + $m[0] * $m[7] * $m[14] + $m[4] * $m[2] * $m[15]
			- $m[4] * $m[3] * $m[14] - $m[12] * $m[2] * $m[7] + $m[12] * $m[3] * $m[6];
		$inv[10] = $m[0] * $m[5] * $m[15] - $m[0] * $m[7] * $m[13] - $m[4] * $m[1] * $m[15]
			+ $m[4] * $m[3] * $m[13] + $m[12] * $m[1] * $m[7] - $m[12] * $m[3] * $m[5];
		$inv[14] = -$m[0] * $m[5] * $m[14] + $m[0] * $m[6] * $m[13] + $m[4] * $m[1] * $m[14]
			- $m[4] * $m[2] * $m[13] - $m[12] * $m[1] * $m[6] + $m[12] * $m[2] * $m[5];

		$inv[3] = -$m[1] * $m[6] * $m[11] + $m[1] * $m[7] * $m[10] + $m[5] * $m[2] * $m[11]
			- $m[5] * $m[3] * $m[10] - $m[9] * $m[2] * $m[7] + $m[9] * $m[3] * $m[6];
		$inv[7] = $m[0] * $m[6] * $m[11] - $m[0] * $m[7] * $m[10] - $m[4] * $m[2] * $m[11]
			+ $m[4] * $m[3] * $m[10] + $m[8] * $m[2] * $m[7] - $m[8] * $m[3] * $m[6];
		$inv[11] = -$m[0] * $m[5] * $m[11] + $m[0] * $m[7] * $m[9] + $m[4] * $m[1] * $m[11]
			- $m[4] * $m[3] * $m[9] - $m[8] * $m[1] * $m[7] + $m[8] * $m[3] * $m[5];
		$inv[15] = $m[0] * $m[5] * $m[10] - $m[0] * $m[6] * $m[9] - $m[4] * $m[1] * $m[10]
			+ $m[4] * $m[2] * $m[9] + $m[8] * $m[1] * $m[6] - $m[8] * $m[2] * $m[5];

		$det = $m[0] * $inv[0] + $m[1] * $inv[4] + $m[2] * $inv[8] + $m[3] * $inv[12];
		if (!is_finite($det) || $det === 0.0) {
			throw new InvalidArgumentException('Matrix is singular and cannot be inverted.');
		}

		$invDet = 1.0 / $det;
		$next = [];
		for ($i = 0; $i < 16; $i++) {
			$next[] = self::assertFinite($inv[$i] * $invDet, 'inverse[' . $i . ']');
		}

		$this->elements = $next;
		return $this;
	}

	public function setMatrixValue(string $transformList): self
	{
		[$elements, $is2D] = self::parseTransformString($transformList);
		$this->elements = $elements;
		$this->is2D = $is2D;

		return $this;
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (!is_int($offset) || $offset < 0 || $offset >= 16) {
			throw new InvalidArgumentException('Matrix element index must be an integer between 0 and 15.');
		}
		$float = self::assertFinite($value, 'element[' . $offset . ']');
		$this->elements[$offset] = $float;
		if (!self::elementsAre2D($this->elements)) {
			$this->is2D = false;
		}
	}

	public function offsetUnset(mixed $offset): void
	{
		throw new InvalidArgumentException('Cannot unset DOMMatrix elements.');
	}

	public function __set(string $name, mixed $value): void
	{
		if (isset(self::ELEMENT_INDEX[$name]) && (is_int($value) || is_float($value))) {
			$this->setElement($name, (float) $value);
			return;
		}

		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	private function setElement(string $name, float $value): void
	{
		$float = self::assertFinite($value, $name);
		$index = self::ELEMENT_INDEX[$name];
		$this->elements[$index] = $float;
		if (!self::elementsAre2D($this->elements)) {
			$this->is2D = false;
		}
	}
}
