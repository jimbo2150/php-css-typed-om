<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

final class CSSScale extends CSSTransformComponent
{
	private CSSNumericValue $x;
	private CSSNumericValue $y;
	private CSSNumericValue $z;

	public function __construct(
		CSSNumericValue|int|float|string $xOrFunction,
		array|CSSNumericValue|int|float|string|null $yOrValues = null,
		CSSNumericValue|int|float|string|null $z = null,
	) {
		if (is_string($xOrFunction) && is_array($yOrValues)) {
			$function = strtolower($xOrFunction);
			[$this->x, $this->y, $this->z, $is2D] = self::parseFunction($function, array_values($yOrValues));
			parent::__construct($function, array_map(static fn (mixed $value): CSSNumericValue => self::numericValue($value), $yOrValues), $is2D);
			return;
		}

		$this->x = self::numericValue($xOrFunction, 'number', ['number']);
		$this->y = $yOrValues === null ? $this->x : self::numericValue($yOrValues, 'number', ['number']);
		$this->z = $z === null ? new CSSUnitValue(1, 'number') : self::numericValue($z, 'number', ['number']);
		$hasZ = $z !== null;
		parent::__construct($hasZ ? 'scale3d' : 'scale', $hasZ ? [$this->x, $this->y, $this->z] : [$this->x, $this->y], self::scalar($this->z, 'number') === 1.0);
	}

	public function getX(): CSSNumericValue { return $this->x; }
	public function getY(): CSSNumericValue { return $this->y; }
	public function getZ(): CSSNumericValue { return $this->z; }

	public function toMatrix(): array
	{
		$matrix = self::identityMatrix();
		$matrix[0] = self::scalar($this->x, 'number');
		$matrix[5] = self::scalar($this->y, 'number');
		$matrix[10] = self::scalar($this->z, 'number');
		return $matrix;
	}

	public function clone(): static
	{
		return new static($this->functionName, $this->values);
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'x' => $this->x,
			'y' => $this->y,
			'z' => $this->z,
			default => parent::__get($name),
		};
	}

	/** @param list<CSSStyleValue|string> $arguments @return array{CSSNumericValue, CSSNumericValue, CSSNumericValue, bool} */
	private static function parseFunction(string $function, array $arguments): array
	{
		$one = new CSSUnitValue(1, 'number');
		$zero = new CSSUnitValue(0, 'number');
		$x = $one;
		$y = $one;
		$z = $one;
		$is2D = true;
		switch ($function) {
			case 'scale':
				if (count($arguments) < 1 || count($arguments) > 2) throw new InvalidArgumentException('scale() requires one or two values.');
				$x = self::numericValue($arguments[0], 'number', ['number']);
				$y = isset($arguments[1]) ? self::numericValue($arguments[1], 'number', ['number']) : $x;
				break;
			case 'scalex':
			case 'scaley':
			case 'scalez':
				if (count($arguments) !== 1) throw new InvalidArgumentException($function . '() requires one value.');
				$value = self::numericValue($arguments[0], 'number', ['number']);
				if ($function === 'scalex') $x = $value;
				if ($function === 'scaley') $y = $value;
				if ($function === 'scalez') { $z = $value; $is2D = false; }
				break;
			case 'scale3d':
				if (count($arguments) !== 3) throw new InvalidArgumentException('scale3d() requires three values.');
				$x = self::numericValue($arguments[0], 'number', ['number']);
				$y = self::numericValue($arguments[1], 'number', ['number']);
				$z = self::numericValue($arguments[2], 'number', ['number']);
				$is2D = false;
				break;
			default:
				throw new InvalidArgumentException('Unsupported scale function: ' . $function);
		}
		return [$x, $y, $z, $is2D];
	}
}
