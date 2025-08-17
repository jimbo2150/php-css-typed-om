<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\DOM;

/**
 * Represents a 4x4 homogeneous matrix for 2D and 3D transformations, read-only version.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/DOMMatrixReadOnly
 */
class DOMMatrixReadOnly
{
	protected array $_matrix; // Stores m11-m44

	public function __construct($init = null)
	{
		$this->_matrix = [
			1.0, 0.0, 0.0, 0.0,
			0.0, 1.0, 0.0, 0.0,
			0.0, 0.0, 1.0, 0.0,
			0.0, 0.0, 0.0, 1.0,
		];

		if (is_string($init)) {
			$this->setFromString($init);
		} elseif (is_array($init)) {
			$this->setFromArray($init);
		} elseif ($init instanceof DOMMatrixReadOnly) { // Can accept DOMMatrix as well
			$this->_matrix = $init->_matrix;
		} elseif (null !== $init) {
			throw new \InvalidArgumentException('Invalid argument for DOMMatrixReadOnly constructor.');
		}
	}

	protected function setFromString(string $matrixString): void
	{
		// Basic parsing for 'matrix()' and 'matrix3d()'
		if (str_starts_with($matrixString, 'matrix(') && str_ends_with($matrixString, ')')) {
			$values = explode(',', substr($matrixString, 7, -1));
			if (6 === count($values)) {
				$this->setFromArray(array_map('floatval', $values));
			} else {
				throw new \InvalidArgumentException('Invalid 2D matrix string format.');
			}
		} elseif (str_starts_with($matrixString, 'matrix3d(') && str_ends_with($matrixString, ')')) {
			$values = explode(',', substr($matrixString, 9, -1));
			if (16 === count($values)) {
				$this->setFromArray(array_map('floatval', $values));
			} else {
				throw new \InvalidArgumentException('Invalid 3D matrix string format.');
			}
		} else {
			throw new \InvalidArgumentException('Unsupported matrix string format.');
		}
	}

	protected function setFromArray(array $values): void
	{
		if (6 === count($values)) { // 2D matrix
			$this->_matrix = [
				$values[0], $values[1], 0.0, 0.0,
				$values[2], $values[3], 0.0, 0.0,
				0.0, 0.0, 1.0, 0.0,
				$values[4], $values[5], 0.0, 1.0,
			];
		} elseif (16 === count($values)) { // 3D matrix
			$this->_matrix = array_map('floatval', $values);
		} else {
			throw new \InvalidArgumentException('Array must contain 6 or 16 numeric values.');
		}
	}

	public function __get(string $name)
	{
		return match ($name) {
			'a' => $this->_matrix[0],
			'b' => $this->_matrix[1],
			'c' => $this->_matrix[4],
			'd' => $this->_matrix[5],
			'e' => $this->_matrix[12],
			'f' => $this->_matrix[13],
			'm11' => $this->_matrix[0],
			'm12' => $this->_matrix[1],
			'm13' => $this->_matrix[2],
			'm14' => $this->_matrix[3],
			'm21' => $this->_matrix[4],
			'm22' => $this->_matrix[5],
			'm23' => $this->_matrix[6],
			'm24' => $this->_matrix[7],
			'm31' => $this->_matrix[8],
			'm32' => $this->_matrix[9],
			'm33' => $this->_matrix[10],
			'm34' => $this->_matrix[11],
			'm41' => $this->_matrix[12],
			'm42' => $this->_matrix[13],
			'm43' => $this->_matrix[14],
			'm44' => $this->_matrix[15],
			'is2D' => 0.0 === $this->_matrix[2] &&
					  0.0 === $this->_matrix[3] &&
					  0.0 === $this->_matrix[6] &&
					  0.0 === $this->_matrix[7] &&
					  0.0 === $this->_matrix[8] &&
					  0.0 === $this->_matrix[9] &&
					  1.0 === $this->_matrix[10] &&
					  0.0 === $this->_matrix[11] &&
					  0.0 === $this->_matrix[14],
			'isIdentity' => (
				abs(1.0 - $this->_matrix[0]) < 0.000001 && abs(0.0 - $this->_matrix[1]) < 0.000001 && abs(0.0 - $this->_matrix[2]) < 0.000001 && abs(0.0 - $this->_matrix[3]) < 0.000001 &&
				abs(0.0 - $this->_matrix[4]) < 0.000001 && abs(1.0 - $this->_matrix[5]) < 0.000001 && abs(0.0 - $this->_matrix[6]) < 0.000001 && abs(0.0 - $this->_matrix[7]) < 0.000001 &&
				abs(0.0 - $this->_matrix[8]) < 0.000001 && abs(0.0 - $this->_matrix[9]) < 0.000001 && abs(1.0 - $this->_matrix[10]) < 0.000001 && abs(0.0 - $this->_matrix[11]) < 0.000001 &&
				abs(0.0 - $this->_matrix[12]) < 0.000001 && abs(0.0 - $this->_matrix[13]) < 0.000001 && abs(0.0 - $this->_matrix[14]) < 0.000001 && abs(1.0 - $this->_matrix[15]) < 0.000001
			),
			default => throw new \InvalidArgumentException('Undefined property: '.$name),
		};
	}

