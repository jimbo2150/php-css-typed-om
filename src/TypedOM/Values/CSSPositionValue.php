<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

final class CSSPositionValue extends CSSStyleValue
{
	public function __construct(private readonly CSSStyleValue $x, private readonly CSSStyleValue $y)
	{
	}

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

	public function getY(): CSSStyleValue
	{
		return $this->y;
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
			default => throw new \Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	private static function parseCoordinate(string $value): CSSStyleValue
	{
		if (in_array(strtolower($value), ['left', 'right', 'top', 'bottom', 'center'], true)) {
			return new CSSKeywordValue(strtolower($value));
		}

		return \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue::parse($value);
	}
}
