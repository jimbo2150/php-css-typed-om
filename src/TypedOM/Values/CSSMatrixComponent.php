<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents a CSS `matrix()` or `matrix3d()` transform component backed by DOMMatrix.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMatrixComponent
 */
final class CSSMatrixComponent extends CSSTransformComponent
{
	private DOMMatrix $matrixValue;

	public DOMMatrix $matrix {
		get => $this->matrixValue;
		/** @param DOMMatrixReadOnly|list<float|int|string> $value */
		set (DOMMatrixReadOnly|array $value) {
			$this->setMatrix($value);
		}
	}

	/**
	 * @param DOMMatrixReadOnly|list<float|int|string>|string $matrixOrFunction
	 * @param list<CSSStyleValue|string>|array{is2D?: bool}|bool|null $valuesOrOptions
	 */
	public function __construct(DOMMatrixReadOnly|array|string $matrixOrFunction, array|bool|null $valuesOrOptions = null)
	{
		if (is_string($matrixOrFunction) && is_array($valuesOrOptions)) {
			$function = strtolower($matrixOrFunction);
			$values = [];
			foreach ($valuesOrOptions as $value) {
				if ($value instanceof CSSNumericValue) {
					if (!$value instanceof CSSUnitValue || $value->getNumericCategory() !== 'number') {
						throw new InvalidArgumentException('Matrix components must be unitless numbers.');
					}
					$values[] = $value->value;
				} elseif (!(is_string($value) && CSSSyntaxScanner::hasControlCharacters($value)) && is_numeric($value) && is_finite((float) $value)) {
					$values[] = (float) $value;
				} else {
					throw new InvalidArgumentException('Matrix components must be finite numbers.');
				}
			}
			$expectedCount = $function === 'matrix3d' ? 16 : 6;
			if (!in_array($function, ['matrix', 'matrix3d'], true) || count($values) !== $expectedCount) {
				throw new InvalidArgumentException('Matrix function has an invalid number of components.');
			}
			$this->matrixValue = new DOMMatrix($values);
			parent::__construct($function, [], $function === 'matrix');
			return;
		}

		if (is_string($matrixOrFunction)) {
			throw new InvalidArgumentException('Invalid CSSMatrixComponent initializer.');
		}

		$this->matrixValue = self::normalizeMatrix($matrixOrFunction);
		$is2D = $this->resolveIs2D($valuesOrOptions, $this->matrixValue->is2D());
		parent::__construct($is2D ? 'matrix' : 'matrix3d', [], $is2D);
	}

	public function getMatrix(): DOMMatrix
	{
		return $this->matrixValue;
	}

	/** @param DOMMatrixReadOnly|list<float|int|string> $matrix */
	public function setMatrix(DOMMatrixReadOnly|array $matrix): void
	{
		$this->matrixValue = self::normalizeMatrix($matrix);
	}

	/** @return list<string> */
	public function getValues(): array
	{
		return $this->serializedComponents($this->twoDimensional);
	}

	public function setIs2D(bool $is2D): void
	{
		parent::setIs2D($is2D);
		$this->functionName = $is2D ? 'matrix' : 'matrix3d';
	}

	public function getFunctionName(): string
	{
		return $this->twoDimensional ? 'matrix' : 'matrix3d';
	}

	public function toMatrix(): DOMMatrix
	{
		if ($this->twoDimensional) {
			return DOMMatrix::fromTrustedElements(
				self::matrix2d(
					$this->matrixValue->getA(),
					$this->matrixValue->getB(),
					$this->matrixValue->getC(),
					$this->matrixValue->getD(),
					$this->matrixValue->getE(),
					$this->matrixValue->getF(),
				),
				true,
			);
		}

		return DOMMatrix::fromTrustedElements($this->matrixValue->toFloat64Array(), false);
	}

	public function __clone(): void
	{
		$this->matrixValue = DOMMatrix::fromMatrix($this->matrixValue);
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		return $this->getFunctionName() . '(' . implode(', ', $this->serializedComponents($this->twoDimensional)) . ')';
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
			if ((is_string($value) && CSSSyntaxScanner::hasControlCharacters($value)) || !is_numeric($value) || !is_finite((float) $value)) {
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
				$this->matrixValue->getA(),
				$this->matrixValue->getB(),
				$this->matrixValue->getC(),
				$this->matrixValue->getD(),
				$this->matrixValue->getE(),
				$this->matrixValue->getF(),
			]
			: $this->matrixValue->toFloat64Array();

		$result = [];
		foreach ($elements as $value) {
			$result[] = $value === 0.0 ? '0' : (string) $value;
		}

		return $result;
	}
}
