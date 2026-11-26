<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\Grammar\CSSValueValidator;
use Jimbo2150\PhpCssTypedOm\Parser\CSSInputPreprocessor;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenizer;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;
use Traversable;

/**
 * Mutable, ordered CSS declaration block.
 *
 * Explicit setters validate against structural delimiters (`;`, `{`, `}`, unbalanced quotes/comments)
 * and leave state untouched on failure.
 *
 * @phpstan-consistent-constructor
 *
 * @property string $cssText
 * @property-read int $length
 * @property-read ?CSSRule $parentRule
 *
 * @implements ArrayAccess<string, string>
 * @implements IteratorAggregate<string, array{value: string, priority: string}>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSStyleDeclaration
 */
class CSSStyleDeclaration implements ArrayAccess, Countable, IteratorAggregate
{
	private const string LOWER_ASCII_NAME_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789_-';
	private const string CSS_WHITESPACE = " \t\n\r\f";
	private const string FAST_PATH_UNSAFE_BYTES = "\\\"'[]{}<;\r\f\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";

	/** @var array<string, array{value: string, priority: string}> */
	private array $declarations = [];

	/** @var ?list<string> */
	private ?array $cachedKeys = null;

	private ?CSSRule $ownerRule = null;
	private ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES;
	private ?int $maxDeclarations = CSSParserOptions::DEFAULT_MAX_DECLARATIONS;
	private ?int $maxDepth = CSSParserOptions::DEFAULT_MAX_DEPTH;
	private ?int $maxMathDepth = CSSParserOptions::DEFAULT_MAX_MATH_DEPTH;
	private int $contentBytes = 0;

	/**
	 * @throws InvalidArgumentException When $cssText contains invalid declarations or exceeds options limits
	 */
	public function __construct(string $cssText = '', ?CSSParserOptions $options = null)
	{
		if ($options !== null) {
			$this->maxBytes = $options->maxBytes;
			$this->maxDeclarations = $options->maxDeclarations;
			$this->maxDepth = $options->maxDepth;
			$this->maxMathDepth = $options->maxMathDepth;
		}
		if ($cssText !== '') {
			$this->setCssText($cssText, $options);
		}
	}

	public function __clone(): void
	{
		$owner = $this->ownerRule;
		if ($owner !== null) {
			$ownerMaxBytes = $owner->resolveMaxBytesInternal();
			if ($ownerMaxBytes !== null && ($this->maxBytes === null || $ownerMaxBytes < $this->maxBytes)) {
				$this->maxBytes = $ownerMaxBytes;
			}
			$sheet = $owner->getParentStyleSheet();
			if ($sheet !== null) {
				$sheetMaxDecl = $sheet->getMaxDeclarationsInternal();
				if ($sheetMaxDecl !== null && ($this->maxDeclarations === null || $sheetMaxDecl < $this->maxDeclarations)) {
					$this->maxDeclarations = $sheetMaxDecl;
				}
				$sheetMaxDepth = $sheet->getMaxDepthInternal();
				if ($sheetMaxDepth !== null && ($this->maxDepth === null || $sheetMaxDepth < $this->maxDepth)) {
					$this->maxDepth = $sheetMaxDepth;
				}
				$sheetMaxMathDepth = $sheet->getMaxMathDepthInternal();
				if ($sheetMaxMathDepth !== null && ($this->maxMathDepth === null || $sheetMaxMathDepth < $this->maxMathDepth)) {
					$this->maxMathDepth = $sheetMaxMathDepth;
				}
			}
		}
		$this->ownerRule = null;
		$this->cachedKeys = null;
	}

	/** @internal */
	public function resolveMaxBytesInternal(): ?int
	{
		$ownerBytes = $this->ownerRule !== null ? $this->ownerRule->resolveMaxBytesInternal() : null;
		if ($ownerBytes === null) {
			return $this->maxBytes;
		}
		if ($this->maxBytes === null) {
			return $ownerBytes;
		}

		return $this->maxBytes < $ownerBytes ? $this->maxBytes : $ownerBytes;
	}

	/** @internal */
	public function resolveMaxDeclarationsInternal(): ?int
	{
		$sheet = $this->ownerRule?->getParentStyleSheet();

		return CSSRule::minLimitInternal($this->maxDeclarations, $sheet?->getMaxDeclarationsInternal());
	}

	/** @internal */
	public function resolveMaxDepthInternal(): ?int
	{
		$sheet = $this->ownerRule?->getParentStyleSheet();

		return CSSRule::minLimitInternal($this->maxDepth, $sheet?->getMaxDepthInternal());
	}

	/** @internal */
	public function resolveMaxMathDepthInternal(): ?int
	{
		$sheet = $this->ownerRule?->getParentStyleSheet();

		return CSSRule::minLimitInternal($this->maxMathDepth, $sheet?->getMaxMathDepthInternal());
	}

	/** @internal */
	public function setLimitsInternal(
		?int $maxBytes,
		?int $maxDeclarations,
		?int $maxDepth = null,
		?int $maxMathDepth = null,
		bool $updateDepths = false,
	): void {
		$this->maxBytes = $maxBytes;
		$this->maxDeclarations = $maxDeclarations;
		if ($updateDepths) {
			$this->maxDepth = $maxDepth;
			$this->maxMathDepth = $maxMathDepth;
		}
	}

	/** @internal */
	public function clampLimitsInternal(
		?int $maxBytes,
		?int $maxDeclarations,
		?int $maxDepth = null,
		?int $maxMathDepth = null,
		bool $syncDepths = false,
	): void {
		if ($maxBytes !== null && ($this->maxBytes === null || $maxBytes < $this->maxBytes)) {
			$this->maxBytes = $maxBytes;
		}
		if ($maxDeclarations !== null && ($this->maxDeclarations === null || $maxDeclarations < $this->maxDeclarations)) {
			$this->maxDeclarations = $maxDeclarations;
		}
		if ($syncDepths) {
			if ($maxDepth !== null && ($this->maxDepth === null || $maxDepth < $this->maxDepth)) {
				$this->maxDepth = $maxDepth;
			}
			if ($maxMathDepth !== null && ($this->maxMathDepth === null || $maxMathDepth < $this->maxMathDepth)) {
				$this->maxMathDepth = $maxMathDepth;
			}
		}
	}

	/** @internal */
	public function setMaxBytesInternal(?int $maxBytes): void
	{
		$this->maxBytes = $maxBytes;
	}

	/** @internal */
	public function getContentBytesInternal(): int
	{
		return $this->contentBytes;
	}

	/** @internal */
	public function assertContentBytesWithinInternal(?int $maxBytes): void
	{
		if ($maxBytes !== null && $this->contentBytes > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS declaration block exceeds maximum size of %d bytes.', $maxBytes));
		}
	}

