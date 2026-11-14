<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `var(--name, fallback)` custom property reference.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSVariableReferenceValue
 */
final class CSSVariableReferenceValue extends CSSStyleValue
{
	private string $variable;
	private ?CSSUnparsedValue $fallback;

	/**
	 * @throws InvalidArgumentException When $variable is not a valid `--*` custom property or $fallback is structurally invalid
	 */
	public function __construct(string $variable, CSSUnparsedValue|string|null $fallback = null)
	{
		$this->setVariable($variable);
		$this->setFallback($fallback);
	}

	public function getVariable(): string
	{
		return $this->variable;
	}

	/**
	 * @throws InvalidArgumentException When $variable is not a valid `--*` identifier
	 */
	public function setVariable(string $variable): void
	{
		$normalized = CSSStyleDeclaration::normalizePropertyName($variable);
		if (!str_starts_with($normalized, '--')) {
			throw new InvalidArgumentException('A custom property name beginning with "--" is required.');
		}
		$this->variable = $normalized;
	}

	public function getFallback(): ?CSSUnparsedValue
	{
		return $this->fallback;
	}

	/**
	 * @throws InvalidArgumentException When $fallback contains invalid structure or control bytes
	 */
	public function setFallback(CSSUnparsedValue|string|null $fallback): void
	{
		if ($fallback === null) {
			$this->fallback = null;
			return;
		}
		if ($fallback instanceof CSSUnparsedValue) {
			CSSSyntaxScanner::assertValidFragment((string) $fallback, 'CSS variable fallback', [';', '{', '}']);
			$this->fallback = $fallback;
			return;
		}
		CSSSyntaxScanner::assertValidFragment($fallback, 'CSS variable fallback', [';', '{', '}']);
		$this->fallback = CSSUnparsedValue::fromCssText($fallback);
	}

	public function clone(): static
	{
		return new static($this->variable, $this->fallback?->clone());
	}

	public function __toString(): string
	{
		$fallback = $this->fallback === null ? '' : ', ' . (string) $this->fallback;
		return 'var(' . $this->variable . $fallback . ')';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'variable' => $this->getVariable(),
			'fallback' => $this->getFallback(),
			default => throw new Error('Undefined property: ' . static::class . '::$' . $name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'variable' && is_string($value)) {
			$this->setVariable($value);
			return;
		}
		if ($name === 'fallback' && ($value === null || is_string($value) || $value instanceof CSSUnparsedValue)) {
			$this->setFallback($value);
			return;
		}
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}
}
