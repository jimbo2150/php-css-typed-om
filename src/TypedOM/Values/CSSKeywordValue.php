<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

class CSSKeywordValue extends CSSStyleValue
{
	private string $value;

	public function __construct(string $value)
	{
		$this->value = $value;
		parent::__construct('keyword');
	}

	public function toString(): string
	{
		return $this->value;
	}

	public function clone(): CSSStyleValue
	{
		return new self($this->value);
	}

	public function isValid(): bool
	{
		return '' !== trim($this->value);
	}

	/**
	 * Magic getter for property access.
	 */
	public function __get(string $name): mixed
	{
		return match ($name) {
			'value' => $this->value,
			'type' => $this->type,
			default => throw new \Error("Undefined property: {$name}"),
		};
	}

	/**
	 * Magic setter for property access.
	 */
	public function __set(string $name, mixed $value): void
	{
		match ($name) {
			'value' => $this->value = (string) $value,
			default => throw new \Error("Undefined property: {$name}"),
		};
	}
}
