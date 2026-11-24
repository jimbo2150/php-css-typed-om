<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Geometry;

use ArrayAccess;
use Countable;
use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;
use Stringable;

/**
 * Read-only 4x4 column-major transformation matrix (W3C Geometry Interfaces Level 1).
 *
 * Implements ArrayAccess over column-major indices 0..15 for ergonomic numeric access.
 *
 * @phpstan-consistent-constructor
 * @implements ArrayAccess<int, float>
 * @property-read bool $isIdentity
 * @property-read float $a
 * @property-read float $b
 * @property-read float $c
 * @property-read float $d
 * @property-read float $e
 * @property-read float $f
 * @property-read float $m11
 * @property-read float $m12
 * @property-read float $m13
 * @property-read float $m14
 * @property-read float $m21
 * @property-read float $m22
 * @property-read float $m23
 * @property-read float $m24
 * @property-read float $m31
 * @property-read float $m32
 * @property-read float $m33
 * @property-read float $m34
 * @property-read float $m41
 * @property-read float $m42
 * @property-read float $m43
 * @property-read float $m44
 * @see https://developer.mozilla.org/en-US/docs/Web/API/DOMMatrixReadOnly
 */
class DOMMatrixReadOnly implements ArrayAccess, Countable, Stringable
{
	/** @var list<float> */
	protected const array IDENTITY_ELEMENTS = [
		1.0, 0.0, 0.0, 0.0,
		0.0, 1.0, 0.0, 0.0,
		0.0, 0.0, 1.0, 0.0,
		0.0, 0.0, 0.0, 1.0,
	];

	/** @var list<array{string, string}> */
	protected const array ALIAS_PAIRS = [
		['a', 'm11'],
		['b', 'm12'],
		['c', 'm21'],
		['d', 'm22'],
		['e', 'm41'],
		['f', 'm42'],
	];

	/** @var array<string, int> */
	protected const array ELEMENT_INDEX = [
		'm11' => 0, 'm12' => 1, 'm13' => 2, 'm14' => 3,
		'm21' => 4, 'm22' => 5, 'm23' => 6, 'm24' => 7,
		'm31' => 8, 'm32' => 9, 'm33' => 10, 'm34' => 11,
		'm41' => 12, 'm42' => 13, 'm43' => 14, 'm44' => 15,
		'a' => 0, 'b' => 1, 'c' => 4, 'd' => 5, 'e' => 12, 'f' => 13,
	];

	/** @var list<float> 16 column-major elements */
	protected array $elements;

	public protected(set) bool $is2D;

	/**
	 * @param DOMMatrixReadOnly|list<int|float>|array<string, mixed>|string|null $init
	 * @throws InvalidArgumentException When the matrix initializer is invalid or non-finite
	 */
	public function __construct(self|array|string|null $init = null)
	{
		if ($init === null) {
			$this->elements = self::IDENTITY_ELEMENTS;
			$this->is2D = true;
			return;
		}

		if ($init instanceof self) {
			$this->elements = $init->elements;
			$this->is2D = $init->is2D;
			return;
		}

		if (is_string($init)) {
			[$this->elements, $this->is2D] = self::parseTransformString($init);
			return;
		}

		if (array_is_list($init)) {
			[$this->elements, $this->is2D] = self::normalizeList($init);
			return;
		}

		[$this->elements, $this->is2D] = self::normalizeDictionary($init);
	}

	/**
	 * @param DOMMatrixReadOnly|list<int|float>|array<string, mixed> $other
	 */
	public static function fromMatrix(self|array $other = []): static
	{
		if ($other === []) {
			return new static();
		}

		return new static($other);
	}

	/** @param list<int|float> $values */
	public static function fromFloat32Array(array $values): static
	{
		if (count($values) !== 16) {
			throw new InvalidArgumentException('fromFloat32Array() requires exactly 16 numeric elements.');
		}

		return new static($values);
	}

	/** @param list<int|float> $values */
	public static function fromFloat64Array(array $values): static
	{
		if (count($values) !== 16) {
			throw new InvalidArgumentException('fromFloat64Array() requires exactly 16 numeric elements.');
		}

		return new static($values);
	}

