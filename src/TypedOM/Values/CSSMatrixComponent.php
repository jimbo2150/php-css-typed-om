<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

final class CSSMatrixComponent extends CSSTransformComponent
{
	/** @var list<float> */
	private array $matrix;

	/** @param list<float|int|string>|string $matrixOrFunction */
	public function __construct(array|string $matrixOrFunction, array|bool|null $valuesOrIs2D = true)
	{
		if (is_string($matrixOrFunction) && is_array($valuesOrIs2D)) {
			$function = strtolower($matrixOrFunction);
			$values = array_map(static function (mixed $value): float {
				if ($value instanceof CSSNumericValue) {
					if (!$value instanceof CSSUnitValue || $value->getNumericCategory() !== 'number') {
						throw new InvalidArgumentException('Matrix components must be unitless numbers.');
					}
					return $value->value;
				}
				if (!is_numeric($value)) {
					throw new InvalidArgumentException('Matrix components must be numeric.');
				}
				return (float) $value;
			}, $valuesOrIs2D);
			$expectedCount = $function === 'matrix3d' ? 16 : 6;
			if (!in_array($function, ['matrix', 'matrix3d'], true) || count($values) !== $expectedCount) {
				throw new InvalidArgumentException('Matrix function has an invalid number of components.');
			}
			$this->matrix = $values;
			parent::__construct($function, array_map(static fn (float $value): string => (string) $value, $values), $function === 'matrix');
			return;
		}

		foreach ($matrixOrFunction as $value) {
			if (!is_numeric($value)) {
				throw new InvalidArgumentException('Matrix components must be numeric.');
			}
		}
		$this->matrix = array_map(static fn (mixed $value): float => (float) $value, $matrixOrFunction);
		if (!in_array(count($this->matrix), [6, 16], true)) {
			throw new InvalidArgumentException('A matrix requires six or sixteen numeric components.');
		}
		$is2D = is_bool($valuesOrIs2D) ? $valuesOrIs2D : count($this->matrix) === 6;
		parent::__construct(count($this->matrix) === 6 ? 'matrix' : 'matrix3d', array_map(static fn (float $value): string => (string) $value, $this->matrix), $is2D);
	}

	/** @return list<float> */
	public function getMatrix(): array
	{
		return $this->matrix;
	}

	public function toMatrix(): array
	{
		$matrix = self::identityMatrix();
		if (count($this->matrix) === 6) {
			[$matrix[0], $matrix[1], $matrix[4], $matrix[5], $matrix[12], $matrix[13]] = $this->matrix;
			return $matrix;
		}
		return $this->matrix;
	}

	public function clone(): static
	{
		return new static($this->matrix, $this->is2D());
	}

	public function __get(string $name): mixed
	{
		return $name === 'matrix' ? $this->getMatrix() : parent::__get($name);
	}
}
