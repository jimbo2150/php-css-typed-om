<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

final class CSSSkew extends CSSTransformComponent
{
	private CSSNumericValue $ax;
	private CSSNumericValue $ay;

	public function __construct(CSSNumericValue|int|float|string $axOrFunction, array|CSSNumericValue|int|float|string|null $ayOrValues = null)
	{
		if (is_string($axOrFunction) && is_array($ayOrValues)) {
			$function = strtolower($axOrFunction);
			$values = array_values($ayOrValues);
			if ($function !== 'skew' || count($values) < 1 || count($values) > 2) {
				throw new InvalidArgumentException('Invalid skew() arguments.');
			}
			$this->ax = self::numericValue($values[0], 'deg', ['angle']);
			$this->ay = isset($values[1]) ? self::numericValue($values[1], 'deg', ['angle']) : new CSSUnitValue(0, 'deg');
			parent::__construct($function, [$this->ax, ...array_slice([$this->ay], 0, isset($values[1]) ? 1 : 0)], true);
			return;
		}
		$this->ax = self::numericValue($axOrFunction, 'deg', ['angle']);
		$this->ay = $ayOrValues === null ? new CSSUnitValue(0, 'deg') : self::numericValue($ayOrValues, 'deg', ['angle']);
		parent::__construct('skew', $ayOrValues === null ? [$this->ax] : [$this->ax, $this->ay], true);
	}

	public function getAx(): CSSNumericValue { return $this->ax; }
	public function getAy(): CSSNumericValue { return $this->ay; }

	public function toMatrix(): array
	{
		return self::matrix2d(1.0, tan(self::scalar($this->ay, 'angle')), tan(self::scalar($this->ax, 'angle')), 1.0, 0.0, 0.0);
	}

	public function clone(): static { return new static($this->functionName, $this->values); }

	public function __get(string $name): mixed
	{
		return match ($name) {
			'ax' => $this->ax,
			'ay' => $this->ay,
			default => parent::__get($name),
		};
	}
}