	/**
	 * Internal helper for creating a matrix with explicit 16 elements and 2D flag.
	 *
	 * @param list<float> $elements
	 */
	public static function fromElements(array $elements, bool $is2D): static
	{
		$normalized = self::validateSixteenElements($elements);
		if ($is2D && !self::elementsAre2D($normalized)) {
			throw new InvalidArgumentException('A 2D matrix cannot contain non-2D 3D components.');
		}

		$matrix = new static();
		$matrix->elements = $normalized;
		$matrix->is2D = $is2D;

		return $matrix;
	}

	/**
	 * @internal Fast factory for callers that have already produced finite float elements.
	 *
	 * @param list<float> $elements
	 */
	public static function fromTrustedElements(array $elements, bool $is2D): static
	{
		$matrix = new static();
		$matrix->elements = $elements;
		$matrix->is2D = $is2D;

		return $matrix;
	}

	public function is2D(): bool
	{
		return $this->is2D;
	}

	public function isIdentity(): bool
	{
		return $this->elements === self::IDENTITY_ELEMENTS;
	}

	public function getA(): float { return $this->elements[0]; }
	public function getB(): float { return $this->elements[1]; }
	public function getC(): float { return $this->elements[4]; }
	public function getD(): float { return $this->elements[5]; }
	public function getE(): float { return $this->elements[12]; }
	public function getF(): float { return $this->elements[13]; }

	public function getM11(): float { return $this->elements[0]; }
	public function getM12(): float { return $this->elements[1]; }
	public function getM13(): float { return $this->elements[2]; }
	public function getM14(): float { return $this->elements[3]; }
	public function getM21(): float { return $this->elements[4]; }
	public function getM22(): float { return $this->elements[5]; }
	public function getM23(): float { return $this->elements[6]; }
	public function getM24(): float { return $this->elements[7]; }
	public function getM31(): float { return $this->elements[8]; }
	public function getM32(): float { return $this->elements[9]; }
	public function getM33(): float { return $this->elements[10]; }
	public function getM34(): float { return $this->elements[11]; }
	public function getM41(): float { return $this->elements[12]; }
	public function getM42(): float { return $this->elements[13]; }
	public function getM43(): float { return $this->elements[14]; }
	public function getM44(): float { return $this->elements[15]; }

	public function translate(float $tx = 0.0, float $ty = 0.0, float $tz = 0.0): DOMMatrix
	{
		return DOMMatrix::fromMatrix($this)->translateSelf($tx, $ty, $tz);
	}

	public function scale(
		float $scaleX = 1.0,
		?float $scaleY = null,
		float $scaleZ = 1.0,
		float $originX = 0.0,
		float $originY = 0.0,
		float $originZ = 0.0,
	): DOMMatrix {
		return DOMMatrix::fromMatrix($this)->scaleSelf($scaleX, $scaleY, $scaleZ, $originX, $originY, $originZ);
	}

	public function scaleNonUniform(float $scaleX = 1.0, float $scaleY = 1.0): DOMMatrix
	{
		return DOMMatrix::fromMatrix($this)->scaleSelf($scaleX, $scaleY, 1.0);
	}

	public function scale3d(
		float $scale = 1.0,
		float $originX = 0.0,
		float $originY = 0.0,
		float $originZ = 0.0,
	): DOMMatrix {
		return DOMMatrix::fromMatrix($this)->scale3dSelf($scale, $originX, $originY, $originZ);
	}

	public function rotate(float $rotX = 0.0, ?float $rotY = null, ?float $rotZ = null): DOMMatrix
	{
		return DOMMatrix::fromMatrix($this)->rotateSelf($rotX, $rotY, $rotZ);
	}

	public function rotateFromVector(float $x = 0.0, float $y = 0.0): DOMMatrix
	{
		return DOMMatrix::fromMatrix($this)->rotateFromVectorSelf($x, $y);
	}

	public function rotateAxisAngle(float $x = 0.0, float $y = 0.0, float $z = 0.0, float $angle = 0.0): DOMMatrix
	{
		return DOMMatrix::fromMatrix($this)->rotateAxisAngleSelf($x, $y, $z, $angle);
	}

	public function skewX(float $sx = 0.0): DOMMatrix
	{
		return DOMMatrix::fromMatrix($this)->skewXSelf($sx);
	}

