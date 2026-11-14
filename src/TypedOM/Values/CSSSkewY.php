<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

final class CSSSkewY extends CSSTransformComponent
{
	private CSSNumericValue $ay;

	public function __construct(CSSNumericValue|int|float|string $valueOrFunction, array|CSSNumericValue|int|float|string|null $values = null)
	{
		if (is_string($valueOrFunction) && is_array($values)) {
			if (strtolower($valueOrFunction) !== 'skewy' || count($values) !== 1) throw new InvalidArgumentException('skewY() requires one angle.');
			$this->ay = self::numericValue($values[0], 'deg', ['angle']);
			parent::__construct('skewy', [$this->ay], true);
			return;
		}
		$this->ay = self::numericValue($valueOrFunction, 'deg', ['angle']);
		parent::__construct('skewy', [$this->ay], true);
	}

	public function getAy(): CSSNumericValue { return $this->ay; }
	public function toMatrix(): array { return self::matrix2d(1.0, tan(self::scalar($this->ay, 'angle')), 0.0, 1.0, 0.0, 0.0); }
	public function clone(): static { return new static($this->functionName, $this->values); }
	public function __get(string $name): mixed { return $name === 'ay' ? $this->ay : parent::__get($name); }
}