	public function toString(): string
	{
		if ($this->is2D) { // Now a property
			return 'matrix('.implode(', ', [$this->a, $this->b, $this->c, $this->d, $this->e, $this->f]).')';
		} else {
			return 'matrix3d('.implode(', ', $this->_matrix).')';
		}
	}

	public function multiply(DOMMatrixReadOnly $other): DOMMatrix
	{
		$result = new DOMMatrix(); // Returns DOMMatrix, not DOMMatrixReadOnly
		$a = $this->_matrix;
		$b = $other->_matrix;
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
		$result->_matrix = $c;

		return $result;
	}

	public function flipX(): DOMMatrix
	{
		$flipMatrix = new DOMMatrix([-1.0, 0.0, 0.0, 0.0,
			0.0, 1.0, 0.0, 0.0,
			0.0, 0.0, 1.0, 0.0,
			0.0, 0.0, 0.0, 1.0,
		]);

		return $this->multiply($flipMatrix);
	}

	public function flipY(): DOMMatrix
	{
		$flipMatrix = new DOMMatrix([
			1.0, 0.0, 0.0, 0.0,
			0.0, -1.0, 0.0, 0.0,
			0.0, 0.0, 1.0, 0.0,
			0.0, 0.0, 0.0, 1.0,
		]);

		return $this->multiply($flipMatrix);
	}

