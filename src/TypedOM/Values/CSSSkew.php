<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents a CSS `skew(ax, ay)` transform component.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSSkew
 */
final class CSSSkew extends CSSTransformComponent
{
	private CSSNumericValue $axValue;
	private CSSNumericValue $ayValue;

	public CSSNumericValue $ax {
		get => $this->axValue;
		set (CSSNumericValue|int|float $value) {
			$this->setAx($value);
		}
	}

	public CSSNumericValue $ay {
		get => $this->ayValue;
		set (CSSNumericValue|int|float $value) {
			$this->setAy($value);
		}
	}

	/**
	 * @param CSSNumericValue|int|float|string $axOrFunction
	 * @param list<CSSStyleValue|string>|CSSNumericValue|int|float|string|null $ayOrValues
	 */
	public function __construct(
		CSSNumericValue|int|float|string $axOrFunction,
		array|CSSNumericValue|int|float|string|null $ayOrValues = null,
	) {
		if (is_string($axOrFunction) && is_array($ayOrValues)) {
			$function = strtolower($axOrFunction);
			$values = array_values($ayOrValues);
			if ($function !== 'skew' || count($values) < 1 || count($values) > 2) {
				throw new InvalidArgumentException('Invalid skew() arguments.');
			}
			$this->axValue = self::numericValue($values[0], 'deg', ['angle']);
			$this->ayValue = isset($values[1]) ? self::numericValue($values[1], 'deg', ['angle']) : new CSSUnitValue(0, 'deg');
			parent::__construct($function, isset($values[1]) ? [$this->axValue, $this->ayValue] : [$this->axValue], true);
			return;
		}
		$this->axValue = self::numericValue($axOrFunction, 'deg', ['angle']);
		$this->ayValue = $ayOrValues === null ? new CSSUnitValue(0, 'deg') : self::numericValue($ayOrValues, 'deg', ['angle']);
		parent::__construct('skew', $ayOrValues === null ? [$this->axValue] : [$this->axValue, $this->ayValue], true);
	}

	public function getAx(): CSSNumericValue { return $this->axValue; }
	public function getAy(): CSSNumericValue { return $this->ayValue; }

	public function setAx(CSSNumericValue|int|float $ax): void
	{
		$this->axValue = self::numericValue($ax, 'deg', ['angle']);
		$this->values = [$this->axValue, $this->ayValue];
	}

	public function setAy(CSSNumericValue|int|float $ay): void
	{
		$this->ayValue = self::numericValue($ay, 'deg', ['angle']);
		$this->values = [$this->axValue, $this->ayValue];
	}

	public function setIs2D(bool $is2D): void
	{
		self::assertStrict2D($is2D, 'CSSSkew');
		parent::setIs2D(true);
	}

	public function toMatrix(): DOMMatrix
	{
		return DOMMatrix::fromTrustedElements(
			self::matrix2d(1.0, self::finiteTan($this->ayValue), self::finiteTan($this->axValue), 1.0, 0.0, 0.0),
			$this->twoDimensional,
		);
	}

	public function applyToMatrixInternal(DOMMatrix $matrix): void
	{
		$matrix->multiplyAffine2DInternal(1.0, self::finiteTan($this->ayValue), self::finiteTan($this->axValue), 1.0, 0.0, 0.0);
	}

	public function getValues(): array
	{
		$this->syncLiveState();
		return parent::getValues();
	}

	protected function prepareForSerialization(): void
	{
		$this->syncLiveState();
	}

	private function syncLiveState(): void
	{
		if (count($this->values) === 1 && !($this->ayValue instanceof CSSUnitValue && $this->ayValue->value === 0.0)) {
			$this->values = [$this->axValue, $this->ayValue];
		}
	}

	public function __clone(): void
	{
		$this->axValue = $this->axValue->clone();
		$this->ayValue = $this->ayValue->clone();
		$this->values = count($this->values) === 1 ? [$this->axValue] : [$this->axValue, $this->ayValue];
	}
}
