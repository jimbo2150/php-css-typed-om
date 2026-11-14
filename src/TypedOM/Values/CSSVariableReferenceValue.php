<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;

final class CSSVariableReferenceValue extends CSSStyleValue
{
	public function __construct(private readonly string $variable, private readonly ?string $fallback = null)
	{
		if (!preg_match('/^--[a-zA-Z0-9_-]+$/D', $variable)) {
			throw new InvalidArgumentException('A custom property name beginning with "--" is required.');
		}
	}

	public function getVariable(): string
	{
		return $this->variable;
	}

	public function getFallback(): ?string
	{
		return $this->fallback;
	}

	public function clone(): static
	{
		return new static($this->variable, $this->fallback);
	}

	public function __toString(): string
	{
		$fallback = $this->fallback === null ? '' : ', ' . $this->fallback;
		return 'var(' . $this->variable . $fallback . ')';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'variable' => $this->getVariable(),
			'fallback' => $this->getFallback(),
			default => throw new \Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}
}
