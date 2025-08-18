<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

/**
 * Represents a reference to a CSS custom property (a `var()` function).
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSVariableReferenceValue
 */
class CSSVariableReferenceValue extends CSSStyleValue
{
	private string $variable;
	private ?CSSUnparsedValue $fallback;

	public function __construct(string $variable, ?CSSUnparsedValue $fallback = null)
	{
		$this->variable = trim($variable);
		$this->fallback = $fallback;
		parent::__construct('variable-reference');
	}

	public function getVariable(): string
	{
		return $this->variable;
	}

	public function getFallback(): ?CSSUnparsedValue
	{
		return $this->fallback;
	}

	public function toString(): string
	{
		$result = 'var('.$this->variable;
		if ($this->fallback) {
			$result .= ', '.$this->fallback->toString();
		}
		$result .= ')';

		return $result;
	}

	public function __toString(): string
	{
		return $this->toString();
	}

	public function isValid(): bool
	{
		// A variable reference is valid if the variable name is valid.
		// A simple check for a valid custom property name.
		return 1 === preg_match('/^--[a-zA-Z0-9_-]+$/', $this->variable);
	}

	public function clone(): CSSStyleValue
	{
		return new self($this->variable, $this->fallback);
	}

	/**
	 * Magic getter for property access.
	 */
	public function __get(string $name): mixed
	{
		return match ($name) {
			'variable' => $this->variable,
			'fallback' => $this->fallback,
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
			'variable' => $this->variable = (string) $value,
			'fallback' => $this->fallback = $value instanceof CSSUnparsedValue ? $value : null,
			default => throw new \Error("Undefined property: {$name}"),
		};
	}
}
