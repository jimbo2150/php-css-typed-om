<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents a CSS `perspective()` transform component.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPerspective
 */
final class CSSPerspective extends CSSTransformComponent
{
	private CSSNumericValue|CSSKeywordValue $length;

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float|string $lengthOrFunction,
		array|CSSNumericValue|CSSKeywordValue|int|float|string|null $values = null,
	) {
		if (is_string($lengthOrFunction) && is_array($values)) {
			if (strtolower($lengthOrFunction) !== 'perspective' || count($values) !== 1) {
				throw new InvalidArgumentException('perspective() requires one length or "none".');
			}
			$this->length = self::normalizeLength($values[0]);
			parent::__construct('perspective', [$this->length], false);
			return;
		}

		$this->length = self::normalizeLength($lengthOrFunction);
		parent::__construct('perspective', [$this->length], false);
	}

	public function getLength(): CSSNumericValue|CSSKeywordValue
	{
		return $this->length;
	}

	public function setLength(CSSNumericValue|CSSKeywordValue|int|float|string $length): void
	{
		$this->length = self::normalizeLength($length);
		$this->values = [$this->length];
	}

	public function toMatrix(): DOMMatrix
	{
		$matrix = self::identityMatrix();
		if ($this->length instanceof CSSKeywordValue) {
			return DOMMatrix::fromElements($matrix, false);
		}
		$perspective = self::scalar($this->length, 'length');
		if (!is_finite($perspective) || $perspective <= 0.0) {
			throw new InvalidArgumentException('Perspective length must be a positive finite length.');
		}
		$matrix[11] = -1.0 / $perspective;

		return DOMMatrix::fromElements($matrix, false);
	}

	public function clone(): static
	{
		return new static($this->length->clone());
	}

	public function __get(string $name): mixed
	{
		return $name === 'length' ? $this->length : parent::__get($name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'length' && ($value instanceof CSSNumericValue || $value instanceof CSSKeywordValue || is_int($value) || is_float($value) || is_string($value))) {
			$this->setLength($value);
			return;
		}
		parent::__set($name, $value);
	}

	private static function normalizeLength(mixed $value): CSSNumericValue|CSSKeywordValue
	{
		if ($value instanceof CSSKeywordValue || (is_string($value) && strcasecmp(trim($value), 'none') === 0)) {
			$keyword = $value instanceof CSSKeywordValue ? $value : new CSSKeywordValue('none');
			if (strcasecmp($keyword->getValue(), 'none') !== 0) {
				throw new InvalidArgumentException('CSSPerspective keyword must be "none".');
			}
			return $keyword;
		}

		$numeric = self::numericValue($value, 'px', ['length']);
		if ($numeric instanceof CSSUnitValue && $numeric->value <= 0.0) {
			throw new InvalidArgumentException('Perspective length must be positive.');
		}

		return $numeric;
	}
}
