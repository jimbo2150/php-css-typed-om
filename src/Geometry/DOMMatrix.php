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
	public function setA(float $value): void { $this->setElementAt(0, $value, 'a', true); }
	public function setB(float $value): void { $this->setElementAt(1, $value, 'b', true); }
	public function setC(float $value): void { $this->setElementAt(4, $value, 'c', true); }
	public function setD(float $value): void { $this->setElementAt(5, $value, 'd', true); }
	public function setE(float $value): void { $this->setElementAt(12, $value, 'e', true); }
	public function setF(float $value): void { $this->setElementAt(13, $value, 'f', true); }

	public function setM11(float $value): void { $this->setElementAt(0, $value, 'm11', true); }
	public function setM12(float $value): void { $this->setElementAt(1, $value, 'm12', true); }
	public function setM13(float $value): void { $this->setElementAt(2, $value, 'm13', false); }
	public function setM14(float $value): void { $this->setElementAt(3, $value, 'm14', false); }
	public function setM21(float $value): void { $this->setElementAt(4, $value, 'm21', true); }
	public function setM22(float $value): void { $this->setElementAt(5, $value, 'm22', true); }
	public function setM23(float $value): void { $this->setElementAt(6, $value, 'm23', false); }
	public function setM24(float $value): void { $this->setElementAt(7, $value, 'm24', false); }
	public function setM31(float $value): void { $this->setElementAt(8, $value, 'm31', false); }
	public function setM32(float $value): void { $this->setElementAt(9, $value, 'm32', false); }
	public function setM33(float $value): void { $this->setElementAt(10, $value, 'm33', false); }
	public function setM34(float $value): void { $this->setElementAt(11, $value, 'm34', false); }
	public function setM41(float $value): void { $this->setElementAt(12, $value, 'm41', true); }
	public function setM42(float $value): void { $this->setElementAt(13, $value, 'm42', true); }
	public function setM43(float $value): void { $this->setElementAt(14, $value, 'm43', false); }
	public function setM44(float $value): void { $this->setElementAt(15, $value, 'm44', false); }

	/** @internal */
	public function mark3DInternal(): void
	{
		$this->is2D = false;
	}

	/**
	 * @internal Multiplies by trusted precomputed 16-element matrix arrays in place.
	 * @param list<float> $rhsElements
	 */
	public function multiplyElementsInternal(array $rhsElements, bool $rhsIs2D): void
	{
		$this->elements = self::multiplyElements($this->elements, $rhsElements, $this->is2D, $rhsIs2D);
		if (!$rhsIs2D) {
			$this->is2D = false;
		}
	}

	/**
	 * @internal Applies a 2D affine matrix `[a, b, c, d, e, f]` directly without intermediate matrix allocation.
	 */
	public function multiplyAffine2DInternal(float $a, float $b, float $c, float $d, float $e, float $f): void
	{
		$l = $this->elements;
		if ($this->is2D) {
			$m11 = $l[0] * $a + $l[4] * $b;
			$m12 = $l[1] * $a + $l[5] * $b;
			$m21 = $l[0] * $c + $l[4] * $d;
			$m22 = $l[1] * $c + $l[5] * $d;
			$m41 = $l[0] * $e + $l[4] * $f + $l[12];
			$m42 = $l[1] * $e + $l[5] * $f + $l[13];
			if (!is_finite($m11) || !is_finite($m12) || !is_finite($m21) || !is_finite($m22) || !is_finite($m41) || !is_finite($m42)) {
				throw new InvalidArgumentException('Matrix matrix product must be finite.');
			}
			$this->elements = [
				$m11 === 0.0 ? 0.0 : $m11,
				$m12 === 0.0 ? 0.0 : $m12,
				0.0,
				0.0,
				$m21 === 0.0 ? 0.0 : $m21,
				$m22 === 0.0 ? 0.0 : $m22,
				0.0,
				0.0,
				0.0,
				0.0,
				1.0,
				0.0,
				$m41 === 0.0 ? 0.0 : $m41,
				$m42 === 0.0 ? 0.0 : $m42,
				0.0,
				1.0,
			];
			return;
		}

		for ($r = 0; $r < 4; $r++) {
			$l0 = $l[$r];
			$l1 = $l[4 + $r];
			$this->elements[$r] = self::assertFiniteFloat($l0 * $a + $l1 * $b, 'matrix product');
			$this->elements[4 + $r] = self::assertFiniteFloat($l0 * $c + $l1 * $d, 'matrix product');
			$this->elements[12 + $r] = self::assertFiniteFloat($l0 * $e + $l1 * $f + $l[12 + $r], 'matrix product');
		}
	}

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
		$tx = self::assertFiniteFloat($tx, 'tx');
		$ty = self::assertFiniteFloat($ty, 'ty');
		$tz = self::assertFiniteFloat($tz, 'tz');
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
		$scaleX = self::assertFiniteFloat($scaleX, 'scaleX');
		$scaleY = self::assertFiniteFloat($scaleY ?? $scaleX, 'scaleY');
		$scaleZ = self::assertFiniteFloat($scaleZ, 'scaleZ');
		$originX = self::assertFiniteFloat($originX, 'originX');
		$originY = self::assertFiniteFloat($originY, 'originY');
		$originZ = self::assertFiniteFloat($originZ, 'originZ');

		$next = $this->elements;
		$nextIs2D = $this->is2D;
		$hasOrigin = $originX !== 0.0 || $originY !== 0.0 || $originZ !== 0.0;
		if ($hasOrigin) {
			self::applyTranslateElements($next, $nextIs2D, $originX, $originY, $originZ);
		}

		if ($nextIs2D && $scaleZ === 1.0) {
			$next[0] = self::assertFiniteFloat($next[0] * $scaleX, 'm11');
			$next[1] = self::assertFiniteFloat($next[1] * $scaleX, 'm12');
			$next[4] = self::assertFiniteFloat($next[4] * $scaleY, 'm21');
			$next[5] = self::assertFiniteFloat($next[5] * $scaleY, 'm22');
		} else {
			for ($r = 0; $r < 4; $r++) {
				$next[$r] = self::assertFiniteFloat($next[$r] * $scaleX, 'scale');
				$next[4 + $r] = self::assertFiniteFloat($next[4 + $r] * $scaleY, 'scale');
				$next[8 + $r] = self::assertFiniteFloat($next[8 + $r] * $scaleZ, 'scale');
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
		$scale = self::assertFiniteFloat($scale, 'scale');
		$originX = self::assertFiniteFloat($originX, 'originX');
		$originY = self::assertFiniteFloat($originY, 'originY');
		$originZ = self::assertFiniteFloat($originZ, 'originZ');

		$this->scaleSelf($scale, $scale, $scale, $originX, $originY, $originZ);
		if ($scale !== 1.0) {
			$this->is2D = false;
		}

		return $this;
	}

	public function rotateSelf(float $rotX = 0.0, ?float $rotY = null, ?float $rotZ = null): self
	{
		$rotX = self::assertFiniteFloat($rotX, 'rotX');
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
				$next[$r] = self::assertFiniteFloat($c0 * $cos + $c1 * $sin, 'rotate');
				$next[4 + $r] = self::assertFiniteFloat(-$c0 * $sin + $c1 * $cos, 'rotate');
			}
			$this->elements = $next;
			return $this;
		}

		$rotY = self::assertFiniteFloat($rotY ?? 0.0, 'rotY');
		$rotZ = self::assertFiniteFloat($rotZ ?? 0.0, 'rotZ');

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
		$x = self::assertFiniteFloat($x, 'x');
		$y = self::assertFiniteFloat($y, 'y');
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
		$tan = self::assertFiniteFloat(tan(deg2rad(self::assertFiniteFloat($sx, 'sx'))), 'tan(sx)');
		$next = $this->elements;
		$rows = $this->is2D ? 2 : 4;
		for ($r = 0; $r < $rows; $r++) {
			$next[4 + $r] = self::assertFiniteFloat($next[4 + $r] + $next[$r] * $tan, 'skewX');
		}
		$this->elements = $next;

		return $this;
	}

	public function skewYSelf(float $sy = 0.0): self
	{
		$tan = self::assertFiniteFloat(tan(deg2rad(self::assertFiniteFloat($sy, 'sy'))), 'tan(sy)');
		$next = $this->elements;
		$rows = $this->is2D ? 2 : 4;
		for ($r = 0; $r < $rows; $r++) {
			$next[$r] = self::assertFiniteFloat($next[$r] + $next[4 + $r] * $tan, 'skewY');
		}
		$this->elements = $next;

		return $this;
	}

	/** @param array<int, float> $elements */
	private static function applyTranslateElements(array &$elements, bool &$is2D, float $tx, float $ty, float $tz): void
	{
		if ($is2D && $tz === 0.0) {
			$elements[12] = self::assertFiniteFloat($elements[0] * $tx + $elements[4] * $ty + $elements[12], 'm41');
			$elements[13] = self::assertFiniteFloat($elements[1] * $tx + $elements[5] * $ty + $elements[13], 'm42');
			return;
		}

		$elements[12] = self::assertFiniteFloat($elements[0] * $tx + $elements[4] * $ty + $elements[8] * $tz + $elements[12], 'm41');
		$elements[13] = self::assertFiniteFloat($elements[1] * $tx + $elements[5] * $ty + $elements[9] * $tz + $elements[13], 'm42');
		$elements[14] = self::assertFiniteFloat($elements[2] * $tx + $elements[6] * $ty + $elements[10] * $tz + $elements[14], 'm43');
		$elements[15] = self::assertFiniteFloat($elements[3] * $tx + $elements[7] * $ty + $elements[11] * $tz + $elements[15], 'm44');
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
		$x = self::assertFiniteFloat($x, 'x');
		$y = self::assertFiniteFloat($y, 'y');
		$z = self::assertFiniteFloat($z, 'z');
		$angle = self::assertFiniteFloat($angle, 'angle');

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
	 * Inverts this matrix in place per W3C Geometry Interfaces Level 1.
	 * Singular or non-invertible matrices become all-`NaN` with `is2D = false`.
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
				$this->elements = array_fill(0, 16, NAN);
				$this->is2D = false;
				return $this;
			}

			$invDet = 1.0 / $det;
			$m11 = $d * $invDet;
			$m12 = -$b * $invDet;
			$m21 = -$c * $invDet;
			$m22 = $a * $invDet;
			$m41 = ($c * $f - $d * $e) * $invDet;
			$m42 = ($b * $e - $a * $f) * $invDet;
			if (!is_finite($m11) || !is_finite($m12) || !is_finite($m21) || !is_finite($m22) || !is_finite($m41) || !is_finite($m42)) {
				$this->elements = array_fill(0, 16, NAN);
				$this->is2D = false;
				return $this;
			}

			$next = self::identityElements();
			$next[0] = $m11 === 0.0 ? 0.0 : $m11;
			$next[1] = $m12 === 0.0 ? 0.0 : $m12;
			$next[4] = $m21 === 0.0 ? 0.0 : $m21;
			$next[5] = $m22 === 0.0 ? 0.0 : $m22;
			$next[12] = $m41 === 0.0 ? 0.0 : $m41;
			$next[13] = $m42 === 0.0 ? 0.0 : $m42;
			$this->elements = $next;
			return $this;
		}

		$m = $this->elements;
		// 12 reusable 2x2 sub-determinants across row pairs (0,1) and (2,3)
		$s0 = $m[0] * $m[5] - $m[1] * $m[4];
		$s1 = $m[0] * $m[9] - $m[1] * $m[8];
		$s2 = $m[0] * $m[13] - $m[1] * $m[12];
		$s3 = $m[4] * $m[9] - $m[5] * $m[8];
		$s4 = $m[4] * $m[13] - $m[5] * $m[12];
		$s5 = $m[8] * $m[13] - $m[9] * $m[12];

		$c5 = $m[10] * $m[15] - $m[11] * $m[14];
		$c4 = $m[6] * $m[15] - $m[7] * $m[14];
		$c3 = $m[6] * $m[11] - $m[7] * $m[10];
		$c2 = $m[2] * $m[15] - $m[3] * $m[14];
		$c1 = $m[2] * $m[11] - $m[3] * $m[10];
		$c0 = $m[2] * $m[7] - $m[3] * $m[6];

		$det = $s0 * $c5 - $s1 * $c4 + $s2 * $c3 + $s3 * $c2 - $s4 * $c1 + $s5 * $c0;
		if (!is_finite($det) || $det === 0.0) {
			$this->elements = array_fill(0, 16, NAN);
			$this->is2D = false;
			return $this;
		}

		$inv = [
			$m[5] * $c5 - $m[9] * $c4 + $m[13] * $c3,
			-$m[1] * $c5 + $m[9] * $c2 - $m[13] * $c1,
			$m[1] * $c4 - $m[5] * $c2 + $m[13] * $c0,
			-$m[1] * $c3 + $m[5] * $c1 - $m[9] * $c0,
			-$m[4] * $c5 + $m[8] * $c4 - $m[12] * $c3,
			$m[0] * $c5 - $m[8] * $c2 + $m[12] * $c1,
			-$m[0] * $c4 + $m[4] * $c2 - $m[12] * $c0,
			$m[0] * $c3 - $m[4] * $c1 + $m[8] * $c0,
			$m[15] * $s3 - $m[11] * $s4 + $m[7] * $s5,
			-$m[15] * $s1 + $m[11] * $s2 - $m[3] * $s5,
			$m[15] * $s0 - $m[7] * $s2 + $m[3] * $s4,
			-$m[11] * $s0 + $m[7] * $s1 - $m[3] * $s3,
			-$m[14] * $s3 + $m[10] * $s4 - $m[6] * $s5,
			$m[14] * $s1 - $m[10] * $s2 + $m[2] * $s5,
			-$m[14] * $s0 + $m[6] * $s2 - $m[2] * $s4,
			$m[10] * $s0 - $m[6] * $s1 + $m[2] * $s3,
		];

		$invDet = 1.0 / $det;
		for ($i = 0; $i < 16; $i++) {
			$val = $inv[$i] * $invDet;
			if (!is_finite($val)) {
				$this->elements = array_fill(0, 16, NAN);
				$this->is2D = false;
				return $this;
			}
			$inv[$i] = $val === 0.0 ? 0.0 : $val;
		}

		$this->elements = $inv;
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
		if ($this->is2D && !self::elementsAre2D($this->elements)) {
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
			$index = self::ELEMENT_INDEX[$name];
			$is2DSlot = $index === 0 || $index === 1 || $index === 4 || $index === 5 || $index === 12 || $index === 13;
			$this->setElementAt($index, (float) $value, $name, $is2DSlot);
			return;
		}

		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	private function setElementAt(int $index, float $value, string $label, bool $is2DSlot): void
	{
		$float = self::assertFiniteFloat($value, $label);
		$this->elements[$index] = $float;
		if (!$is2DSlot && $this->is2D) {
			$expected = ($index === 10 || $index === 15) ? 1.0 : 0.0;
			if ($float !== $expected) {
				$this->is2D = false;
			}
		}
	}
}
