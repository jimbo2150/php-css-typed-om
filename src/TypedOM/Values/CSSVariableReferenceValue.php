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
		if ($depth < 0 || $depth === PHP_INT_MAX) {
			throw new InvalidArgumentException('Nesting depth cannot be negative or overflow integer bounds.');
		}
		$options ??= CSSParserOptions::default();
		$effectiveMaxDepth = $options->maxDepth !== null
			? min(CSSUnparsedValue::MAX_UNPARSED_DEPTH, $options->maxDepth)
			: CSSUnparsedValue::MAX_UNPARSED_DEPTH;
		if (($depth + 1) > $effectiveMaxDepth) {
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
	 * @return array{
	 *     ancestors: list<array{node: CSSUnparsedValue|CSSVariableReferenceValue, varDepth: int}>,
	 *     root: CSSUnparsedValue|CSSVariableReferenceValue,
	 *     ancestorMinMaxBytes: ?int,
	 *     ancestorMinMaxDepth: int
	 * }
	 */
	public function resolveRootBudgetInternal(): array
	{
		return CSSUnparsedValue::buildAncestorBudgetInternal($this);
	}

	/** @internal Updates only this reference node's own byte limit without traversing fallback trees. */
	public function clampMaxBytesScalarInternal(?int $limit): void
	{
		if ($limit !== null && ($this->maxBytes === null || $limit < $this->maxBytes)) {
			$this->maxBytes = $limit;
		}
	}

	/** @internal Updates only this reference node's own depth limit without traversing fallback trees. */
	public function clampMaxDepthScalarInternal(int $limit): void
	{
		if ($limit < $this->maxDepth) {
			$this->maxDepth = $limit;
		}
	}

	/** @internal */
	public function clampLimitsInternal(?int $maxBytes, int $maxDepth): void
	{
		$this->clampMaxBytesScalarInternal($maxBytes);
		$this->clampMaxDepthScalarInternal($maxDepth);
		$this->fallbackValue?->clampLimitsInternal($maxBytes, $maxDepth);
	}

	/** @internal */
	public function clampMaxBytesInternal(?int $limit): void
	{
		if ($limit === null) {
			return;
		}
		$this->clampLimitsInternal($limit, CSSUnparsedValue::MAX_UNPARSED_DEPTH);
	}

	/** @internal Measures rendered byte length iteratively with immediate limit abort and no string allocations. */
	public function measureRenderedBytesInternal(?int $maxBytes = null): int
	{
		if ($this->fallbackValue === null) {
			$bytes = 5 + strlen($this->variableValue);
			if ($maxBytes !== null && $bytes > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $maxBytes));
			}

			return $bytes;
		}

		return CSSUnparsedValue::measureBytesIteratively(
			$this->fallbackValue->getValues(),
			$maxBytes,
			'var(' . $this->variableValue . ', ',
			')',
		);
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
		if ($maxBytes === null) {
			return;
		}
		$this->measureRenderedBytesInternal($maxBytes);
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
		if ($depth < 0 || $depth === PHP_INT_MAX) {
			throw new InvalidArgumentException('Nesting depth cannot be negative or overflow integer bounds.');
		}
		if ($fallback === null) {
			$previousFallback = $this->fallbackValue;
			$this->fallbackValue = null;
			if ($previousFallback !== null) {
				$previousFallback->setParentRefInternal(null);
			}
			return;
		}

		$budget = $this->resolveRootBudgetInternal();
		$ancestors = $budget['ancestors'];

		$callerMaxDepth = min(CSSUnparsedValue::MAX_UNPARSED_DEPTH, $options->maxDepth ?? $this->maxDepth);
		$minRemaining = $callerMaxDepth - ($depth + 1);
		$cap = $callerMaxDepth;
		foreach ($ancestors as $ancestor) {
			$aMaxDepth = $ancestor['node']->getMaxDepthInternal();
			$rem = $aMaxDepth - ($ancestor['varDepth'] + 1);
			if ($rem < $minRemaining) {
				$minRemaining = $rem;
				$cap = $aMaxDepth;
			}
		}
		if ($minRemaining < 0) {
			throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $cap));
		}

		$localMaxBytes = $options?->maxBytes !== null && ($this->maxBytes === null || $options->maxBytes < $this->maxBytes)
			? $options->maxBytes
			: $this->maxBytes;
		$wrapperBytes = strlen($this->variableValue) + 7;
		if ($localMaxBytes !== null && $wrapperBytes > $localMaxBytes) {
			throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $localMaxBytes));
		}
		$effectiveOptions = new CSSParserOptions(
			maxBytes: $localMaxBytes,
			maxDepth: $cap,
			maxMathDepth: $options->maxMathDepth ?? CSSParserOptions::DEFAULT_MAX_MATH_DEPTH,
			maxRules: $options->maxRules ?? CSSParserOptions::DEFAULT_MAX_RULES,
			maxDeclarations: $options->maxDeclarations ?? CSSParserOptions::DEFAULT_MAX_DECLARATIONS,
			maxErrors: $options->maxErrors ?? CSSParserOptions::DEFAULT_MAX_ERRORS,
		);
		$nextFallback = self::normalizeFallback($fallback, $effectiveOptions, $cap - $minRemaining - 1, $localMaxBytes, $cap);
		$nextFallback?->clampLimitsInternal(
			$budget['ancestorMinMaxBytes'],
			min($callerMaxDepth, $budget['ancestorMinMaxDepth']),
		);

		$previousFallback = $this->fallbackValue;
		$this->fallbackValue = $nextFallback;
		try {
			if ($localMaxBytes !== null) {
				$this->assertRenderedWithinMaxBytesInternal($localMaxBytes);
			}
			CSSUnparsedValue::assertAncestorsWithinMaxBytesInternal($ancestors);
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
			$clonedFb->clampLimitsInternal($this->maxBytes, $this->maxDepth);
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

		$wrapperBytes = strlen($this->variableValue) + 7;
		if ($this->maxBytes !== null && $wrapperBytes > $this->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $this->maxBytes));
		}

		$outerRemaining = $this->maxBytes !== null ? $this->maxBytes - $wrapperBytes : null;
		$fbMax = $this->fallbackValue->getMaxBytesInternal();
		$outerIsTighter = $outerRemaining !== null && ($fbMax === null || $outerRemaining < $fbMax);
		$effectiveFbLimit = $outerIsTighter ? $outerRemaining : $fbMax;

		try {
			$fbRendered = CSSUnparsedValue::stringifyIteratively(
				$this->fallbackValue->getValues(),
				$effectiveFbLimit,
			);
		} catch (CSSParserLimitException $e) {
			if ($outerIsTighter && str_contains($e->getMessage(), 'maximum size')) {
				throw new CSSParserLimitException(sprintf('CSS unparsed value exceeds maximum size of %d bytes.', $this->maxBytes));
			}
			throw $e;
		}

		return 'var(' . $this->variableValue . ', ' . $fbRendered . ')';
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
		if ($this->maxBytes !== null && strlen($value) > $this->maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS custom property name exceeds maximum size of %d bytes.', $this->maxBytes));
		}
		$normalized = CSSStyleDeclaration::normalizePropertyName($value);
		if (!str_starts_with($normalized, '--')) {
			throw new InvalidArgumentException('A custom property name beginning with "--" is required.');
		}
		if (!$validateRoot) {
			$this->variableValue = $normalized;
			return;
		}

		$budget = $this->resolveRootBudgetInternal();
		$previous = $this->variableValue;
		$this->variableValue = $normalized;
		try {
			CSSUnparsedValue::assertAncestorsWithinMaxBytesInternal($budget['ancestors']);
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
		if (($depth + 1) > $effectiveMaxDepth) {
			throw new CSSParserLimitException(sprintf('Maximum CSS var() nesting depth of %d exceeded.', $effectiveMaxDepth));
		}

		$effectiveMaxBytes = $defaultMaxBytes;
		if ($options->maxBytes !== null && ($effectiveMaxBytes === null || $options->maxBytes < $effectiveMaxBytes)) {
			$effectiveMaxBytes = $options->maxBytes;
		}

		if ($fallback instanceof CSSUnparsedValue) {
			if ($effectiveMaxBytes !== null) {
				$fallback->measureRenderedBytesInternal($effectiveMaxBytes);
			}
			CSSUnparsedValue::assertTreeMaxDepthInternal($fallback, $depth + 1, $effectiveMaxDepth);

			$rendered = CSSUnparsedValue::stringifyIteratively($fallback->getValues(), $effectiveMaxBytes);
			CSSSyntaxScanner::assertValidFragment($rendered, 'CSS variable fallback', [';', '{', '}']);
			$cloned = $fallback->clone();
			$cloned->clampLimitsInternal($effectiveMaxBytes, $effectiveMaxDepth);
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
