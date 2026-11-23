<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents a CSS `perspective()` transform component.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPerspective
 */
final class CSSPerspective extends CSSTransformComponent
{
	private CSSNumericValue|CSSKeywordValue $lengthValue;

	public CSSNumericValue|CSSKeywordValue $length {
		get => $this->getLength();
		set (CSSNumericValue|CSSKeywordValue|int|float|string $value) {
			$this->setLength($value);
		}
	}

	public function __construct(
		CSSNumericValue|CSSKeywordValue|int|float|string $lengthOrFunction,
		array|CSSNumericValue|CSSKeywordValue|int|float|string|null $values = null,
	) {
		if (is_string($lengthOrFunction) && is_array($values)) {
			if (strtolower($lengthOrFunction) !== 'perspective' || count($values) !== 1) {
				throw new InvalidArgumentException('perspective() requires one length or "none".');
			}
			$this->lengthValue = self::normalizeLength($values[0]);
			parent::__construct('perspective', [$this->lengthValue], false);
			return;
		}

		$this->lengthValue = self::normalizeLength($lengthOrFunction);
		parent::__construct('perspective', [$this->lengthValue], false);
	}

	public function getLength(): CSSNumericValue|CSSKeywordValue
	{
		return $this->lengthValue instanceof CSSKeywordValue ? $this->lengthValue->clone() : $this->lengthValue;
	}

	/** @return list<CSSNumericValue|CSSKeywordValue> */
	public function getValues(): array
	{
		return [$this->getLength()];
	}

	public function setLength(CSSNumericValue|CSSKeywordValue|int|float|string $length): void
	{
		$this->lengthValue = self::normalizeLength($length);
		$this->values = [$this->lengthValue];
	}

	public function setIs2D(bool $is2D): void
	{
		if ($is2D) {
			throw new InvalidArgumentException('CSSPerspective cannot be 2D.');
		}
		parent::setIs2D(false);
	}

	public function toMatrix(): DOMMatrix
	{
		$matrix = self::IDENTITY_MATRIX;
		if ($this->lengthValue instanceof CSSKeywordValue) {
			return DOMMatrix::fromTrustedElements($matrix, false);
		}
		$perspective = self::scalar($this->lengthValue, 'length');
		if (!is_finite($perspective) || $perspective <= 0.0) {
			throw new InvalidArgumentException('Perspective length must be a positive finite length.');
		}
		$m34 = -1.0 / $perspective;
		if (!is_finite($m34)) {
			throw new InvalidArgumentException('Perspective length produces a non-finite matrix entry.');
		}
		$matrix[11] = $m34;

		return DOMMatrix::fromTrustedElements($matrix, false);
	}

	public function __clone(): void
	{
		$this->lengthValue = $this->lengthValue->clone();
		$this->values = [$this->lengthValue];
	}

	public function clone(): static
	{
		return clone $this;
	}

	private static function normalizeLength(mixed $value): CSSNumericValue|CSSKeywordValue
	{
		if (
			$value instanceof CSSKeywordValue
			|| (is_string($value) && strcasecmp(CSSSyntaxScanner::trimCssWhitespace($value), 'none') === 0)
			|| ($value instanceof CSSUnparsedValue && strcasecmp(CSSSyntaxScanner::trimCssWhitespace((string) $value), 'none') === 0)
		) {
			$keyword = $value instanceof CSSKeywordValue ? $value : new CSSKeywordValue('none');
			if (strcasecmp($keyword->getValue(), 'none') !== 0) {
				throw new InvalidArgumentException('CSSPerspective keyword must be "none".');
			}
			return new CSSKeywordValue('none');
		}

		$numeric = self::numericValue($value, 'px', ['length']);
		if ($numeric instanceof CSSUnitValue && $numeric->value <= 0.0) {
			throw new InvalidArgumentException('Perspective length must be positive.');
		}

		return $numeric;
	}
}
