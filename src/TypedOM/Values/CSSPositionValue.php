<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

/**
 * Represents a 2D CSS position coordinate pair `(x, y)`.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPositionValue
 */
final class CSSPositionValue extends CSSStyleValue
{
	private CSSStyleValue $x;
	private CSSStyleValue $y;

	/**
	 * @throws InvalidArgumentException When $x or $y is not a CSSNumericValue or CSSKeywordValue
	 */
	public function __construct(CSSStyleValue $x, CSSStyleValue $y)
	{
		$this->setX($x);
		$this->setY($y);
	}

	/**
	 * Parses a 1- or 2-component CSS position expression.
	 *
	 * @throws InvalidArgumentException When the position syntax or axis order is invalid
	 */
	public static function fromCssText(string $cssText): self
	{
		$parts = CSSSyntaxScanner::splitTopLevelWhitespace(trim($cssText));
		if ($parts === [] || count($parts) > 2) {
			throw new InvalidArgumentException('Only one- and two-component positions are supported.');
		}

		if (count($parts) === 1) {
			if (in_array(strtolower($parts[0]), ['top', 'bottom'], true)) {
				$parts = ['center', $parts[0]];
			} else {
				$parts[] = 'center';
			}
		}

		$x = self::parseCoordinate($parts[0]);
		$y = self::parseCoordinate($parts[1]);
		$xWord = strtolower((string) $x);
		$yWord = strtolower((string) $y);
		if (in_array($xWord, ['top', 'bottom'], true) && in_array($yWord, ['left', 'right', 'center'], true)) {
			[$x, $y] = [$y, $x];
			[$xWord, $yWord] = [$yWord, $xWord];
		}
		if (in_array($xWord, ['top', 'bottom'], true) || in_array($yWord, ['left', 'right'], true)) {
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
		self::validateCoordinate($x, 'x');
		$this->x = $x;
	}

	public function getY(): CSSStyleValue
	{
		return $this->y;
	}

	public function setY(CSSStyleValue $y): void
	{
		self::validateCoordinate($y, 'y');
		$this->y = $y;
	}

	public function clone(): static
	{
		return new static($this->x->clone(), $this->y->clone());
	}

	public function __toString(): string
	{
		return (string) $this->x . ' ' . (string) $this->y;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'x' => $this->getX(),
			'y' => $this->getY(),
			default => throw new Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'x' && $value instanceof CSSStyleValue) {
			$this->setX($value);
			return;
		}
		if ($name === 'y' && $value instanceof CSSStyleValue) {
			$this->setY($value);
			return;
		}
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	private static function validateCoordinate(CSSStyleValue $coordinate, string $axis): void
	{
		if ($coordinate instanceof CSSKeywordValue) {
			$allowed = $axis === 'x' ? ['left', 'right', 'center'] : ['top', 'bottom', 'center'];
			if (!in_array(strtolower($coordinate->getValue()), $allowed, true)) {
				throw new InvalidArgumentException('Invalid position keyword for ' . $axis . ': ' . $coordinate->getValue());
			}
			return;
		}

		if (!$coordinate instanceof CSSNumericValue) {
			throw new InvalidArgumentException('Position coordinate must be a CSSNumericValue or CSSKeywordValue.');
		}
		if (!in_array($coordinate->getNumericCategory(), ['length', 'percent', 'derived'], true)) {
			throw new InvalidArgumentException('Position coordinate must be a length or percentage.');
		}
	}

	private static function parseCoordinate(string $value): CSSStyleValue
	{
		if (in_array(strtolower($value), ['left', 'right', 'top', 'bottom', 'center'], true)) {
			return new CSSKeywordValue(strtolower($value));
		}

		return CSSNumericValue::parse($value);
	}
}