	public function skewY(float $sy = 0.0): DOMMatrix
	{
		return DOMMatrix::fromMatrix($this)->skewYSelf($sy);
	}

	/** @param DOMMatrixReadOnly|list<int|float>|array<string, mixed> $other */
	public function multiply(self|array $other): DOMMatrix
	{
		return DOMMatrix::fromMatrix($this)->multiplySelf($other);
	}

	public function flipX(): DOMMatrix
	{
		$m = $this->elements;
		$m[0] = $m[0] === 0.0 ? 0.0 : -$m[0];
		$m[1] = $m[1] === 0.0 ? 0.0 : -$m[1];
		$m[2] = $m[2] === 0.0 ? 0.0 : -$m[2];
		$m[3] = $m[3] === 0.0 ? 0.0 : -$m[3];

		return DOMMatrix::fromTrustedElements($m, $this->is2D);
	}

	public function flipY(): DOMMatrix
	{
		$m = $this->elements;
		$m[4] = $m[4] === 0.0 ? 0.0 : -$m[4];
		$m[5] = $m[5] === 0.0 ? 0.0 : -$m[5];
		$m[6] = $m[6] === 0.0 ? 0.0 : -$m[6];
		$m[7] = $m[7] === 0.0 ? 0.0 : -$m[7];

		return DOMMatrix::fromTrustedElements($m, $this->is2D);
	}

	/**
	 * @throws InvalidArgumentException When the matrix is singular (non-invertible)
	 */
	public function inverse(): DOMMatrix
	{
		return DOMMatrix::fromMatrix($this)->invertSelf();
	}

	/**
	 * Transforms a 3D homogeneous point (`x`, `y`, `z`, `w`).
	 *
	 * @param array{x?: int|float, y?: int|float, z?: int|float, w?: int|float} $point
	 * @return array{x: float, y: float, z: float, w: float}
	 */
	public function transformPoint(array $point = []): array
	{
		$x = self::assertFinite($point['x'] ?? 0.0, 'point.x');
		$y = self::assertFinite($point['y'] ?? 0.0, 'point.y');
		$z = self::assertFinite($point['z'] ?? 0.0, 'point.z');
		$w = self::assertFinite($point['w'] ?? 1.0, 'point.w');
		$m = $this->elements;

		return [
			'x' => self::assertFinite($m[0] * $x + $m[4] * $y + $m[8] * $z + $m[12] * $w, 'transformedPoint.x'),
			'y' => self::assertFinite($m[1] * $x + $m[5] * $y + $m[9] * $z + $m[13] * $w, 'transformedPoint.y'),
			'z' => self::assertFinite($m[2] * $x + $m[6] * $y + $m[10] * $z + $m[14] * $w, 'transformedPoint.z'),
			'w' => self::assertFinite($m[3] * $x + $m[7] * $y + $m[11] * $z + $m[15] * $w, 'transformedPoint.w'),
		];
	}

	/** @return list<float> */
	public function toFloat32Array(): array
	{
		return $this->elements;
	}

	/** @return list<float> */
	public function toFloat64Array(): array
	{
		return $this->elements;
	}

	/** @return array<string, float|bool> */
	public function toJSON(): array
	{
		return [
			'a' => $this->elements[0],
			'b' => $this->elements[1],
			'c' => $this->elements[4],
			'd' => $this->elements[5],
			'e' => $this->elements[12],
			'f' => $this->elements[13],
			'm11' => $this->elements[0],
			'm12' => $this->elements[1],
			'm13' => $this->elements[2],
			'm14' => $this->elements[3],
			'm21' => $this->elements[4],
			'm22' => $this->elements[5],
			'm23' => $this->elements[6],
			'm24' => $this->elements[7],
			'm31' => $this->elements[8],
			'm32' => $this->elements[9],
			'm33' => $this->elements[10],
			'm34' => $this->elements[11],
			'm41' => $this->elements[12],
			'm42' => $this->elements[13],
			'm43' => $this->elements[14],
			'm44' => $this->elements[15],
			'is2D' => $this->is2D,
			'isIdentity' => $this->isIdentity(),
		];
	}

	public function count(): int
	{
		return 16;
	}

	public function offsetExists(mixed $offset): bool
	{
		return is_int($offset) && $offset >= 0 && $offset < 16;
	}

