<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS `skewX(ax)` transform component.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSSkewX
 */
final class CSSSkewX extends CSSTransformComponent
{
	private CSSNumericValue $ax;

	public function __construct(CSSNumericValue|int|float|string $valueOrFunction, array|CSSNumericValue|int|float|string|null $values = null)
	{
		if (is_string($valueOrFunction) && is_array($values)) {
			if (strtolower($valueOrFunction) !== 'skewx' || count($values) !== 1) {
				throw new InvalidArgumentException('skewX() requires one angle.');
			}
			$this->ax = self::numericValue($values[0], 'deg', ['angle']);
			parent::__construct('skewx', [$this->ax], true);
			return;
		}
		$this->ax = self::numericValue($valueOrFunction, 'deg', ['angle']);
		parent::__construct('skewx', [$this->ax], true);
	}

	public function getAx(): CSSNumericValue { return $this->ax; }

	public function setAx(CSSNumericValue $ax): void
	{
		$this->ax = self::numericValue($ax, 'deg', ['angle']);
		$this->values = [$this->ax];
	}

	public function toMatrix(): array
	{
		return self::matrix2d(1.0, 0.0, tan(self::scalar($this->ax, 'angle')), 1.0, 0.0, 0.0);
	}

	public function clone(): static { return new static($this->ax->clone()); }

	public function __get(string $name): mixed
	{
		return $name === 'ax' ? $this->ax : parent::__get($name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'ax' && $value instanceof CSSNumericValue) {
			$this->setAx($value);
			return;
		}
		parent::__set($name, $value);
	}
}
