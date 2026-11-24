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
use Throwable;
use WeakReference;

/**
 * Represents a CSS `var(--name, fallback)` custom property reference.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSVariableReferenceValue
 */
final class CSSVariableReferenceValue extends CSSStyleValue
{
	private string $variableValue;

	private ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES;

	private int $maxDepth = CSSUnparsedValue::MAX_UNPARSED_DEPTH;

	/** @var ?WeakReference<CSSUnparsedValue> */
	private ?WeakReference $parentUnparsed = null;

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
			$this->setFallback($value);
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
		$effectiveMaxDepth = $options->maxDepth !== null
			? min(CSSUnparsedValue::MAX_UNPARSED_DEPTH, $options->maxDepth)
			: CSSUnparsedValue::MAX_UNPARSED_DEPTH;
		if ($depth > $effectiveMaxDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $effectiveMaxDepth));
		}

		$this->maxBytes = $options->maxBytes;
		$this->maxDepth = $effectiveMaxDepth;
		$this->assignVariable($variable, false);
		$this->fallbackValue = self::normalizeFallback($fallback, $options, $depth, $this->maxBytes, $effectiveMaxDepth);
		$this->fallbackValue?->setParentRefInternal($this);
		$this->assertRenderedWithinMaxBytesInternal($this->maxBytes);
	}

	/** @internal */
	public static function createParsed(
		string $normalizedVariable,
		?CSSUnparsedValue $fallback,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
		int $maxDepth = CSSUnparsedValue::MAX_UNPARSED_DEPTH,
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
		$effectiveDepth = $maxDepth;
		$fbDepth = $fallback?->getMaxDepthInternal();
		if ($fbDepth !== null && $fbDepth < $effectiveDepth) {
			$effectiveDepth = $fbDepth;
		}
		$instance->maxBytes = $effectiveMax;
		$instance->maxDepth = $effectiveDepth;
		$instance->variableValue = $normalizedVariable;
		$instance->fallbackValue = $fallback;
		$instance->parentUnparsed = null;
		$fallback?->setParentRefInternal($instance);
		return $instance;
	}

	/** @internal */
	public function getMaxBytesInternal(): ?int
	{
		return $this->maxBytes;
	}

	/** @internal */
	public function getMaxDepthInternal(): int
	{
		return $this->maxDepth;
	}

	/** @internal */
	public function setParentUnparsedInternal(?CSSUnparsedValue $parent): void
	{
		$this->parentUnparsed = $parent !== null ? WeakReference::create($parent) : null;
	}

	/** @internal */
	public function getParentUnparsedInternal(): ?CSSUnparsedValue
	{
		return $this->parentUnparsed?->get();
	}

	/**
	 * @internal
	 * @return array{root: CSSUnparsedValue|CSSVariableReferenceValue, depth: int, maxBytes: ?int, maxDepth: int}
	 */
	public function resolveRootBudgetInternal(): array
	{
		$current = $this;
		$varDepth = 0;
		$maxBytes = $this->maxBytes;
		$maxDepth = $this->maxDepth;
		$visited = [spl_object_id($this) => true];

		while (true) {
			if ($current instanceof CSSUnparsedValue) {
				$parent = $current->getParentRefInternal();
				if ($parent === null) {
					break;
				}
				$id = spl_object_id($parent);
				if (isset($visited[$id])) {
					break;
				}
				$visited[$id] = true;
				$varDepth++;
				$pBytes = $parent->getMaxBytesInternal();
				if ($pBytes !== null && ($maxBytes === null || $pBytes < $maxBytes)) {
					$maxBytes = $pBytes;
				}
				$pDepth = $parent->getMaxDepthInternal();
				if ($pDepth < $maxDepth) {
					$maxDepth = $pDepth;
				}
				$current = $parent;
			} else {
				$parent = $current->getParentUnparsedInternal();
				if ($parent === null) {
					break;
				}
				$id = spl_object_id($parent);
				if (isset($visited[$id])) {
					break;
				}
				$visited[$id] = true;
				$pBytes = $parent->getMaxBytesInternal();
				if ($pBytes !== null && ($maxBytes === null || $pBytes < $maxBytes)) {
					$maxBytes = $pBytes;
				}
				$pDepth = $parent->getMaxDepthInternal();
				if ($pDepth < $maxDepth) {
					$maxDepth = $pDepth;
				}
				$current = $parent;
			}
		}

		return [
			'root' => $current,
			'depth' => $varDepth,
			'maxBytes' => $maxBytes,
			'maxDepth' => $maxDepth,
		];
	}

	/** @internal Updates only this reference node's own byte limit without traversing fallback trees. */
	public function clampMaxBytesScalarInternal(?int $limit): void
	{
		if ($limit !== null && ($this->maxBytes === null || $limit < $this->maxBytes)) {
			$this->maxBytes = $limit;
		}
	}

	/** @internal */
	public function clampMaxBytesInternal(?int $limit): void
	{
		if ($limit === null) {
			return;
		}
		$this->clampMaxBytesScalarInternal($limit);
		$this->fallbackValue?->clampMaxBytesInternal($limit);
	}

	/** @internal */
	public function shallowCloneInternal(): self
	{
		static $ref = null;
		$ref ??= new ReflectionClass(self::class);
		/** @var self $instance */
		$instance = $ref->newInstanceWithoutConstructor();
		$instance->maxBytes = $this->maxBytes;
		$instance->maxDepth = $this->maxDepth;
		$instance->variableValue = $this->variableValue;
		$instance->fallbackValue = $this->fallbackValue;
		$instance->parentUnparsed = null;

		return $instance;
	}

	/** @internal */
	public function setFallbackInternal(?CSSUnparsedValue $fallback): void
	{
		$this->fallbackValue = $fallback;
	}

	/** @internal */
	public function assertRenderedWithinMaxBytesInternal(?int $maxBytes): void
	{
		if ($this->fallbackValue === null) {
			if ($maxBytes !== null && (strlen($this->variableValue) + 5) > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $maxBytes));
			}

			return;
		}

		CSSUnparsedValue::stringifyIteratively(
			$this->fallbackValue->getValues(),
			$maxBytes,
			'var(' . $this->variableValue . ', ',
			')',
		);
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
	 * @throws InvalidArgumentException When $fallback contains invalid structure, control bytes, or exceeds limits
	 */
	public function setFallback(
		CSSUnparsedValue|string|null $fallback,
		?CSSParserOptions $options = null,
		int $depth = 0,
	): void {
		$budget = $this->resolveRootBudgetInternal();
		$strictestMaxBytes = $budget['maxBytes'];
		$strictestMaxDepth = $budget['maxDepth'];
		if ($options?->maxDepth !== null && $options->maxDepth < $strictestMaxDepth) {
			$strictestMaxDepth = $options->maxDepth;
		}
		$effectiveDepth = max($depth, $budget['depth']);
		$effectiveOptions = $options ?? new CSSParserOptions(maxBytes: $strictestMaxBytes, maxDepth: $strictestMaxDepth);
		$nextFallback = self::normalizeFallback($fallback, $effectiveOptions, $effectiveDepth, $strictestMaxBytes, $strictestMaxDepth);

		$previousFallback = $this->fallbackValue;
		$this->fallbackValue = $nextFallback;
		try {
			$localLimit = $options?->maxBytes !== null && ($strictestMaxBytes === null || $options->maxBytes < $strictestMaxBytes)
				? $options->maxBytes
				: $strictestMaxBytes;
			$this->assertRenderedWithinMaxBytesInternal($localLimit);
			$root = $budget['root'];
			if ($root !== $this) {
				if ($root instanceof CSSUnparsedValue) {
					CSSUnparsedValue::stringifyIteratively($root->getValues(), $strictestMaxBytes);
				} else {
					$root->assertRenderedWithinMaxBytesInternal($strictestMaxBytes);
				}
			}
		} catch (Throwable $e) {
			$this->fallbackValue = $previousFallback;
			throw $e;
		}

		if ($previousFallback !== null && $previousFallback !== $nextFallback) {
			$previousFallback->setParentRefInternal(null);
		}
		$nextFallback?->setParentRefInternal($this);
	}

	public function __clone(): void
	{
		$this->parentUnparsed = null;
		if ($this->fallbackValue !== null) {
			$clonedFb = $this->fallbackValue->clone();
			$clonedFb->setParentRefInternal($this);
			$this->fallbackValue = $clonedFb;
		}
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		if ($this->fallbackValue === null) {
			$rendered = 'var(' . $this->variableValue . ')';
			if ($this->maxBytes !== null && strlen($rendered) > $this->maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $this->maxBytes));
			}

			return $rendered;
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

	private function assignVariable(string $value, bool $validateRoot = true): void
	{
		$budget = $this->resolveRootBudgetInternal();
		$strictestMaxBytes = $budget['maxBytes'];
		if ($strictestMaxBytes !== null && strlen($value) > $strictestMaxBytes) {
			throw new CSSParserLimitException(sprintf('CSS custom property name exceeds maximum size of %d bytes.', $strictestMaxBytes));
		}
		$normalized = CSSStyleDeclaration::normalizePropertyName($value);
		if (!str_starts_with($normalized, '--')) {
			throw new InvalidArgumentException('A custom property name beginning with "--" is required.');
		}
		if (!$validateRoot) {
			$this->variableValue = $normalized;
			return;
		}

		$previous = $this->variableValue;
		$this->variableValue = $normalized;
		try {
			$root = $budget['root'];
			if ($root instanceof CSSUnparsedValue) {
				CSSUnparsedValue::stringifyIteratively($root->getValues(), $strictestMaxBytes);
			} else {
				$root->assertRenderedWithinMaxBytesInternal($strictestMaxBytes);
			}
		} catch (Throwable $e) {
			$this->variableValue = $previous;
			throw $e;
		}
	}

	private static function normalizeFallback(
		CSSUnparsedValue|string|null $fallback,
		?CSSParserOptions $options = null,
		int $depth = 0,
		?int $defaultMaxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
		int $maxAllowedDepth = CSSUnparsedValue::MAX_UNPARSED_DEPTH,
	): ?CSSUnparsedValue {
		if ($fallback === null) {
			return null;
		}
		$options ??= new CSSParserOptions(maxBytes: $defaultMaxBytes, maxDepth: $maxAllowedDepth);
		$effectiveMaxDepth = $options->maxDepth !== null
			? min($maxAllowedDepth, $options->maxDepth, CSSUnparsedValue::MAX_UNPARSED_DEPTH)
			: min($maxAllowedDepth, CSSUnparsedValue::MAX_UNPARSED_DEPTH);
		if ($depth > $effectiveMaxDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $effectiveMaxDepth));
		}

		$effectiveMaxBytes = $defaultMaxBytes;
		if ($options->maxBytes !== null && ($effectiveMaxBytes === null || $options->maxBytes < $effectiveMaxBytes)) {
			$effectiveMaxBytes = $options->maxBytes;
		}

		if ($fallback instanceof CSSUnparsedValue) {
			CSSUnparsedValue::assertTreeMaxDepthInternal($fallback, $depth + 1, $effectiveMaxDepth);

			$rendered = CSSUnparsedValue::stringifyIteratively($fallback->getValues(), $effectiveMaxBytes);
			CSSSyntaxScanner::assertValidFragment($rendered, 'CSS variable fallback', [';', '{', '}']);
			$cloned = $fallback->clone();
			$cloned->clampMaxBytesInternal($effectiveMaxBytes);
			return $cloned;
		}

		if ($effectiveMaxBytes !== null && strlen($fallback) > $effectiveMaxBytes) {
			throw new CSSParserLimitException(sprintf('CSS variable fallback exceeds maximum size of %d bytes.', $effectiveMaxBytes));
		}
		$effectiveOptions = $effectiveMaxBytes === $options->maxBytes && $effectiveMaxDepth === $options->maxDepth
			? $options
			: new CSSParserOptions(
				maxBytes: $effectiveMaxBytes,
				maxDepth: $effectiveMaxDepth,
				maxMathDepth: $options->maxMathDepth,
				maxRules: $options->maxRules,
				maxDeclarations: $options->maxDeclarations,
				maxErrors: $options->maxErrors,
			);

		return CSSUnparsedValue::fromCssText($fallback, $effectiveOptions, $depth + 1);
	}
}