	/**
	 * Parses a CSS declaration block.
	 *
	 * @param list<string>|null $diagnostics Populated with error messages in non-strict mode
	 * @throws InvalidArgumentException In strict mode when a declaration is invalid or limits are exceeded
	 */
	public static function parse(
		string $cssText,
		bool $strict = true,
		?array &$diagnostics = null,
		?CSSParserOptions $options = null,
	): static {
		$style = new static();
		if ($options !== null) {
			$style->setLimitsInternal(
				$options->maxBytes,
				$options->maxDeclarations,
				$options->maxDepth,
				$options->maxMathDepth,
				true,
			);
		}
		$detailed = [];
		$style->parseInto($cssText, $strict, $detailed, 0, $options);
		$diagnostics ??= [];
		foreach ($detailed as $entry) {
			$diagnostics[] = $entry['message'];
		}

		return $style;
	}

	/**
	 * Parses a declaration block while recording exact byte offsets for diagnostics.
	 *
	 * @param list<array{message: string, offset: int}>|null $diagnostics
	 */
	public static function parseWithOffsets(
		string $cssText,
		bool $strict = true,
		?array &$diagnostics = null,
		int $baseOffset = 0,
		?CSSParserOptions $options = null,
	): static {
		$style = new static();
		if ($options !== null) {
			$style->setLimitsInternal(
				$options->maxBytes,
				$options->maxDeclarations,
				$options->maxDepth,
				$options->maxMathDepth,
				true,
			);
		}
		$diagnostics ??= [];
		$style->parseInto($cssText, $strict, $diagnostics, $baseOffset, $options);

		return $style;
	}

	/**
	 * Returns the list of allowed descriptor names if restricted by a subclass, or null if unrestricted.
	 *
	 * @return list<string>|null
	 */
	public static function getAllowedDescriptors(): ?array
	{
		$allowed = static::allowedDescriptors();
		return $allowed === null ? null : array_keys($allowed);
	}

	/**
	 * Override in descriptor subclasses to enforce a strict whitelist of descriptor names.
	 *
	 * @return array<string, true>|null
	 */
	protected static function allowedDescriptors(): ?array
	{
		return null;
	}

	protected static function allowsCustomProperties(): bool
	{
		return true;
	}

	protected static function allowsImportant(): bool
	{
		return true;
	}

	protected static function validatesPropertyValues(): bool
	{
		return true;
	}

	/** @internal */
	public function shouldValidatePropertyValuesInternal(): bool
	{
		if (!static::validatesPropertyValues()) {
			return false;
		}
		$owner = $this->ownerRule;
		if ($owner instanceof CSSPropertyRule
			|| $owner instanceof CSSCounterStyleRule
			|| $owner instanceof CSSFontPaletteValuesRule
			|| $owner instanceof CSSViewTransitionRule
			|| $owner instanceof CSSFontFaceRule
		) {
			return false;
		}

		return true;
	}

	/**
	 * @throws InvalidArgumentException When $name is not permitted on this declaration block
	 */
	protected static function assertAllowedDescriptor(string $name): void
	{
		if (str_starts_with($name, '--')) {
			if (!static::allowsCustomProperties()) {
				throw new InvalidArgumentException(sprintf('Custom property "%s" is not allowed in %s.', $name, static::class));
			}
			return;
		}

		$allowed = static::allowedDescriptors();
		if ($allowed !== null && !isset($allowed[$name])) {
			throw new InvalidArgumentException(sprintf('Descriptor "%s" is not allowed in %s.', $name, static::class));
		}
	}

	public function getParentRule(): ?CSSRule
	{
		return $this->ownerRule;
	}

	/** @internal Links this declaration block to its owning CSSRule. */
	public function setParentRule(?CSSRule $parentRule): void
	{
		$this->ownerRule = $parentRule;
	}

	public function getLength(): int
	{
		return count($this->declarations);
	}

	public function item(int $index): string
	{
		if ($index < 0 || $index >= count($this->declarations)) {
			return '';
		}

		$this->cachedKeys ??= array_keys($this->declarations);
		return $this->cachedKeys[$index];
	}

	public function getPropertyValue(string $property): string
	{
		$name = self::normalizePropertyName($property);

		return $this->declarations[$name]['value'] ?? '';
	}

	public function getPropertyPriority(string $property): string
	{
		$name = self::normalizePropertyName($property);

		return $this->declarations[$name]['priority'] ?? '';
	}

	/**
	 * @internal Direct array access for hot internal loops without ArrayIterator overhead.
	 * @return array<string, array{value: string, priority: string}>
	 */
	public function getDeclarationsRaw(): array
	{
		return $this->declarations;
	}