	public function inverse(): DOMMatrix
	{
		$m = $this->_matrix;
		$inv = [];

		$inv[0] = $m[5] * $m[10] * $m[15] -
				 $m[5] * $m[11] * $m[14] -
				 $m[9] * $m[6] * $m[15] +
				 $m[9] * $m[7] * $m[14] +
				 $m[13] * $m[6] * $m[11] -
				 $m[13] * $m[7] * $m[10];

		$inv[4] = -$m[4] * $m[10] * $m[15] +
				  $m[4] * $m[11] * $m[14] +
				  $m[8] * $m[6] * $m[15] -
				  $m[8] * $m[7] * $m[14] -
				  $m[12] * $m[6] * $m[11] +
				  $m[12] * $m[7] * $m[10];

		$inv[8] = $m[4] * $m[9] * $m[15] -
				 $m[4] * $m[11] * $m[13] -
				 $m[8] * $m[5] * $m[15] +
				 $m[8] * $m[7] * $m[13] +
				 $m[12] * $m[5] * $m[11] -
				 $m[12] * $m[7] * $m[9];

		$inv[12] = -$m[4] * $m[9] * $m[14] +
				   $m[4] * $m[10] * $m[13] +
				   $m[8] * $m[5] * $m[14] -
				   $m[8] * $m[6] * $m[13] -
				   $m[12] * $m[5] * $m[10] +
				   $m[12] * $m[6] * $m[9];

		$inv[1] = -$m[1] * $m[10] * $m[15] +
				  $m[1] * $m[11] * $m[14] +
				  $m[9] * $m[2] * $m[15] -
				  $m[9] * $m[3] * $m[14] -
				  $m[13] * $m[2] * $m[11] +
				  $m[13] * $m[3] * $m[10];

		$inv[5] = $m[0] * $m[10] * $m[15] -
				 $m[0] * $m[11] * $m[14] -
				 $m[8] * $m[2] * $m[15] +
				 $m[8] * $m[3] * $m[14] +
				 $m[12] * $m[2] * $m[11] -
				 $m[12] * $m[3] * $m[10];

		$inv[9] = -$m[0] * $m[9] * $m[15] +
				  $m[0] * $m[11] * $m[13] +
				  $m[8] * $m[1] * $m[15] -
				  $m[8] * $m[3] * $m[13] -
				  $m[12] * $m[1] * $m[11] +
				  $m[12] * $m[3] * $m[9];

		$inv[13] = $m[0] * $m[9] * $m[14] -
				   $m[0] * $m[10] * $m[13] -
				   $m[8] * $m[1] * $m[14] +
				   $m[8] * $m[2] * $m[13] +
				   $m[12] * $m[1] * $m[10] -
				   $m[12] * $m[2] * $m[9];

		$inv[2] = $m[1] * $m[6] * $m[15] -
				 $m[1] * $m[7] * $m[14] -
				 $m[5] * $m[2] * $m[15] +
				 $m[5] * $m[3] * $m[14] +
				 $m[13] * $m[2] * $m[7] -
				 $m[13] * $m[3] * $m[6];

		$inv[6] = -$m[0] * $m[6] * $m[15] +
				  $m[0] * $m[7] * $m[14] +
				  $m[4] * $m[2] * $m[15] -
				  $m[4] * $m[3] * $m[14] -
				  $m[12] * $m[2] * $m[7] +
				  $m[12] * $m[3] * $m[6];

		$inv[10] = $m[0] * $m[5] * $m[15] -
				  $m[0] * $m[7] * $m[13] -
				  $m[4] * $m[1] * $m[15] +
				  $m[4] * $m[3] * $m[13] +
				  $m[12] * $m[1] * $m[7] -
				  $m[12] * $m[3] * $m[5];

		$inv[14] = -$m[0] * $m[5] * $m[14] +
				   $m[0] * $m[6] * $m[13] +
				   $m[4] * $m[1] * $m[14] -
				   $m[4] * $m[2] * $m[13] -
				   $m[12] * $m[1] * $m[6] +
				   $m[12] * $m[2] * $m[5];

		$inv[3] = -$m[1] * $m[6] * $m[11] +
				  $m[1] * $m[7] * $m[10] +
				  $m[5] * $m[2] * $m[11] -
				  $m[5] * $m[3] * $m[10] -
				  $m[9] * $m[2] * $m[7] +
				  $m[9] * $m[3] * $m[6];

		$inv[7] = $m[0] * $m[6] * $m[11] -
				 $m[0] * $m[7] * $m[10] -
				 $m[4] * $m[2] * $m[11] +
				 $m[4] * $m[3] * $m[10] +
				 $m[8] * $m[2] * $m[7] -
				 $m[8] * $m[3] * $m[6];

		$inv[11] = -$m[0] * $m[5] * $m[11] +
				   $m[0] * $m[7] * $m[9] +
				   $m[4] * $m[1] * $m[11] -
				   $m[4] * $m[3] * $m[9] -
				   $m[8] * $m[1] * $m[7] +
				   $m[8] * $m[3] * $m[5];

		$inv[15] = $m[0] * $m[5] * $m[10] -
				   $m[0] * $m[6] * $m[9] -
				   $m[4] * $m[1] * $m[10] +
				   $m[4] * $m[2] * $m[9] +
				   $m[8] * $m[1] * $m[6] -
				   $m[8] * $m[2] * $m[5];

		$det = $m[0] * $inv[0] + $m[1] * $inv[4] + $m[2] * $inv[8] + $m[3] * $inv[12];

		if (0.0 === $det) {
			throw new \InvalidArgumentException('InvalidStateError: Matrix is not invertible.');
		}

		$det = 1.0 / $det;

		$result = new DOMMatrix();
		for ($i = 0; $i < 16; ++$i) {
			$result->_matrix[$i] = $inv[$i] * $det;
		}

		return $result;
	}

