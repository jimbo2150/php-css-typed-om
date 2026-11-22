<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents a CSS `matrix()` or `matrix3d()` transform component backed by DOMMatrix.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMatrixComponent
 */
final class CSSMatrixComponent extends CSSTransformComponent
{
	private DOMMatrix $matrix;

	/**
	 * @param DOMMatrixReadOnly|list<float|int|string>|string $matrixOrFunction
	 * @param list<CSSStyleValue|string>|array{is2D?: bool}|bool|null $valuesOrOptions
	 */
	public function __construct(DOMMatrixReadOnly|array|string $matrixOrFunction, array|bool|null $valuesOrOptions = null)
	{
		if (is_string($matrixOrFunction) && is_array($valuesOrOptions)) {
			$function = strtolower($matrixOrFunction);
			$values = array_map(static function (mixed $value): float {
				if ($value instanceof CSSNumericValue) {
					if (!$value instanceof CSSUnitValue || $value->getNumericCategory() !== 'number') {
						throw new InvalidArgumentException('Matrix components must be unitless numbers.');
					}
					return $value->value;
				}
				if (!is_numeric($value) || !is_finite((float) $value)) {
					throw new InvalidArgumentException('Matrix components must be finite numbers.');
				}
				return (float) $value;
			}, $valuesOrOptions);
			$expectedCount = $function === 'matrix3d' ? 16 : 6;
			if (!in_array($function, ['matrix', 'matrix3d'], true) || count($values) !== $expectedCount) {
				throw new InvalidArgumentException('Matrix function has an invalid number of components.');
			}
			$this->matrix = new DOMMatrix($values);
			parent::__construct($function, $this->serializedComponents($function === 'matrix'), $function === 'matrix');
			return;
		}

		if (is_string($matrixOrFunction)) {
			throw new InvalidArgumentException('Invalid CSSMatrixComponent initializer.');
		}

		$this->matrix = self::normalizeMatrix($matrixOrFunction);
		$is2D = $this->resolveIs2D($valuesOrOptions, $this->matrix->is2D());
		parent::__construct($is2D ? 'matrix' : 'matrix3d', $this->serializedComponents($is2D), $is2D);
	}

	public function getMatrix(): DOMMatrix
	{
		return $this->matrix;
	}

	/** @param DOMMatrixReadOnly|list<float|int|string> $matrix */
	public function setMatrix(DOMMatrixReadOnly|array $matrix): void
	{
		$this->matrix = self::normalizeMatrix($matrix);
		$this->values = $this->serializedComponents($this->twoDimensional);
	}

	public function setIs2D(bool $is2D): void
	{
		parent::setIs2D($is2D);
		$this->values = $this->serializedComponents($this->twoDimensional);
	}

	public function getFunctionName(): string
	{
		return $this->twoDimensional ? 'matrix' : 'matrix3d';
	}

	public function toMatrix(): DOMMatrix
	{
		if ($this->twoDimensional) {
			return DOMMatrix::fromElements(
				self::matrix2d(
					$this->matrix->getA(),
					$this->matrix->getB(),
					$this->matrix->getC(),
					$this->matrix->getD(),
					$this->matrix->getE(),
					$this->matrix->getF(),
				),
				true,
			);
		}

		return DOMMatrix::fromElements($this->matrix->toFloat64Array(), false);
	}

	public function clone(): static
	{
		return new static(DOMMatrix::fromMatrix($this->matrix), $this->twoDimensional);
	}

	public function __toString(): string
	{
		return $this->getFunctionName() . '(' . implode(', ', $this->serializedComponents($this->twoDimensional)) . ')';
	}

	public function __get(string $name): mixed
	{
		return $name === 'matrix' ? $this->getMatrix() : parent::__get($name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'matrix' && ($value instanceof DOMMatrixReadOnly || is_array($value))) {
			$this->setMatrix($value);
			return;
		}
		parent::__set($name, $value);
	}

	/** @param DOMMatrixReadOnly|list<mixed> $matrix */
	private static function normalizeMatrix(DOMMatrixReadOnly|array $matrix): DOMMatrix
	{
		if ($matrix instanceof DOMMatrixReadOnly) {
			return DOMMatrix::fromMatrix($matrix);
		}

		if (!in_array(count($matrix), [6, 16], true)) {
			throw new InvalidArgumentException('A matrix requires six or sixteen numeric components.');
		}

		$normalized = [];
		foreach ($matrix as $value) {
			if (!is_numeric($value) || !is_finite((float) $value)) {
				throw new InvalidArgumentException('Matrix components must be finite numbers.');
			}
			$normalized[] = (float) $value;
		}

		return new DOMMatrix($normalized);
	}

	/** @param array{is2D?: bool}|bool|null $options */
	private function resolveIs2D(array|bool|null $options, bool $default): bool
	{
		if (is_bool($options)) {
			return $options;
		}
		if (is_array($options) && array_key_exists('is2D', $options)) {
			if (!is_bool($options['is2D'])) {
				throw new InvalidArgumentException('CSSMatrixComponent options.is2D must be a boolean.');
			}
			return $options['is2D'];
		}

		return $default;
	}

	/** @return list<string> */
	private function serializedComponents(bool $is2D): array
	{
		$elements = $is2D
			? [
				$this->matrix->getA(),
				$this->matrix->getB(),
				$this->matrix->getC(),
				$this->matrix->getD(),
				$this->matrix->getE(),
				$this->matrix->getF(),
			]
			: $this->matrix->toFloat64Array();

		return array_map(static fn (float $value): string => $value === 0.0 ? '0' : (string) $value, $elements);
	}
}
