<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\DOM;

/**
 * Represents a 4x4 homogeneous matrix for 2D and 3D transformations.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/DOMMatrix
 */
class DOMMatrix extends DOMMatrixReadOnly
{
	public function __construct($init = null)
	{
		parent::__construct($init);
	}

	public function __set(string $name, $value): void
	{
		$value = (float) $value;
		switch ($name) {
			case 'a': $this->_matrix[0] = $value;
				break;
			case 'b': $this->_matrix[1] = $value;
				break;
			case 'c': $this->_matrix[4] = $value;
				break;
			case 'd': $this->_matrix[5] = $value;
				break;
			case 'e': $this->_matrix[12] = $value;
				break;
			case 'f': $this->_matrix[13] = $value;
				break;
			case 'm11': $this->_matrix[0] = $value;
				break;
			case 'm12': $this->_matrix[1] = $value;
				break;
			case 'm13': $this->_matrix[2] = $value;
				break;
			case 'm14': $this->_matrix[3] = $value;
				break;
			case 'm21': $this->_matrix[4] = $value;
				break;
			case 'm22': $this->_matrix[5] = $value;
				break;
			case 'm23': $this->_matrix[6] = $value;
				break;
			case 'm24': $this->_matrix[7] = $value;
				break;
			case 'm31': $this->_matrix[8] = $value;
				break;
			case 'm32': $this->_matrix[9] = $value;
				break;
			case 'm33': $this->_matrix[10] = $value;
				break;
			case 'm34': $this->_matrix[11] = $value;
				break;
			case 'm41': $this->_matrix[12] = $value;
				break;
			case 'm42': $this->_matrix[13] = $value;
				break;
			case 'm43': $this->_matrix[14] = $value;
				break;
			case 'm44': $this->_matrix[15] = $value;
				break;
			default: throw new \InvalidArgumentException('Undefined property: '.$name);
		}
	}

	public function invertSelf(): DOMMatrix
	{
		$invertedMatrix = $this->inverse();
		$this->_matrix = $invertedMatrix->_matrix;

		return $this;
	}

	public function multiplySelf(DOMMatrixReadOnly $other): DOMMatrix
	{
		$multipliedMatrix = $this->multiply($other);
		$this->_matrix = $multipliedMatrix->_matrix;

		return $this;
	}

	public function preMultiplySelf(DOMMatrixReadOnly $other): DOMMatrix
	{
		$result = new DOMMatrix();
		$a = $other->_matrix;
		$b = $this->_matrix;
		$c = [];

		for ($i = 0; $i < 4; ++$i) {
			for ($j = 0; $j < 4; ++$j) {
				$c[$i * 4 + $j] =
					$a[$i * 4 + 0] * $b[0 * 4 + $j] +
					$a[$i * 4 + 1] * $b[1 * 4 + $j] +
					$a[$i * 4 + 2] * $b[2 * 4 + $j] +
					$a[$i * 4 + 3] * $b[3 * 4 + $j];
			}
		}
		$this->_matrix = $c;

		return $this;
	}

	public function translateSelf(float $tx = 0.0, float $ty = 0.0, float $tz = 0.0): DOMMatrix
	{
		$this->_matrix = $this->translate($tx, $ty, $tz)->_matrix;

		return $this;
	}

	public function scaleSelf(float $scaleX = 1.0, ?float $scaleY = null, float $scaleZ = 1.0): DOMMatrix
	{
		if (null === $scaleY) {
			$scaleY = $scaleX;
		}

	       $scaleMatrix = new DOMMatrix([
	           $scaleX, 0.0, 0.0, 0.0,
	           0.0, $scaleY, 0.0, 0.0,
	           0.0, 0.0, $scaleZ, 0.0,
	           0.0, 0.0, 0.0, 1.0,
	       ]);

	       $this->multiplySelf($scaleMatrix);

		return $this;
	}

	public function rotateSelf(float $rotX = 0.0, ?float $rotY = null, ?float $rotZ = 0.0): DOMMatrix
	{
		$this->_matrix = $this->rotate($rotX, $rotY, $rotZ)->_matrix;

		return $this;
	}

	public function scale3dSelf(float $scale = 1.0, float $originX = 0.0, float $originY = 0.0, float $originZ = 0.0): DOMMatrix
	{
		$this->_matrix = $this->scale($scale, $scale, $scale, $originX, $originY, $originZ)->_matrix;

		return $this;
	}

	public function rotateAxisAngleSelf(float $x = 0.0, float $y = 0.0, float $z = 0.0, float $angle = 0.0): DOMMatrix
	{
		// Convert angle to radians
		$angle = deg2rad($angle);

		$len = sqrt($x * $x + $y * $y + $z * $z);
		if (0.0 === $len) {
			// Identity matrix if axis is zero vector
			$this->_matrix = (new DOMMatrix())->setMatrixValue('matrix3d(1,0,0,0,0,1,0,0,0,0,1,0,0,0,0,1)')->_matrix;

			return $this;
		}

		$x /= $len;
		$y /= $len;
		$z /= $len;

		$c = cos($angle);
		$s = sin($angle);
		$t = 1 - $c;

		$rotationMatrix = new DOMMatrix([
			$t * $x * $x + $c,     $t * $x * $y + $s * $z, $t * $x * $z - $s * $y, 0.0,
			$t * $x * $y - $s * $z, $t * $y * $y + $c,     $t * $y * $z + $s * $x, 0.0,
			$t * $x * $z + $s * $y, $t * $y * $z - $s * $x, $t * $z * $z + $c,     0.0,
			0.0, 0.0, 0.0, 1.0,
		]);

		$this->_matrix = $this->multiply($rotationMatrix)->_matrix;

		return $this;
	}

	public function rotateFromVectorSelf(float $x = 0.0, float $y = 0.0): DOMMatrix
	{
		$angle = atan2($y, $x);
		$this->_matrix = $this->rotateSelf(0, 0, rad2deg($angle))->_matrix;

		return $this;
	}

	public function setMatrixValue(string $matrixString): DOMMatrix
	{
		$this->setFromString($matrixString);

		return $this;
	}

	public function skewXSelf(float $angle = 0.0): DOMMatrix
	{
		$angle = deg2rad($angle);
		$skewMatrix = new DOMMatrix([
			1.0, 0.0, 0.0, 0.0,
			tan($angle), 1.0, 0.0, 0.0,
			0.0, 0.0, 1.0, 0.0,
			0.0, 0.0, 0.0, 1.0,
		]);
		$this->_matrix = $this->multiply($skewMatrix)->_matrix;

		return $this;
	}

	public function skewYSelf(float $angle = 0.0): DOMMatrix
	{
		$angle = deg2rad($angle);
		$skewMatrix = new DOMMatrix([
			1.0, tan($angle), 0.0, 0.0,
			0.0, 1.0, 0.0, 0.0,
			0.0, 0.0, 1.0, 0.0,
			0.0, 0.0, 0.0, 1.0,
		]);
		$this->_matrix = $this->multiply($skewMatrix)->_matrix;

		return $this;
	}

	public function toFloat64Array(): array
	{
		return $this->_matrix;
	}
}