	/**
	 * Sets or removes a property declaration atomically.
	 *
	 * @throws InvalidArgumentException When the property name, value, priority, or size limit is invalid
	 */
	public function setProperty(string $property, string $value, string $priority = ''): void
	{
		$maxBytes = $this->resolveMaxBytesInternal();
		if ($maxBytes !== null && strlen($property) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property name exceeds maximum size of %d bytes.', $maxBytes));
		}
		if ($maxBytes !== null && strlen($value) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS property value exceeds maximum size of %d bytes.', $maxBytes));
		}

		$name = self::normalizePropertyName($property);
		static::assertAllowedDescriptor($name);
		$priority = strtolower(trim($priority, self::CSS_WHITESPACE));
		if ($priority !== '' && $priority !== 'important') {
			throw new InvalidArgumentException('Priority must be empty or "important".');
		}
		if ($priority === 'important' && !static::allowsImportant()) {
			throw new InvalidArgumentException(sprintf('!important is not allowed in %s.', static::class));
		}

		$value = str_contains($value, '\\')
			? CSSSyntaxScanner::trimCssWhitespace($value)
			: trim($value, self::CSS_WHITESPACE);
		if ($value === '') {
			$oldEntry = $this->declarations[$name] ?? null;
			$delta = $oldEntry !== null ? -1 : 0;
			if ($oldEntry !== null) {
				$this->contentBytes -= strlen($name) + strlen($oldEntry['value']);
				unset($this->declarations[$name]);
			}
			$this->cachedKeys = null;
			$this->notifySheetMutation($delta, $name === 'initial-value');
			return;
		}
		$oldEntry = $this->declarations[$name] ?? null;
		$oldBytes = $oldEntry !== null ? strlen($name) + strlen($oldEntry['value']) : 0;
		$newBytes = strlen($name) + strlen($value);
		$nextContentBytes = $this->contentBytes - $oldBytes + $newBytes;
		if ($maxBytes !== null && ($newBytes > $maxBytes || $nextContentBytes > $maxBytes)) {
			throw new CSSParserLimitException(sprintf('CSS property declaration exceeds maximum size of %d bytes.', $maxBytes));
		}

		self::validatePropertyValue($value);
		if (!str_starts_with($name, '--') && str_contains($value, '/*') && CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($value)) === '') {
			throw new InvalidArgumentException('CSS property value cannot be empty.');
		}
		if ($this->shouldValidatePropertyValuesInternal() && CSSStandardProperties::isKnown($name)) {
			$maxDepth = $this->resolveMaxDepthInternal();
			$maxMathDepth = $this->resolveMaxMathDepthInternal();
			$validationOptions = ($maxDepth === CSSParserOptions::DEFAULT_MAX_DEPTH
				&& $maxMathDepth === CSSParserOptions::DEFAULT_MAX_MATH_DEPTH
				&& $maxBytes === CSSParserOptions::DEFAULT_MAX_BYTES)
				? null
				: new CSSParserOptions(
					maxBytes: $maxBytes,
					maxDepth: $maxDepth,
					maxMathDepth: $maxMathDepth,
					maxDeclarations: $this->resolveMaxDeclarationsInternal(),
				);
			if (!CSSValueValidator::validateStandardProperty($name, $value, $validationOptions)) {
				throw new InvalidArgumentException(sprintf('Invalid value "%s" for CSS property "%s".', $value, $name));
			}
		}
		$delta = $oldEntry !== null ? 0 : 1;
		$this->assertSheetDeclarationGrowth($delta, null);
		$this->declarations[$name] = ['value' => $value, 'priority' => $priority];
		$this->contentBytes = $nextContentBytes;
		$this->cachedKeys = null;
		$this->notifySheetMutation($delta, $name === 'initial-value');
	}

	/**
	 * @internal Sets an already-normalized and validated property while still enforcing descriptor/important policies.
	 */
	public function setValidatedProperty(
		string $normalizedName,
		string $trimmedValue,
		string $priority = '',
		bool $respectExistingImportant = false,
		bool $enforceMaxBytes = true,
	): void {
		static::assertAllowedDescriptor($normalizedName);
		if ($priority === 'important' && !static::allowsImportant()) {
			throw new InvalidArgumentException(sprintf('!important is not allowed in %s.', static::class));
		}
		if ($trimmedValue === '' && !str_starts_with($normalizedName, '--')) {
			$oldEntry = $this->declarations[$normalizedName] ?? null;
			$delta = $oldEntry !== null ? -1 : 0;
			if ($oldEntry !== null) {
				$this->contentBytes -= strlen($normalizedName) + strlen($oldEntry['value']);
				unset($this->declarations[$normalizedName]);
			}
			$this->cachedKeys = null;
			$this->notifySheetMutation($delta, $normalizedName === 'initial-value');
			return;
		}
		$current = $this->declarations[$normalizedName] ?? null;
		if ($respectExistingImportant && $current !== null && $current['priority'] === 'important' && $priority !== 'important') {
			return;
		}

		$oldBytes = $current !== null ? strlen($normalizedName) + strlen($current['value']) : 0;
		$newBytes = strlen($normalizedName) + strlen($trimmedValue);
		$nextContentBytes = $this->contentBytes - $oldBytes + $newBytes;
		if ($enforceMaxBytes) {
			$maxBytes = $this->resolveMaxBytesInternal();
			if ($maxBytes !== null && $nextContentBytes > $maxBytes) {
				throw new CSSParserLimitException(sprintf('CSS declaration block exceeds maximum size of %d bytes.', $maxBytes));
			}
		}

		$delta = $current !== null ? 0 : 1;
		$this->assertSheetDeclarationGrowth($delta, null);
		$this->declarations[$normalizedName] = ['value' => $trimmedValue, 'priority' => $priority];
		$this->contentBytes = $nextContentBytes;
		$this->cachedKeys = null;
		$this->notifySheetMutation($delta, $normalizedName === 'initial-value');
	}

	public function removeProperty(string $property): string
	{
		$name = self::normalizePropertyName($property);
		$oldEntry = $this->declarations[$name] ?? null;
		$value = $oldEntry['value'] ?? '';
		$delta = $oldEntry !== null ? -1 : 0;
		if ($oldEntry !== null) {
			$this->contentBytes -= strlen($name) + strlen($oldEntry['value']);
			unset($this->declarations[$name]);
		}
		$this->cachedKeys = null;
		$this->notifySheetMutation($delta, $name === 'initial-value');

		return $value;
	}

	public function clear(): void
	{
		$delta = -count($this->declarations);
		$this->declarations = [];
		$this->contentBytes = 0;
		$this->cachedKeys = null;
		$this->notifySheetMutation($delta, true);
	}

	public function getCssText(): string
	{
		if ($this->declarations === []) {
			return '';
		}

		$serialized = [];
		foreach ($this->declarations as $property => $declaration) {
			$serialized[] = $declaration['priority'] === ''
				? $property . ': ' . $declaration['value'] . ';'
				: $property . ': ' . $declaration['value'] . ' !important;';
		}

		return implode(' ', $serialized);
	}

	/**
	 * Atomically replaces all declarations with the parsed contents of $cssText.
	 *
	 * @throws InvalidArgumentException When any declaration is invalid or limits are exceeded
	 */
	public function setCssText(string $cssText, ?CSSParserOptions $options = null): void
	{
		$sheet = $this->ownerRule?->getParentStyleSheet();
		if ($options !== null) {
			$effectiveOptions = $options;
		} else {
			$resolvedMaxBytes = $this->resolveMaxBytesInternal();
			$resolvedMaxDecls = $this->resolveMaxDeclarationsInternal();
			$resolvedMaxDepth = $this->resolveMaxDepthInternal();
			$resolvedMaxMathDepth = $this->resolveMaxMathDepthInternal();
			if ($sheet !== null
				&& $resolvedMaxBytes === $sheet->getMaxBytesInternal()
				&& $resolvedMaxDecls === $sheet->getMaxDeclarationsInternal()
				&& $resolvedMaxDepth === $sheet->getMaxDepthInternal()
				&& $resolvedMaxMathDepth === $sheet->getMaxMathDepthInternal()
			) {
				$effectiveOptions = $sheet->resolveParserOptionsInternal(null);
			} elseif ($sheet !== null
				|| $resolvedMaxBytes !== CSSParserOptions::DEFAULT_MAX_BYTES
				|| $resolvedMaxDecls !== CSSParserOptions::DEFAULT_MAX_DECLARATIONS
				|| $resolvedMaxDepth !== CSSParserOptions::DEFAULT_MAX_DEPTH
				|| $resolvedMaxMathDepth !== CSSParserOptions::DEFAULT_MAX_MATH_DEPTH
			) {
				$effectiveOptions = new CSSParserOptions(
					maxBytes: $resolvedMaxBytes,
					maxDepth: $resolvedMaxDepth,
					maxMathDepth: $resolvedMaxMathDepth,
					maxRules: $sheet?->getMaxRulesInternal() ?? CSSParserOptions::DEFAULT_MAX_RULES,
					maxDeclarations: $resolvedMaxDecls,
					maxErrors: $sheet?->getMaxErrorsInternal() ?? CSSParserOptions::DEFAULT_MAX_ERRORS,
				);
			} else {
				$effectiveOptions = null;
			}
		}
		$allowEmptyInitialValue = $this->ownerRule instanceof CSSPropertyRule;
		$validatePropertyGrammar = $this->shouldValidatePropertyValuesInternal();
		$sawEmptyInitialValue = false;
		$replacement = new static();
		$detailed = [];
		$replacement->parseInto($cssText, true, $detailed, 0, $effectiveOptions, $allowEmptyInitialValue, $sawEmptyInitialValue, $validatePropertyGrammar);
		$delta = count($replacement->declarations) - count($this->declarations);
		$this->assertSheetDeclarationGrowth($delta, $effectiveOptions);
		$this->declarations = $replacement->declarations;
		$this->contentBytes = $replacement->contentBytes;
		$this->cachedKeys = null;
		$this->notifySheetMutation($delta, true);
		if ($this->ownerRule instanceof CSSPropertyRule) {
			$this->ownerRule->setEmptyInitialValueInternal($sawEmptyInitialValue);
		}
	}

	private function assertSheetDeclarationGrowth(int $delta, ?CSSParserOptions $overrideOptions): void
	{
		if ($delta <= 0) {
			return;
		}
		$sheet = $this->ownerRule?->getParentStyleSheet();
		if ($overrideOptions !== null) {
			if ($overrideOptions->maxDeclarations !== null && (count($this->declarations) + $delta) > $overrideOptions->maxDeclarations) {
				throw new CSSParserLimitException(sprintf('Maximum CSS declaration limit of %d exceeded.', $overrideOptions->maxDeclarations));
			}
			if ($sheet !== null && $overrideOptions->maxDeclarations !== null && ($sheet->getTotalDeclarationCount() + $delta) > $overrideOptions->maxDeclarations) {
				throw new CSSParserLimitException(sprintf('Maximum CSS declaration limit of %d exceeded.', $overrideOptions->maxDeclarations));
			}

			return;
		}
		$maxDeclarations = $this->resolveMaxDeclarationsInternal();
		if ($maxDeclarations !== null && (count($this->declarations) + $delta) > $maxDeclarations) {
			throw new CSSParserLimitException(sprintf('Maximum CSS declaration limit of %d exceeded.', $maxDeclarations));
		}
		if ($sheet !== null) {
			$sheetMaxDecls = $sheet->getMaxDeclarationsInternal();
			if ($sheetMaxDecls !== null && ($sheet->getTotalDeclarationCount() + $delta) > $sheetMaxDecls) {
				throw new CSSParserLimitException(sprintf('Maximum CSS declaration limit of %d exceeded.', $sheetMaxDecls));
			}
		}
	}

	private function notifySheetMutation(int $delta, bool $clearEmptyInitialValue): void
	{
		if ($delta !== 0) {
			$this->ownerRule?->getParentStyleSheet()?->adjustTotalDeclarationCount($delta);
		}
		if ($clearEmptyInitialValue && $this->ownerRule instanceof CSSPropertyRule) {
			$this->ownerRule->clearEmptyInitialValueInternal();
		}
	}

	/** @return Traversable<string, array{value: string, priority: string}> */
	public function getIterator(): Traversable
	{
		return new ArrayIterator($this->declarations);
	}

	public function count(): int
	{
		return $this->getLength();
	}

	public function offsetExists(mixed $offset): bool
	{
		return is_string($offset) && isset($this->declarations[self::normalizePropertyName($offset)]);
	}

	public function offsetGet(mixed $offset): string
	{
		return is_string($offset) ? $this->getPropertyValue($offset) : '';
	}

	public function offsetSet(mixed $offset, mixed $value): void
	{
		if (!is_string($offset) || !is_string($value)) {
			throw new InvalidArgumentException('A CSS property name and string value are required.');
		}

		$this->setProperty($offset, $value);
	}

	public function offsetUnset(mixed $offset): void
	{
		if (is_string($offset)) {
			$this->removeProperty($offset);
		}
	}

	public function __toString(): string
	{
		return $this->getCssText();
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'cssText' => $this->getCssText(),
			'length' => $this->getLength(),
			'parentRule' => $this->getParentRule(),
			default => $this->getPropertyValue(self::propertyNameFromAccessor($name)),
		};
	}

	public function __isset(string $name): bool
	{
		return match ($name) {
			'cssText', 'length' => true,
			'parentRule' => $this->ownerRule !== null,
			default => isset($this->declarations[self::normalizePropertyName(self::propertyNameFromAccessor($name))]),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'cssText' && is_string($value)) {
			$this->setCssText($value);
			return;
		}
		if ($name === 'length' || $name === 'parentRule') {
			throw new Error('Cannot set read-only property ' . static::class . '::$' . $name);
		}
		if (is_float($value) && !is_finite($value)) {
			throw new InvalidArgumentException('CSS numeric property value must be finite.');
		}
		if (is_string($value) || is_int($value) || is_float($value)) {
			$this->setProperty(self::propertyNameFromAccessor($name), (string) $value);
			return;
		}
		throw new Error('Cannot set property ' . static::class . '::$' . $name);
	}

	/**
	 * Validates and normalizes a CSS property or custom property name.
	 *
	 * @throws InvalidArgumentException When $property is not a valid CSS identifier
	 */
	public static function normalizePropertyName(string $property): string
	{
		if ($property !== '') {
			$first = $property[0];
			if ((($first >= 'a' && $first <= 'z') || $first === '_')
				&& strspn($property, self::LOWER_ASCII_NAME_CHARS) === strlen($property)
			) {
				return $property;
			}
		}

		$hasBackslash = str_contains($property, '\\');
		$property = $hasBackslash
			? CSSSyntaxScanner::trimCssWhitespace($property)
			: trim($property, self::CSS_WHITESPACE);
		if (CSSSyntaxScanner::hasControlCharacters($property)) {
			throw new InvalidArgumentException('Invalid CSS property name.');
		}
		if ($hasBackslash && preg_match('//u', $property) !== 1) {
			throw new InvalidArgumentException('Invalid CSS property name: ' . $property);
		}
		if (str_starts_with($property, '--')) {
			if ($hasBackslash) {
				$decoded = CSSSyntaxScanner::decodeIdentAt($property, 0, strlen($property));
				if ($decoded === null
					|| $decoded['end'] !== strlen($property)
					|| strlen($decoded['ident']) <= 2
					|| preg_match('/[\x00-\x1F\x7F]/', $decoded['ident']) === 1
				) {
					throw new InvalidArgumentException('Invalid CSS custom property name: ' . $property);
				}

				return CSS::escape($decoded['ident']);
			} elseif (!preg_match('/^--(?:[-_a-zA-Z0-9]|[^\x00-\x7F])+$/uD', $property)) {
				throw new InvalidArgumentException('Invalid CSS custom property name: ' . $property);
			}

			return $property;
		}

		if ($hasBackslash) {
			$decoded = CSSSyntaxScanner::decodeIdentAt($property, 0, strlen($property));
			if ($decoded === null || $decoded['end'] !== strlen($property)) {
				throw new InvalidArgumentException('Invalid CSS property name: ' . $property);
			}
			if (str_starts_with($decoded['ident'], '--')) {
				if (strlen($decoded['ident']) <= 2
					|| preg_match('/[\x00-\x1F\x7F]/', $decoded['ident']) === 1
				) {
					throw new InvalidArgumentException('Invalid CSS custom property name: ' . $property);
				}

				return CSS::escape($decoded['ident']);
			}
			if (!preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_-]*$/D', $decoded['ident'])) {
				throw new InvalidArgumentException('Invalid CSS property name: ' . $property);
			}

			return strtolower($decoded['ident']);
		}

		if (!preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_-]*$/D', $property)) {
			throw new InvalidArgumentException('Invalid CSS property name: ' . $property);
		}

		return strtolower($property);
	}

	/**
	 * Validates a CSS property value for structural CSS injection and syntax balance.
	 *
	 * @throws InvalidArgumentException When the value contains disallowed structure or !important
	 */
	public static function validatePropertyValue(string $value): void
	{
		CSSSyntaxScanner::assertValidFragment($value, 'CSS property value', [';', '{', '}']);

		if (str_contains($value, '!') && CSSSyntaxScanner::findLastTopLevelImportant($value) !== null) {
			throw new InvalidArgumentException('Pass CSS priority separately instead of including !important in the value.');
		}
	}

	/**
	 * @internal Extracts a slice without quotes/escapes/comments/brackets/control-bytes if parens are balanced and free of `url(`/`-->`.
	 */
	public static function extractCleanAsciiSliceInternal(string $source, int $start, int $end): ?string
	{
		$len = $end - $start;
		if ($len <= 0) {
			return '';
		}
		$slice = substr($source, $start, $len);
		if (str_contains($slice, '/*') || str_contains($slice, '-->')) {
			return null;
		}
		if (!str_contains($slice, '(') && !str_contains($slice, ')')) {
			return $slice;
		}
		if (stripos($slice, 'url(') !== false) {
			return null;
		}
		$depth = 0;
		$pos = 0;
		while ($pos < $len) {
			$pos += strcspn($slice, '()', $pos, $len - $pos);
			if ($pos >= $len) {
				break;
			}
			if ($slice[$pos] === '(') {
				$depth++;
			} else {
				$depth--;
				if ($depth < 0) {
					return null;
				}
			}
			$pos++;
		}

		return $depth === 0 ? $slice : null;
	}

	/**
	 * @internal Fast path for an already-extracted clean ASCII declaration `$slice` starting at `$start`.
	 * @param list<array{message: string, offset: int}> $diagnostics
	 */
	public function parseCleanAsciiDeclarationInternal(
		string $slice,
		int $start,
		bool $strict,
		array &$diagnostics,
		?CSSParserOptions $options = null,
		int &$declarationCount = 0,
		bool $throwOnLimit = false,
		bool $allowSentinelSlot = false,
		bool $allowEmptyInitialValue = false,
		bool &$sawEmptyInitialValue = false,
		bool $validatePropertyGrammar = true,
	): bool {
		$declaration = trim($slice, " \t\n");
		if ($declaration === '') {
			return true;
		}
		$segmentOffset = $start + strspn($slice, " \t\n");
		$colon = strpos($declaration, ':');
		if ($colon === false) {
			self::handleInvalidDeclaration('Missing colon in declaration: ' . $declaration, $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		$property = trim(substr($declaration, 0, $colon), " \t\n");
		$value = trim(substr($declaration, $colon + 1), " \t\n");

		try {
			$name = self::normalizePropertyName($property);
			static::assertAllowedDescriptor($name);
		} catch (InvalidArgumentException $exception) {
			self::handleInvalidDeclaration($exception->getMessage(), $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		$priority = '';
		if ($value !== '' && str_contains($value, '!')) {
			$important = CSSSyntaxScanner::findLastTopLevelImportant($value);
			if ($important !== null) {
				if (!static::allowsImportant()) {
					self::handleInvalidDeclaration(
						sprintf('!important is not allowed in %s (%s).', static::class, $name),
						$segmentOffset,
						$strict,
						$diagnostics,
						$options,
						$allowSentinelSlot,
					);
					return true;
				}
				$priority = 'important';
				$value = rtrim(substr($value, 0, $important), " \t\n");
				if ($value !== '' && str_contains($value, '!') && CSSSyntaxScanner::findLastTopLevelImportant($value) !== null) {
					self::handleInvalidDeclaration('Duplicate !important in CSS declaration (' . $name . ').', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
					return true;
				}
			}
		}

		return $this->commitParsedDeclaration(
			$name,
			$value,
			$priority,
			$segmentOffset,
			$strict,
			$diagnostics,
			$options,
			$declarationCount,
			$throwOnLimit,
			$allowSentinelSlot,
			$allowEmptyInitialValue,
			$sawEmptyInitialValue,
			$validatePropertyGrammar,
		);
	}

	/**
	 * @internal Parses a single declaration from scanned CSS Syntax L3 tokens in [$start, $end).
	 * @param list<array{type: CSSTokenType, start: int, end: int, hasEscapes: bool, closedAtEof: bool, closedQuote: string, trailingBsAtEof: bool}> $tokens
	 * @param list<string> $unclosedBlocks
	 * @param list<array{message: string, offset: int}> $diagnostics
	 */
	public function parseDeclarationFromTokensInternal(
		string $source,
		int $start,
		int $end,
		array $tokens,
		bool $hasComments,
		?int $controlByteOffset,
		?int $badTokenOffset,
		?string $badTokenMessage,
		bool $hasUnclosedConstruct,
		array $unclosedBlocks,
		bool $hasTopLevelBraces,
		bool $strict,
		array &$diagnostics,
		?CSSParserOptions $options = null,
		int &$declarationCount = 0,
		bool $throwOnLimit = false,
		bool $allowSentinelSlot = false,
		bool $allowEmptyInitialValue = false,
		bool &$sawEmptyInitialValue = false,
		bool $browserMode = false,
		bool $validatePropertyGrammar = true,
	): bool {
		$segmentOffset = $tokens !== [] ? $tokens[0]['start'] : $start;

		if ($controlByteOffset !== null) {
			self::handleInvalidDeclaration('Disallowed control characters in CSS declaration.', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		if ($badTokenOffset !== null) {
			self::handleInvalidDeclaration($badTokenMessage ?? 'Malformed token in CSS declaration.', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		if ($hasTopLevelBraces) {
			self::handleInvalidDeclaration('Disallowed structural delimiter or unbalanced syntax in CSS declaration.', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		if ($hasUnclosedConstruct && !$browserMode) {
			self::handleInvalidDeclaration('Unclosed comment or string in CSS declaration.', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		if (!$browserMode && $unclosedBlocks !== []) {
			self::handleInvalidDeclaration('Unbalanced syntax in CSS declaration.', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		// Find first two non-whitespace tokens: must be <ident-token> followed by <colon-token>.
		$tokenCount = count($tokens);
		$idx = 0;
		while ($idx < $tokenCount && $tokens[$idx]['type'] === CSSTokenType::Whitespace) {
			$idx++;
		}
		if ($idx >= $tokenCount) {
			return true;
		}
		$nameTok = $tokens[$idx];
		if ($nameTok['type'] !== CSSTokenType::Ident) {
			$rawDecl = $hasComments
				? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($source, $start, $end))
				: CSSSyntaxScanner::trimCssWhitespace(substr($source, $start, $end - $start));
			self::handleInvalidDeclaration('Missing colon in declaration: ' . $rawDecl, $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		$idx++;
		while ($idx < $tokenCount && $tokens[$idx]['type'] === CSSTokenType::Whitespace) {
			$idx++;
		}
		if ($idx >= $tokenCount || $tokens[$idx]['type'] !== CSSTokenType::Colon) {
			$rawDecl = $hasComments
				? CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($source, $start, $end))
				: CSSSyntaxScanner::trimCssWhitespace(substr($source, $start, $end - $start));
			self::handleInvalidDeclaration('Missing colon in declaration: ' . $rawDecl, $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		$rawProp = substr($source, $nameTok['start'], $nameTok['end'] - $nameTok['start']);
		try {
			$name = self::normalizePropertyName($rawProp);
			static::assertAllowedDescriptor($name);
		} catch (InvalidArgumentException $exception) {
			self::handleInvalidDeclaration($exception->getMessage(), $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		$colonEnd = $tokens[$idx]['end'];
		$valStartIdx = $idx + 1;
		while ($valStartIdx < $tokenCount && $tokens[$valStartIdx]['type'] === CSSTokenType::Whitespace) {
			$valStartIdx++;
		}
		$valEndIdx = $tokenCount - 1;
		while ($valEndIdx >= $valStartIdx && $tokens[$valEndIdx]['type'] === CSSTokenType::Whitespace) {
			$valEndIdx--;
		}

		if ($valStartIdx > $valEndIdx) {
			return $this->commitParsedDeclaration(
				$name,
				'',
				'',
				$segmentOffset,
				$strict,
				$diagnostics,
				$options,
				$declarationCount,
				$throwOnLimit,
				$allowSentinelSlot,
				$allowEmptyInitialValue,
				$sawEmptyInitialValue,
				$validatePropertyGrammar,
			);
		}

		// Detect trailing !important on token stream only at top level ($unclosedBlocks === []), allowing whitespace/comments between '!' and 'important'.
		$priority = '';
		$valueSliceEnd = $tokens[$valEndIdx]['end'];
		$bangIdx = ($unclosedBlocks === [] && $valEndIdx - $valStartIdx >= 1)
			? self::matchTrailingImportantTokensInternal($source, $tokens, $valStartIdx, $valEndIdx)
			: null;
		if ($bangIdx !== null) {
			if (!static::allowsImportant()) {
				self::handleInvalidDeclaration(
					sprintf('!important is not allowed in %s (%s).', static::class, $name),
					$segmentOffset,
					$strict,
					$diagnostics,
					$options,
					$allowSentinelSlot,
				);
				return true;
			}
			$priority = 'important';
			$valEndIdx = $bangIdx - 1;
			while ($valEndIdx >= $valStartIdx && $tokens[$valEndIdx]['type'] === CSSTokenType::Whitespace) {
				$valEndIdx--;
			}
			if (self::matchTrailingImportantTokensInternal($source, $tokens, $valStartIdx, $valEndIdx) !== null) {
				self::handleInvalidDeclaration('Duplicate !important in CSS declaration (' . $name . ').', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
				return true;
			}
			$valueSliceEnd = $valEndIdx >= $valStartIdx ? $tokens[$valEndIdx]['end'] : $colonEnd;
		}

		if ($valStartIdx > $valEndIdx) {
			$value = '';
		} else {
			$valSliceStart = $tokens[$valStartIdx]['start'];
			$rawSlice = $hasComments
				? CSSSyntaxScanner::removeComments($source, $valSliceStart, $valueSliceEnd)
				: substr($source, $valSliceStart, $valueSliceEnd - $valSliceStart);
			$value = str_contains($rawSlice, '\\')
				? CSSSyntaxScanner::trimCssWhitespace($rawSlice)
				: trim($rawSlice, self::CSS_WHITESPACE);

			if ($browserMode && $priority === '') {
				$lastValTok = $tokens[$valEndIdx];
				$suffix = '';
				if ($lastValTok['closedAtEof']) {
					if ($lastValTok['type'] === CSSTokenType::String) {
						if ($lastValTok['trailingBsAtEof'] && str_ends_with($value, '\\')) {
							$value = substr($value, 0, -1);
						}
						$suffix .= $lastValTok['closedQuote'];
					} elseif ($lastValTok['type'] === CSSTokenType::Url) {
						if (self::endsWithUnescapedTrailingBackslash($value)) {
							$value = substr($value, 0, -1) . "\u{FFFD}";
						}
						$suffix .= ')';
					}
				}
				for ($b = count($unclosedBlocks) - 1; $b >= 0; $b--) {
					$suffix .= $unclosedBlocks[$b];
				}
				if ($suffix !== '') {
					$value .= $suffix;
				}
			}
		}

		if ($value !== '' && $options !== null && $options->maxBytes !== null && (strlen($name) + strlen($value)) > $options->maxBytes) {
			$message = sprintf('CSS property declaration exceeds maximum size of %d bytes.', $options->maxBytes);
			if ($strict || $throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			self::recordDiagnostic($message, $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		return $this->commitParsedDeclaration(
			$name,
			$value,
			$priority,
			$segmentOffset,
			$strict,
			$diagnostics,
			$options,
			$declarationCount,
			$throwOnLimit,
			$allowSentinelSlot,
			$allowEmptyInitialValue,
			$sawEmptyInitialValue,
			$validatePropertyGrammar,
		);
	}

	/**
	 * @internal Matches trailing `!important` (allowing intervening whitespace tokens) in `$tokens[$valStartIdx..$valEndIdx]`.
	 * @param list<array{type: CSSTokenType, start: int, end: int, hasEscapes: bool, closedAtEof: bool, closedQuote: string, trailingBsAtEof: bool}> $tokens
	 */
	public static function matchTrailingImportantTokensInternal(
		string $source,
		array $tokens,
		int $valStartIdx,
		int $valEndIdx,
	): ?int {
		if ($valEndIdx - $valStartIdx < 1) {
			return null;
		}
		$lastTok = $tokens[$valEndIdx];
		if ($lastTok['type'] !== CSSTokenType::Ident || !self::tokenIdentEqualsLower($source, $lastTok, 'important')) {
			return null;
		}
		$bangIdx = $valEndIdx - 1;
		while ($bangIdx >= $valStartIdx && $tokens[$bangIdx]['type'] === CSSTokenType::Whitespace) {
			$bangIdx--;
		}
		if ($bangIdx < $valStartIdx) {
			return null;
		}
		$bangTok = $tokens[$bangIdx];
		if ($bangTok['type'] === CSSTokenType::Delim
			&& ($bangTok['end'] - $bangTok['start']) === 1
			&& $source[$bangTok['start']] === '!'
		) {
			return $bangIdx;
		}

		return null;
	}

	/**
	 * @param array{type: CSSTokenType, start: int, end: int, hasEscapes: bool, closedAtEof: bool, closedQuote: string, trailingBsAtEof: bool} $token
	 */
	private static function tokenIdentEqualsLower(string $source, array $token, string $expectedLower): bool
	{
		$rawLen = $token['end'] - $token['start'];
		if (!$token['hasEscapes']) {
			return $rawLen === strlen($expectedLower)
				&& substr_compare($source, $expectedLower, $token['start'], $rawLen, true) === 0;
		}

		return strtolower(CSSSyntaxScanner::decodeCssIdentEscapes(substr($source, $token['start'], $rawLen))) === $expectedLower;
	}

	private static function endsWithUnescapedTrailingBackslash(string $value): bool
	{
		$len = strlen($value);
		$count = 0;
		for ($i = $len - 1; $i >= 0 && $value[$i] === '\\'; $i--) {
			$count++;
		}

		return ($count & 1) === 1;
	}

	/**
	 * @param list<array{message: string, offset: int}> $diagnostics
	 */
	private function commitParsedDeclaration(
		string $name,
		string $value,
		string $priority,
		int $segmentOffset,
		bool $strict,
		array &$diagnostics,
		?CSSParserOptions $options,
		int &$declarationCount,
		bool $throwOnLimit,
		bool $allowSentinelSlot,
		bool $allowEmptyInitialValue,
		bool &$sawEmptyInitialValue,
		bool $validatePropertyGrammar = true,
	): bool {
		if ($value === '' && !str_starts_with($name, '--')) {
			if ($allowEmptyInitialValue && $name === 'initial-value' && $priority === '') {
				if ($options !== null && $options->maxDeclarations !== null && $declarationCount >= $options->maxDeclarations) {
					$message = sprintf('Maximum CSS declaration limit of %d exceeded.', $options->maxDeclarations);
					if ($strict || $throwOnLimit) {
						throw new CSSParserLimitException($message);
					}
					self::recordDiagnostic($message, $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
					return false;
				}
				$current = $this->declarations['initial-value'] ?? null;
				if ($current !== null && $current['priority'] === 'important') {
					return true;
				}
				if ($declarationCount >= PHP_INT_MAX) {
					throw new InvalidArgumentException('CSS declaration count overflows integer bounds.');
				}
				$declarationCount++;
				if ($current !== null) {
					$this->contentBytes -= strlen('initial-value') + strlen($current['value']);
				}
				unset($this->declarations['initial-value']);
				$this->cachedKeys = null;
				$sawEmptyInitialValue = true;
				return true;
			}
			self::handleInvalidDeclaration('CSS declaration value cannot be empty: ' . $name, $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		if ($validatePropertyGrammar && CSSStandardProperties::isKnown($name)) {
			try {
				$validGrammar = CSSValueValidator::validateStandardProperty($name, $value, $options);
			} catch (CSSParserLimitException $exception) {
				if ($strict || $throwOnLimit) {
					throw $exception;
				}
				self::recordDiagnostic($exception->getMessage(), $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
				return false;
			}
			if (!$validGrammar) {
				self::handleInvalidDeclaration(
					sprintf('Invalid value "%s" for CSS property "%s".', $value, $name),
					$segmentOffset,
					$strict,
					$diagnostics,
					$options,
					$allowSentinelSlot,
				);
				return true;
			}
		}

		if ($options !== null && $options->maxDeclarations !== null && $declarationCount >= $options->maxDeclarations) {
			$message = sprintf('Maximum CSS declaration limit of %d exceeded.', $options->maxDeclarations);
			if ($strict || $throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			self::recordDiagnostic($message, $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
			return false;
		}

		if ($declarationCount >= PHP_INT_MAX) {
			throw new InvalidArgumentException('CSS declaration count overflows integer bounds.');
		}
		$declarationCount++;

		$current = $this->declarations[$name] ?? null;
		if ($current !== null && $current['priority'] === 'important' && $priority !== 'important') {
			return true;
		}
		if ($name === 'initial-value') {
			$sawEmptyInitialValue = false;
		}
		$oldBytes = $current !== null ? strlen($name) + strlen($current['value']) : 0;
		$this->contentBytes += strlen($name) + strlen($value) - $oldBytes;
		$this->declarations[$name] = ['value' => $value, 'priority' => $priority];
		$this->cachedKeys = null;

		return true;
	}

	private static function propertyNameFromAccessor(string $name): string
	{
		if ($name === 'cssFloat') {
			return 'float';
		}
		$name = preg_replace_callback('/[A-Z]/', static fn (array $match): string => '-' . strtolower($match[0]), $name) ?? $name;
		if (str_starts_with($name, 'webkit-') || str_starts_with($name, 'moz-') || str_starts_with($name, 'ms-') || str_starts_with($name, 'o-')) {
			$name = '-' . $name;
		}
		return $name;
	}

	/** @param list<array{message: string, offset: int}> $diagnostics */
	private function parseInto(
		string $cssText,
		bool $strict,
		array &$diagnostics,
		int $baseOffset,
		?CSSParserOptions $options = null,
		bool $allowEmptyInitialValue = false,
		bool &$sawEmptyInitialValue = false,
		?bool $validatePropertyGrammar = null,
	): void {
		$validatePropertyGrammar ??= $this->shouldValidatePropertyValuesInternal();
		if ($baseOffset < 0) {
			throw new InvalidArgumentException('Base offset cannot be negative.');
		}
		$options ??= CSSParserOptions::default();
		$browserMode = !$strict && $options->browserErrorRecovery;

		if ($browserMode) {
			try {
				$source = CSSInputPreprocessor::preprocess($cssText, $options);
			} catch (CSSParserLimitException $exception) {
				$diagnostics[] = ['message' => $exception->getMessage(), 'offset' => $baseOffset];
				return;
			}
			$startOffset = 0;
			$checkControlBytes = false;
		} else {
			if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
				$message = sprintf('CSS declaration block exceeds maximum size of %d bytes.', $options->maxBytes);
				if ($strict) {
					throw new CSSParserLimitException($message);
				}
				$diagnostics[] = ['message' => $message, 'offset' => $baseOffset];
				return;
			}
			$source = $cssText;
			$startOffset = str_starts_with($source, "\xEF\xBB\xBF") ? 3 : 0;
			$checkControlBytes = true;
		}

		$this->declarations = [];
		$this->contentBytes = 0;
		$this->cachedKeys = null;
		$length = strlen($source);
		$declarationCount = 0;
		$initialDiagCount = count($diagnostics);
		$sawEmptyInitialValue = false;

		$tokenizer = null;
		$pos = $startOffset;

		while ($pos < $length) {
			$span = strcspn($source, self::FAST_PATH_UNSAFE_BYTES, $pos, $length - $pos);
			$stop = $pos + $span;

			if (($stop >= $length || $source[$stop] === ';')
				&& ($cleanSlice = self::extractCleanAsciiSliceInternal($source, $pos, $stop)) !== null
			) {
				$ok = $this->parseCleanAsciiDeclarationInternal(
					$cleanSlice,
					$pos,
					$strict,
					$diagnostics,
					$options,
					$declarationCount,
					$strict,
					false,
					$allowEmptyInitialValue,
					$sawEmptyInitialValue,
					$validatePropertyGrammar,
				);
				if (!$ok) {
					break;
				}
				$pos = $stop < $length ? $stop + 1 : $length;
				$tokenizer?->setOffsetInternal($pos);
				continue;
			}

			$tokenizer ??= CSSTokenizer::forParserInternal($source, $options, $browserMode, $checkControlBytes, $pos, $length);
			$tokenizer->setOffsetInternal($pos);

			// Token-driven CSS Syntax L3 §5 declaration slice consumption
			$tokens = [];
			$blockStack = [];
			$hasComments = false;
			$controlByteOffset = null;
			$badTokenOffset = null;
			$badTokenMessage = null;
			$hasUnclosedConstruct = false;
			$hasTopLevelBraces = false;
			$sliceStart = $pos;
			$sliceEnd = $length;

			while (true) {
				$type = $tokenizer->advanceInternal();
				if ($tokenizer->tokenHadLeadingComment) {
					$hasComments = true;
				}
				if ($checkControlBytes && $tokenizer->commentControlErrorOffsets !== []) {
					$controlByteOffset ??= $tokenizer->commentControlErrorOffsets[0];
				}
				if ($tokenizer->tokenUnclosedCommentOffset !== null) {
					$hasUnclosedConstruct = true;
				}
				if ($tokenizer->tokenControlByteOffset !== null) {
					$controlByteOffset ??= $tokenizer->tokenControlByteOffset;
				}
				if ($type === CSSTokenType::Eof) {
					$sliceEnd = $length;
					break;
				}
				if ($type === CSSTokenType::Semicolon && $blockStack === []) {
					$sliceEnd = $tokenizer->tokenStart;
					break;
				}
				if ($type === CSSTokenType::BadString || $type === CSSTokenType::BadUrl) {
					$badTokenOffset ??= $tokenizer->tokenStart;
					$badTokenMessage ??= $type === CSSTokenType::BadString
						? 'Invalid CSS string token.'
						: 'Invalid CSS url() token.';
				}
				if ($tokenizer->tokenClosedAtEof) {
					$hasUnclosedConstruct = true;
				}
				if ($type === CSSTokenType::Delim && $tokenizer->tokenParseErrorMessage !== null) {
					// Standalone backslash before newline or EOF error already handled; keep token
				}

				if ($type === CSSTokenType::LeftParenthesis || $type === CSSTokenType::Function) {
					$blockStack[] = ')';
				} elseif ($type === CSSTokenType::LeftSquareBracket) {
					$blockStack[] = ']';
				} elseif ($type === CSSTokenType::LeftCurlyBracket) {
					if ($blockStack === []) {
						$hasTopLevelBraces = true;
					}
					$blockStack[] = '}';
				} elseif ($type === CSSTokenType::RightParenthesis
					|| $type === CSSTokenType::RightSquareBracket
					|| $type === CSSTokenType::RightCurlyBracket
				) {
					$closer = match ($type) {
						CSSTokenType::RightParenthesis => ')',
						CSSTokenType::RightSquareBracket => ']',
						CSSTokenType::RightCurlyBracket => '}',
					};
					if ($blockStack === []) {
						$hasTopLevelBraces = true;
					} elseif (end($blockStack) === $closer) {
						array_pop($blockStack);
						if ($blockStack === [] && $closer === '}') {
							$hasTopLevelBraces = true;
						}
					} else {
						$hasTopLevelBraces = true;
					}
				}

				$tokens[] = [
					'type' => $type,
					'start' => $tokenizer->tokenStart,
					'end' => $tokenizer->tokenEnd,
					'hasEscapes' => $tokenizer->tokenHasEscapes,
					'closedAtEof' => $tokenizer->tokenClosedAtEof,
					'closedQuote' => $tokenizer->tokenClosedQuote,
					'trailingBsAtEof' => $tokenizer->tokenTrailingBackslashAtEof,
				];
			}

			// In default mode, catch any raw control bytes inside the slice; in browser mode, check only non-comment token spans.
			if (!$browserMode && $controlByteOffset === null && CSSSyntaxScanner::hasControlCharactersInSlice($source, $sliceStart, $sliceEnd - $sliceStart)) {
				$controlByteOffset = $sliceStart;
			} elseif ($browserMode && $controlByteOffset === null) {
				if (!$hasComments) {
					if (CSSSyntaxScanner::hasControlCharactersInSlice($source, $sliceStart, $sliceEnd - $sliceStart)) {
						$controlByteOffset = $sliceStart;
					}
				} else {
					foreach ($tokens as $tok) {
						$tokLen = $tok['end'] - $tok['start'];
						if ($tokLen > 0 && CSSSyntaxScanner::hasControlCharactersInSlice($source, $tok['start'], $tokLen)) {
							$controlByteOffset = $tok['start'];
							break;
						}
					}
				}
			}

			if ($tokens === []) {
				if ($controlByteOffset !== null) {
					self::handleInvalidDeclaration('Disallowed control characters in CSS declaration.', $sliceStart, $strict, $diagnostics, $options, false);
				} elseif ($hasUnclosedConstruct && !$browserMode) {
					self::handleInvalidDeclaration('Unclosed comment or string in CSS declaration.', $sliceStart, $strict, $diagnostics, $options, false);
				}
				$pos = $tokenizer->getOffsetInternal();
				continue;
			}

			$ok = $this->parseDeclarationFromTokensInternal(
				$source,
				$sliceStart,
				$sliceEnd,
				$tokens,
				$hasComments,
				$controlByteOffset,
				$badTokenOffset,
				$badTokenMessage,
				$hasUnclosedConstruct,
				$blockStack,
				$hasTopLevelBraces,
				$strict,
				$diagnostics,
				$options,
				$declarationCount,
				$strict,
				false,
				$allowEmptyInitialValue,
				$sawEmptyInitialValue,
				$browserMode,
				$validatePropertyGrammar,
			);
			if (!$ok) {
				break;
			}
			$pos = $tokenizer->getOffsetInternal();
		}

		if ($baseOffset !== 0) {
			$totalDiag = count($diagnostics);
			for ($i = $initialDiagCount; $i < $totalDiag; $i++) {
				if ($diagnostics[$i]['offset'] > PHP_INT_MAX - $baseOffset) {
					throw new InvalidArgumentException('Diagnostic offset overflows integer bounds.');
				}
				$diagnostics[$i] = [
					'message' => $diagnostics[$i]['message'],
					'offset' => $baseOffset + $diagnostics[$i]['offset'],
				];
			}
		}
	}

	/** @param list<array{message: string, offset: int}> $diagnostics */
	private static function handleInvalidDeclaration(
		string $message,
		int $offset,
		bool $strict,
		array &$diagnostics,
		?CSSParserOptions $options = null,
		bool $allowSentinelSlot = false,
	): void {
		self::recordDiagnostic($message, $offset, $diagnostics, $options, $allowSentinelSlot);
		if ($strict) {
			throw new InvalidArgumentException($message);
		}
	}

	/** @param list<array{message: string, offset: int}> $diagnostics */
	private static function recordDiagnostic(
		string $message,
		int $offset,
		array &$diagnostics,
		?CSSParserOptions $options,
		bool $allowSentinelSlot = false,
	): void {
		$maxErrors = $options?->maxErrors;
		if ($maxErrors !== null && ($allowSentinelSlot ? count($diagnostics) > $maxErrors : count($diagnostics) >= $maxErrors)) {
			return;
		}
		$diagnostics[] = ['message' => $message, 'offset' => $offset];
	}
}
