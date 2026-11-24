<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use ReflectionClass;

/**
 * Represents a CSS `var(--name, fallback)` custom property reference.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSVariableReferenceValue
 */
final class CSSVariableReferenceValue extends CSSStyleValue
{
	private string $variableValue;

	private ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES;

	public string $variable {
		get => $this->variableValue;
		set (string $value) {
			$this->assignVariable($value);
		}
	}

	private ?CSSUnparsedValue $fallbackValue = null;

	public ?CSSUnparsedValue $fallback {
		get => $this->fallbackValue;
		set (CSSUnparsedValue|string|null $value) {
			$this->fallbackValue = self::normalizeFallback($value, null, 0, $this->maxBytes);
		}
	}

	/**
	 * @throws InvalidArgumentException When $variable is not a valid `--*` custom property or $fallback is structurally invalid
	 */
	public function __construct(
		string $variable,
		CSSUnparsedValue|string|null $fallback = null,
		?CSSParserOptions $options = null,
		int $depth = 0,
	) {
		$options ??= CSSParserOptions::default();
		if ($options->maxDepth !== null && $depth > $options->maxDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $options->maxDepth));
		}

		$this->maxBytes = $options->maxBytes;
		$this->assignVariable($variable);
		$this->fallbackValue = self::normalizeFallback($fallback, $options, $depth, $this->maxBytes);
	}

	/** @internal */
	public static function createParsed(
		string $normalizedVariable,
		?CSSUnparsedValue $fallback,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	): self {
		static $ref = null;
		$ref ??= new ReflectionClass(self::class);
		/** @var self $instance */
		$instance = $ref->newInstanceWithoutConstructor();
		$effectiveMax = $maxBytes;
		$fbMax = $fallback?->getMaxBytesInternal();
		if ($fbMax !== null && ($effectiveMax === null || $fbMax < $effectiveMax)) {
			$effectiveMax = $fbMax;
		}
		$instance->maxBytes = $effectiveMax;
		$instance->variableValue = $normalizedVariable;
		$instance->fallbackValue = $fallback;
		return $instance;
	}

	/** @internal */
	public function getMaxBytesInternal(): ?int
	{
		return $this->maxBytes;
	}

	/** @internal */
	public function clampMaxBytesInternal(?int $limit): void
	{
		if ($limit !== null && ($this->maxBytes === null || $limit < $this->maxBytes)) {
			$this->maxBytes = $limit;
		}
	}

	/** @internal */
	public function shallowCloneInternal(): self
	{
		return self::createParsed($this->variableValue, $this->fallbackValue, $this->maxBytes);
	}

	/** @internal */
	public function setFallbackInternal(?CSSUnparsedValue $fallback): void
	{
		$this->fallbackValue = $fallback;
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
		$this->variable = $variable;
	}

	public function getFallback(): ?CSSUnparsedValue
	{
		return $this->fallbackValue;
	}

	/**
	 * @throws InvalidArgumentException When $fallback contains invalid structure or control bytes
	 */
	public function setFallback(
		CSSUnparsedValue|string|null $fallback,
		?CSSParserOptions $options = null,
		int $depth = 0,
	): void {
		$this->fallbackValue = self::normalizeFallback($fallback, $options, $depth, $this->maxBytes);
	}

	public function __clone(): void
	{
		if ($this->fallbackValue !== null) {
			$this->fallbackValue = $this->fallbackValue->clone();
		}
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		if ($this->fallbackValue === null) {
			return 'var(' . $this->variableValue . ')';
		}

		$maxBytes = $this->maxBytes;
		$fbMax = $this->fallbackValue->getMaxBytesInternal();
		if ($fbMax !== null && ($maxBytes === null || $fbMax < $maxBytes)) {
			$maxBytes = $fbMax;
		}

		return CSSUnparsedValue::stringifyIteratively(
			$this->fallbackValue->getValues(),
			$maxBytes,
			'var(' . $this->variableValue . ', ',
			')',
		);
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

	private function assignVariable(string $value): void
	{
		if ($this->maxBytes !== null && strlen($value) > $this->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS custom property name exceeds maximum size of %d bytes.', $this->maxBytes));
		}
		$normalized = CSSStyleDeclaration::normalizePropertyName($value);
		if (!str_starts_with($normalized, '--')) {
			throw new InvalidArgumentException('A custom property name beginning with "--" is required.');
		}
		$this->variableValue = $normalized;
	}

	private static function normalizeFallback(
		CSSUnparsedValue|string|null $fallback,
		?CSSParserOptions $options = null,
		int $depth = 0,
		?int $defaultMaxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	): ?CSSUnparsedValue {
		if ($fallback === null) {
			return null;
		}
		$options ??= new CSSParserOptions(maxBytes: $defaultMaxBytes);
		if ($fallback instanceof CSSUnparsedValue) {
			$rendered = CSSUnparsedValue::stringifyIteratively($fallback->getValues(), $options->maxBytes);
			CSSSyntaxScanner::assertValidFragment($rendered, 'CSS variable fallback', [';', '{', '}']);
			$cloned = $fallback->clone();
			$cloned->clampMaxBytesInternal($options->maxBytes);
			return $cloned;
		}
		if ($options->maxBytes !== null && strlen($fallback) > $options->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS variable fallback exceeds maximum size of %d bytes.', $options->maxBytes));
		}

		return CSSUnparsedValue::fromCssText($fallback, $options, $depth);
	}
}
