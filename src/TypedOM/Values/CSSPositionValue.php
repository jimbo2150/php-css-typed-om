<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MagicPropertyAccessTrait;

/**
 * Represents a CSS position value.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPositionValue
 */
class CSSPositionValue extends CSSStyleValue
{
	use MagicPropertyAccessTrait;

	private CSSNumericValue $x;
	private CSSNumericValue $y;

	public function __construct(CSSNumericValue $x, CSSNumericValue $y)
	{
		$this->x = $x;
		$this->y = $y;
		parent::__construct('position');
	}

	public function getX(): CSSNumericValue
	{
		return $this->x;
	}

	public function getY(): CSSNumericValue
	{
		return $this->y;
	}

	public function toString(): string
	{
		return $this->x->toString().' '.$this->y->toString();
	}

	public function isValid(): bool
	{
		return $this->x->isValid() && $this->y->isValid();
	}

	public function clone(): CSSStyleValue
	{
		return new self(clone $this->x, clone $this->y);
	}

	public static function parse(string $cssText): self
	{
		$parts = preg_split('/\s+/', trim($cssText), -1, PREG_SPLIT_NO_EMPTY);

		if (2 !== count($parts)) {
			throw new \InvalidArgumentException('Invalid CSS position value: '.$cssText);
		}

		return new self(CSSNumericValue::parse($parts[0]), CSSNumericValue::parse($parts[1]));
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'x' => $this->x,
			'y' => $this->y,
			'type' => $this->type,
			default => throw new \Error(sprintf('Undefined property: %s::$%s', self::class, $name)),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		throw new \Error(sprintf('Cannot set property %s::$%s', self::class, $name));
	}
}
