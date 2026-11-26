<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a CSS `skewY(ay)` transform component.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSSkewY
 */
final class CSSSkewY extends CSSTransformComponent
{
	private CSSNumericValue $ayValue;

	public CSSNumericValue $ay {
		get => $this->ayValue;
		set (CSSNumericValue|int|float $value) {
			$this->setAy($value);
		}
	}

	public function __construct(CSSNumericValue|int|float|string $valueOrFunction, array|CSSNumericValue|int|float|string|null $values = null)
	{
		if (is_string($valueOrFunction) && is_array($values)) {
			$values = array_values($values);
			if (strtolower($valueOrFunction) !== 'skewy' || count($values) !== 1) {
				throw new InvalidArgumentException('skewY() requires one angle.');
			}
			$this->ayValue = self::numericValue($values[0], 'deg', ['angle']);
			parent::__construct('skewy', [$this->ayValue], true);
			return;
		}
		$this->ayValue = self::numericValue($valueOrFunction, 'deg', ['angle']);
		parent::__construct('skewy', [$this->ayValue], true);
	}

	public function getAy(): CSSNumericValue { return $this->ayValue; }

	public function setAy(CSSNumericValue|int|float $ay): void
	{
		$this->ayValue = self::numericValue($ay, 'deg', ['angle']);
		$this->values = [$this->ayValue];
	}

	public function setIs2D(bool $is2D): void
	{
		self::assertStrict2D($is2D, 'CSSSkewY');
		parent::setIs2D(true);
	}

	public function toMatrix(): DOMMatrix
	{
		return DOMMatrix::fromTrustedElements(self::matrix2d(1.0, self::finiteTan($this->ayValue), 0.0, 1.0, 0.0, 0.0), $this->twoDimensional);
	}

	public function applyToMatrixInternal(DOMMatrix $matrix): void
	{
		$matrix->multiplyAffine2DInternal(1.0, self::finiteTan($this->ayValue), 0.0, 1.0, 0.0, 0.0);
	}

	public function __clone(): void
	{
		$this->ayValue = $this->ayValue->clone();
		$this->values = [$this->ayValue];
	}
}
