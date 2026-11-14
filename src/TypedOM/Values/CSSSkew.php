<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents a CSS `skew(ax, ay)` transform component.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSSkew
 */
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
			parent::__construct($function, isset($values[1]) ? [$this->ax, $this->ay] : [$this->ax], true);
			return;
		}
		$this->ax = self::numericValue($axOrFunction, 'deg', ['angle']);
		$this->ay = $ayOrValues === null ? new CSSUnitValue(0, 'deg') : self::numericValue($ayOrValues, 'deg', ['angle']);
		parent::__construct('skew', $ayOrValues === null ? [$this->ax] : [$this->ax, $this->ay], true);
	}

	public function getAx(): CSSNumericValue { return $this->ax; }
	public function getAy(): CSSNumericValue { return $this->ay; }

	public function setAx(CSSNumericValue $ax): void
	{
		$this->ax = self::numericValue($ax, 'deg', ['angle']);
		$this->values = [$this->ax, $this->ay];
	}

	public function setAy(CSSNumericValue $ay): void
	{
		$this->ay = self::numericValue($ay, 'deg', ['angle']);
		$this->values = [$this->ax, $this->ay];
	}

	public function toMatrix(): array
	{
		return self::matrix2d(1.0, tan(self::scalar($this->ay, 'angle')), tan(self::scalar($this->ax, 'angle')), 1.0, 0.0, 0.0);
	}

	public function clone(): static
	{
		return new static($this->ax->clone(), $this->ay->clone());
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'ax' => $this->ax,
			'ay' => $this->ay,
			default => parent::__get($name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'ax' && $value instanceof CSSNumericValue) {
			$this->setAx($value);
			return;
		}
		if ($name === 'ay' && $value instanceof CSSNumericValue) {
			$this->setAy($value);
			return;
		}
		parent::__set($name, $value);
	}
}
