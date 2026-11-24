<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS `skewX(ax)` transform component.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSSkewX
 */
final class CSSSkewX extends CSSTransformComponent
{
	private CSSNumericValue $axValue;

	public CSSNumericValue $ax {
		get => $this->axValue;
		set (CSSNumericValue|int|float $value) {
			$this->setAx($value);
		}
	}

	public function __construct(CSSNumericValue|int|float|string $valueOrFunction, array|CSSNumericValue|int|float|string|null $values = null)
	{
		if (is_string($valueOrFunction) && is_array($values)) {
			$values = array_values($values);
			if (strtolower($valueOrFunction) !== 'skewx' || count($values) !== 1) {
				throw new InvalidArgumentException('skewX() requires one angle.');
			}
			$this->axValue = self::numericValue($values[0], 'deg', ['angle']);
			parent::__construct('skewx', [$this->axValue], true);
			return;
		}
		$this->axValue = self::numericValue($valueOrFunction, 'deg', ['angle']);
		parent::__construct('skewx', [$this->axValue], true);
	}

	public function getAx(): CSSNumericValue { return $this->axValue; }

	public function setAx(CSSNumericValue|int|float $ax): void
	{
		$this->axValue = self::numericValue($ax, 'deg', ['angle']);
		$this->values = [$this->axValue];
	}

	public function setIs2D(bool $is2D): void
	{
		if (!$is2D) {
			throw new InvalidArgumentException('CSSSkewX cannot be 3D.');
		}
		parent::setIs2D(true);
	}

	public function toMatrix(): DOMMatrix
	{
		$tanX = tan(self::scalar($this->axValue, 'angle'));
		if (!is_finite($tanX)) {
			throw new InvalidArgumentException('Skew angle produces a non-finite tangent.');
		}

		return DOMMatrix::fromTrustedElements(self::matrix2d(1.0, 0.0, $tanX, 1.0, 0.0, 0.0), $this->twoDimensional);
	}

	public function __clone(): void
	{
		$this->axValue = $this->axValue->clone();
		$this->values = [$this->axValue];
	}

	public function clone(): static
	{
		return clone $this;
	}
}