	public function translate(float $tx = 0.0, float $ty = 0.0, float $tz = 0.0): DOMMatrix
	{
		$translateMatrix = new DOMMatrix([
			1.0, 0.0, 0.0, 0.0,
			0.0, 1.0, 0.0, 0.0,
			0.0, 0.0, 1.0, 0.0,
			$tx, $ty, $tz, 1.0,
		]);

		return $this->multiply($translateMatrix);
	}

	public function scale(float $scaleX = 1.0, ?float $scaleY = null, float $scaleZ = 1.0, float $originX = 0.0, float $originY = 0.0, float $originZ = 0.0): DOMMatrix
	{
		if (null === $scaleY) {
			$scaleY = $scaleX;
		}
		
		$matrix = new DOMMatrix($this);
		
		if ($originX !== 0.0 || $originY !== 0.0 || $originZ !== 0.0) {
			$matrix->translateSelf($originX, $originY, $originZ);
		}
		
		$matrix->scaleSelf($scaleX, $scaleY, $scaleZ);
		
		if ($originX !== 0.0 || $originY !== 0.0 || $originZ !== 0.0) {
			$matrix->translateSelf(-$originX, -$originY, -$originZ);
		}

		return $matrix;
	}

	public function rotate(float $rotX = 0.0, ?float $rotY = null, float $rotZ = 0.0): DOMMatrix
	{
		$result = new DOMMatrix($this);

		if (0.0 !== $rotX) {
			$radX = deg2rad($rotX);
			$cosX = cos($radX);
			$sinX = sin($radX);
			$rotateXMatrix = new DOMMatrix([
				1.0, 0.0, 0.0, 0.0,
				0.0, $cosX, $sinX, 0.0,
				0.0, -$sinX, $cosX, 0.0,
				0.0, 0.0, 0.0, 1.0,
			]);
			$result = $result->multiply($rotateXMatrix);
		}

		if (null !== $rotY && 0.0 !== $rotY) {
			$radY = deg2rad($rotY);
			$cosY = cos($radY);
			$sinY = sin($radY);
			$rotateYMatrix = new DOMMatrix([
				$cosY, 0.0, -$sinY, 0.0,
				0.0, 1.0, 0.0, 0.0,
				$sinY, 0.0, $cosY, 0.0,
				0.0, 0.0, 0.0, 1.0,
			]);
			$result = $result->multiply($rotateYMatrix);
		}

		if (0.0 !== $rotZ) {
			$radZ = deg2rad($rotZ);
			$cosZ = cos($radZ);
			$sinZ = sin($radZ);
			$rotateZMatrix = new DOMMatrix([
				$cosZ, $sinZ, 0.0, 0.0,
				-$sinZ, $cosZ, 0.0, 0.0,
				0.0, 0.0, 1.0, 0.0,
				0.0, 0.0, 0.0, 1.0,
			]);
			$result = $result->multiply($rotateZMatrix);
		}

		return $result;
	}

	public static function fromMatrix(DOMMatrixReadOnly $other): DOMMatrixReadOnly
	{
		return new DOMMatrixReadOnly($other);
	}

	public static function fromFloat32Array(array $array): DOMMatrixReadOnly
	{
		return new DOMMatrixReadOnly($array);
	}

	public static function fromFloat64Array(array $array): DOMMatrixReadOnly
	{
		return new DOMMatrixReadOnly($array);
	}

	public function toFloat32Array(): array
	{
		return $this->_matrix;
	}

	public function toFloat64Array(): array
	{
		return $this->_matrix;
	}
}