	public function offsetGet(mixed $offset): ?float
	{
		return is_int($offset) && isset($this->elements[$offset]) ? $this->elements[$offset] : null;
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		throw new InvalidArgumentException('DOMMatrixReadOnly is immutable.');
	}

	public function offsetUnset(mixed $offset): void
	{
		throw new InvalidArgumentException('DOMMatrixReadOnly is immutable.');
	}

	public function __toString(): string
	{
		$m = $this->elements;
		if ($this->is2D) {
			return 'matrix('
				. self::formatNumber($m[0]) . ', '
				. self::formatNumber($m[1]) . ', '
				. self::formatNumber($m[4]) . ', '
				. self::formatNumber($m[5]) . ', '
				. self::formatNumber($m[12]) . ', '
				. self::formatNumber($m[13]) . ')';
		}

		$parts = [];
		foreach ($m as $value) {
			$parts[] = self::formatNumber($value);
		}

		return 'matrix3d(' . implode(', ', $parts) . ')';
	}

	public function __get(string $name): mixed
	{
		if ($name === 'isIdentity') {
			return $this->isIdentity();
		}
		if (isset(self::ELEMENT_INDEX[$name])) {
			return $this->elements[self::ELEMENT_INDEX[$name]];
		}

		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'isIdentity' || isset(self::ELEMENT_INDEX[$name]);
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	/** @return list<float> */
	protected static function identityElements(): array
	{
		return self::IDENTITY_ELEMENTS;
	}

	/**
	 * @param list<float> $left
	 * @param list<float> $right
	 * @return list<float>
	 */
	public static function multiplyElements(
		array $left,
		array $right,
		bool $leftIs2D = false,
		bool $rightIs2D = false,
	): array {
		if ($leftIs2D && $rightIs2D) {
			return [
				self::assertFinite($left[0] * $right[0] + $left[4] * $right[1], 'matrix product'),
				self::assertFinite($left[1] * $right[0] + $left[5] * $right[1], 'matrix product'),
				0.0,
				0.0,
				self::assertFinite($left[0] * $right[4] + $left[4] * $right[5], 'matrix product'),
				self::assertFinite($left[1] * $right[4] + $left[5] * $right[5], 'matrix product'),
				0.0,
				0.0,
				0.0,
				0.0,
				1.0,
				0.0,
				self::assertFinite($left[0] * $right[12] + $left[4] * $right[13] + $left[12], 'matrix product'),
				self::assertFinite($left[1] * $right[12] + $left[5] * $right[13] + $left[13], 'matrix product'),
				0.0,
				1.0,
			];
		}

		$result = self::IDENTITY_ELEMENTS;
		for ($column = 0; $column < 4; $column++) {
			$colBase = $column * 4;
			$r0 = $right[$colBase];
			$r1 = $right[$colBase + 1];
			$r2 = $right[$colBase + 2];
			$r3 = $right[$colBase + 3];
			for ($row = 0; $row < 4; $row++) {
				$sum = $left[$row] * $r0
					+ $left[4 + $row] * $r1
					+ $left[8 + $row] * $r2
					+ $left[12 + $row] * $r3;
				$result[$colBase + $row] = self::assertFinite($sum, 'matrix product');
			}
		}

		return $result;
	}

	/** @param list<float> $elements */
	protected static function elementsAre2D(array $elements): bool
	{
		return $elements[2] === 0.0
			&& $elements[3] === 0.0
			&& $elements[6] === 0.0
			&& $elements[7] === 0.0
			&& $elements[8] === 0.0
			&& $elements[9] === 0.0
			&& $elements[10] === 1.0
			&& $elements[11] === 0.0
			&& $elements[14] === 0.0
			&& $elements[15] === 1.0;
	}

	protected static function assertFinite(mixed $value, string $label): float
	{
		if (is_string($value) && CSSSyntaxScanner::hasControlCharacters($value)) {
			throw new InvalidArgumentException(sprintf('Matrix %s contains disallowed control characters.', $label));
		}
		if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
			throw new InvalidArgumentException(sprintf('Matrix %s must be a finite number.', $label));
		}
		$float = (float) $value;
		if (!is_finite($float)) {
			throw new InvalidArgumentException(sprintf('Matrix %s must be finite.', $label));
		}

		return $float === 0.0 ? 0.0 : $float;
	}

