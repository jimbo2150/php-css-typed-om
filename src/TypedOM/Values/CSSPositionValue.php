<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;

/**
 * Represents a 2D CSS position coordinate pair `(x, y)`.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPositionValue
 */
final class CSSPositionValue extends CSSStyleValue
{
	public CSSStyleValue $x {
		set (CSSStyleValue $value) {
			self::validateCoordinate($value, 'x');
			$this->x = $value;
		}
	}

	public CSSStyleValue $y {
		set (CSSStyleValue $value) {
			self::validateCoordinate($value, 'y');
			$this->y = $value;
		}
	}

	/**
	 * @throws InvalidArgumentException When $x or $y is not a CSSNumericValue or CSSKeywordValue
	 */
	public function __construct(CSSStyleValue $x, CSSStyleValue $y)
	{
		$this->x = $x;
		$this->y = $y;
	}

	/**
	 * Parses a 1- or 2-component CSS position expression.
	 *
	 * @throws InvalidArgumentException When the position syntax or axis order is invalid
	 */
	public static function fromCssText(string $cssText, ?CSSParserOptions $options = null): self
	{
		$options ??= CSSParserOptions::default();
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS position value exceeds maximum size of %d bytes.', $options->maxBytes));
		}
		if ($cssText !== '' && CSSSyntaxScanner::hasControlCharacters($cssText)) {
			throw new InvalidArgumentException('CSS position value contains disallowed control characters.');
		}

		$parts = CSSSyntaxScanner::splitTopLevelWhitespace(CSSSyntaxScanner::trimCssWhitespace($cssText));
		if ($parts === [] || count($parts) > 2) {
			throw new InvalidArgumentException('Only one- and two-component positions are supported.');
		}

		if (count($parts) === 1) {
			$lower = strtolower($parts[0]);
			if ($lower === 'top' || $lower === 'bottom') {
				$parts = ['center', $parts[0]];
			} else {
				$parts[] = 'center';
			}
		}

		$x = self::parseCoordinate($parts[0], $options);
		$y = self::parseCoordinate($parts[1], $options);
		$xWord = strtolower((string) $x);
		$yWord = strtolower((string) $y);
		if (
			(($xWord === 'top' || $xWord === 'bottom') && ($yWord === 'left' || $yWord === 'right' || $yWord === 'center'))
			|| ($xWord === 'center' && ($yWord === 'left' || $yWord === 'right'))
		) {
			[$x, $y] = [$y, $x];
			[$xWord, $yWord] = [$yWord, $xWord];
		}
		if ($xWord === 'top' || $xWord === 'bottom' || $yWord === 'left' || $yWord === 'right') {
			throw new InvalidArgumentException('Position keywords are in the wrong axis.');
		}

		return new self($x, $y);
	}

	public function getX(): CSSStyleValue
	{
		return $this->x;
	}

	public function setX(CSSStyleValue $x): void
	{
		$this->x = $x;
	}

	public function getY(): CSSStyleValue
	{
		return $this->y;
	}

	public function setY(CSSStyleValue $y): void
	{
		$this->y = $y;
	}

	public function __clone(): void
	{
		$this->x = $this->x->clone();
		$this->y = $this->y->clone();
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		return (string) $this->x . ' ' . (string) $this->y;
	}

	public function __get(string $name): mixed
	{
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}

	public function __isset(string $name): bool
	{
		return false;
	}

	public function __set(string $name, mixed $value): void
	{
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	private static function validateCoordinate(CSSStyleValue $coordinate, string $axis): void
	{
		if ($coordinate instanceof CSSKeywordValue) {
			$lower = strtolower($coordinate->getValue());
			$valid = $axis === 'x'
				? ($lower === 'left' || $lower === 'right' || $lower === 'center')
				: ($lower === 'top' || $lower === 'bottom' || $lower === 'center');
			if (!$valid) {
				throw new InvalidArgumentException('Invalid position keyword for ' . $axis . ': ' . $coordinate->getValue());
			}
			return;
		}

		if (!$coordinate instanceof CSSNumericValue) {
			throw new InvalidArgumentException('Position coordinate must be a CSSNumericValue or CSSKeywordValue.');
		}

		$type = $coordinate->getNumericTypeInternal();
		$dims = $type->toArray();
		$lengthExp = (int) ($dims['length'] ?? 0);
		$percentExp = (int) ($dims['percent'] ?? 0);
		$hasOther = false;
		foreach ($dims as $dim => $exp) {
			if ($dim !== 'length' && $dim !== 'percent' && $dim !== 'percentHint' && (int) $exp !== 0) {
				$hasOther = true;
				break;
			}
		}

		// Valid position coordinates are <length>, <percentage>, or <length-percentage> (1D length + optional percentHint).
		$isLength = $lengthExp === 1 && $percentExp === 0 && !$hasOther;
		$isPercent = $lengthExp === 0 && $percentExp === 1 && !$hasOther;
		if (!$isLength && !$isPercent) {
			throw new InvalidArgumentException('Position coordinate must be a length or percentage.');
		}
	}

	private static function parseCoordinate(string $value, ?CSSParserOptions $options = null): CSSStyleValue
	{
		$lower = strtolower($value);
		if ($lower === 'left' || $lower === 'right' || $lower === 'top' || $lower === 'bottom' || $lower === 'center') {
			return new CSSKeywordValue($lower);
		}

		$numeric = CSSNumericValue::parse($value, null, $options);
		if ($numeric instanceof CSSUnitValue && $numeric->unit === 'number' && $numeric->value === 0.0) {
			return new CSSUnitValue(0.0, 'px');
		}

		return $numeric;
	}
}