	/** @return array{list<float>, bool} */
	protected static function parseTransformString(string $cssText): array
	{
		if (strlen($cssText) > CSSParserOptions::DEFAULT_MAX_BYTES) {
			throw new CSSParserLimitException(
				sprintf('Transform list exceeds maximum size of %d bytes.', CSSParserOptions::DEFAULT_MAX_BYTES),
			);
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($cssText);
		if ($trimmed === '' || strcasecmp($trimmed, 'none') === 0) {
			return [self::IDENTITY_ELEMENTS, true];
		}

		CSSSyntaxScanner::assertValidFragment($trimmed, 'CSS transform', [';', '{', '}']);
		$matrix = CSSTransformValue::fromCssText($trimmed)->toMatrix();

		return [$matrix->elements, $matrix->is2D];
	}

	/**
	 * @param list<mixed> $values
	 * @return array{list<float>, bool}
	 */
	protected static function normalizeList(array $values): array
	{
		$count = count($values);
		if ($count === 6) {
			$a = self::assertFinite($values[0], 'a');
			$b = self::assertFinite($values[1], 'b');
			$c = self::assertFinite($values[2], 'c');
			$d = self::assertFinite($values[3], 'd');
			$e = self::assertFinite($values[4], 'e');
			$f = self::assertFinite($values[5], 'f');
			return [[
				$a, $b, 0.0, 0.0,
				$c, $d, 0.0, 0.0,
				0.0, 0.0, 1.0, 0.0,
				$e, $f, 0.0, 1.0,
			], true];
		}

		if ($count === 16) {
			return [self::validateSixteenElements($values), false];
		}

		throw new InvalidArgumentException('A matrix sequence must contain 6 or 16 numeric components.');
	}

	/**
	 * @param array<string, mixed> $init
	 * @return array{list<float>, bool}
	 */
	protected static function normalizeDictionary(array $init): array
	{
		foreach (self::ALIAS_PAIRS as [$short, $long]) {
			if (array_key_exists($short, $init) && array_key_exists($long, $init)) {
				$shortVal = self::assertFinite($init[$short], $short);
				$longVal = self::assertFinite($init[$long], $long);
				if ($shortVal !== $longVal) {
					throw new InvalidArgumentException(sprintf('Conflicting DOMMatrixInit values for "%s" and "%s".', $short, $long));
				}
			}
		}

		$elements = self::IDENTITY_ELEMENTS;
		$explicitIs2D = null;

		foreach ($init as $key => $value) {
			if ($key === 'is2D') {
				if (!is_bool($value)) {
					throw new InvalidArgumentException('DOMMatrixInit::$is2D must be a boolean.');
				}
				$explicitIs2D = $value;
				continue;
			}
			if ($key === 'isIdentity') {
				if (!is_bool($value)) {
					throw new InvalidArgumentException('DOMMatrixInit::$isIdentity must be a boolean.');
				}
				continue;
			}
			if (!isset(self::ELEMENT_INDEX[$key])) {
				throw new InvalidArgumentException('Unknown DOMMatrixInit property: ' . $key);
			}
			$index = self::ELEMENT_INDEX[$key];
			$float = self::assertFinite($value, $key);
			$elements[$index] = $float;
		}

		$are2D = self::elementsAre2D($elements);
		if ($explicitIs2D === true && !$are2D) {
			throw new InvalidArgumentException('DOMMatrixInit cannot specify is2D=true with non-2D 3D matrix entries.');
		}

		$is2D = $explicitIs2D ?? $are2D;

		return [$elements, $is2D];
	}

	/**
	 * @param list<mixed> $values
	 * @return list<float>
	 */
	protected static function validateSixteenElements(array $values): array
	{
		if (count($values) !== 16) {
			throw new InvalidArgumentException('A 3D matrix requires exactly 16 numeric components.');
		}
		$normalized = [];
		foreach ($values as $index => $value) {
			$normalized[] = self::assertFinite($value, 'element[' . $index . ']');
		}

		return $normalized;
	}

	private static function formatNumber(float $value): string
	{
		if ($value === 0.0) {
			return '0';
		}

		return (string) $value;
	}
}
