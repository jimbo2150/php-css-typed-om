<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use ArrayAccess;
use ArrayIterator;
use Countable;
use Error;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Traversable;

/**
 * Mutable, ordered CSS declaration block.
 *
 * Explicit setters validate against structural delimiters (`;`, `{`, `}`, unbalanced quotes/comments)
 * and leave state untouched on failure.
 *
 * @implements ArrayAccess<string, string>
 * @implements IteratorAggregate<string, array{value: string, priority: string}>
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSStyleDeclaration
 */
class CSSStyleDeclaration implements ArrayAccess, Countable, IteratorAggregate
{
	private const string LOWER_ASCII_NAME_CHARS = 'abcdefghijklmnopqrstuvwxyz0123456789_-';
	private const string CSS_WHITESPACE = " \t\n\r\f";

	/** @var array<string, array{value: string, priority: string}> */
	private array $declarations = [];

	/** @var ?list<string> */
	private ?array $cachedKeys = null;

	private ?CSSRule $parentRule = null;

	/**
	 * @throws InvalidArgumentException When $cssText contains invalid declarations or exceeds options limits
	 */
	public function __construct(string $cssText = '', ?CSSParserOptions $options = null)
	{
		if ($cssText !== '') {
			$this->setCssText($cssText, $options);
		}
	}

	public function __clone(): void
	{
		$this->parentRule = null;
		$this->cachedKeys = null;
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
		return $this->parentRule;
	}

	public function setParentRule(?CSSRule $parentRule): void
	{
		$this->parentRule = $parentRule;
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
	 * @throws InvalidArgumentException When the property name, value, or priority is invalid
	 */
	public function setProperty(string $property, string $value, string $priority = ''): void
	{
		$name = self::normalizePropertyName($property);
		static::assertAllowedDescriptor($name);
		$priority = strtolower(trim($priority, self::CSS_WHITESPACE));
		if ($priority !== '' && $priority !== 'important') {
			throw new InvalidArgumentException('Priority must be empty or "important".');
		}
		if ($priority === 'important' && !static::allowsImportant()) {
			throw new InvalidArgumentException(sprintf('!important is not allowed in %s.', static::class));
		}

		$value = trim($value, self::CSS_WHITESPACE);
		if ($value === '') {
			unset($this->declarations[$name]);
			$this->cachedKeys = null;
			return;
		}

		self::validatePropertyValue($value);
		$this->declarations[$name] = ['value' => $value, 'priority' => $priority];
		$this->cachedKeys = null;
	}

	/**
	 * @internal Sets an already-normalized and validated property while still enforcing descriptor/important policies.
	 */
	public function setValidatedProperty(
		string $normalizedName,
		string $trimmedValue,
		string $priority = '',
		bool $respectExistingImportant = false,
	): void {
		static::assertAllowedDescriptor($normalizedName);
		if ($priority === 'important' && !static::allowsImportant()) {
			throw new InvalidArgumentException(sprintf('!important is not allowed in %s.', static::class));
		}
		if ($trimmedValue === '' && !str_starts_with($normalizedName, '--')) {
			unset($this->declarations[$normalizedName]);
			$this->cachedKeys = null;
			return;
		}
		if ($respectExistingImportant) {
			$current = $this->declarations[$normalizedName] ?? null;
			if ($current !== null && $current['priority'] === 'important' && $priority !== 'important') {
				return;
			}
		}

		$this->declarations[$normalizedName] = ['value' => $trimmedValue, 'priority' => $priority];
		$this->cachedKeys = null;
	}

	public function removeProperty(string $property): string
	{
		$name = self::normalizePropertyName($property);
		$value = $this->declarations[$name]['value'] ?? '';
		unset($this->declarations[$name]);
		$this->cachedKeys = null;

		return $value;
	}

	public function clear(): void
	{
		$this->declarations = [];
		$this->cachedKeys = null;
	}

	public function getCssText(): string
	{
		if ($this->declarations === []) {
			return '';
		}

		$serialized = [];
		foreach ($this->declarations as $property => $declaration) {
			$serialized[] = $property . ': ' . $declaration['value'] . ($declaration['priority'] === '' ? '' : ' !important') . ';';
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
		$replacement = static::parse($cssText, true, $diagnostics, $options);
		$this->declarations = $replacement->declarations;
		$this->cachedKeys = null;
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
			'parentRule' => $this->parentRule !== null,
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

		$property = trim($property, self::CSS_WHITESPACE);
		if (CSSSyntaxScanner::hasControlCharacters($property)) {
			throw new InvalidArgumentException('Invalid CSS property name.');
		}
		if (str_starts_with($property, '--')) {
			if (!preg_match('/^--(?:[-_a-zA-Z0-9]|[^\x00-\x7F]|' . CSSSyntaxScanner::IDENT_ESCAPE_REGEX . ')+$/uD', $property)) {
				throw new InvalidArgumentException('Invalid CSS custom property name: ' . $property);
			}
			return $property;
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
	 * @internal Parses a single declaration slice [$start, $end) directly into $this->declarations.
	 * @param list<array{message: string, offset: int}> $diagnostics
	 */
	public function parseDeclarationSlice(
		string $source,
		int $start,
		int $end,
		bool $strict,
		array &$diagnostics,
		?CSSParserOptions $options = null,
		int &$declarationCount = 0,
		bool $throwOnLimit = false,
		bool $allowSentinelSlot = false,
	): bool {
		if ($start >= $end) {
			return true;
		}

		$segmentOffset = self::findDeclarationStartOffset($source, $start, $end);
		if ($segmentOffset > $start && CSSSyntaxScanner::hasControlCharactersInSlice($source, $start, $segmentOffset - $start)) {
			self::handleInvalidDeclaration('Disallowed control characters in CSS declaration.', $start, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		if ($segmentOffset >= $end) {
			return true;
		}

		if (strcspn($source, "\\/\"'()[]{};\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F", $segmentOffset, $end - $segmentOffset) === ($end - $segmentOffset)) {
			$declaration = trim(substr($source, $segmentOffset, $end - $segmentOffset), self::CSS_WHITESPACE);
			if ($declaration === '') {
				return true;
			}
			$colon = strpos($declaration, ':');
			if ($colon === false) {
				self::handleInvalidDeclaration('Missing colon in declaration: ' . $declaration, $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
				return true;
			}
			$property = trim(substr($declaration, 0, $colon), self::CSS_WHITESPACE);
			$value = trim(substr($declaration, $colon + 1), self::CSS_WHITESPACE);

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
					$value = rtrim(substr($value, 0, $important), self::CSS_WHITESPACE);
				}
			}

			if ($value === '' && !str_starts_with($name, '--')) {
				self::handleInvalidDeclaration('CSS declaration value cannot be empty: ' . $name, $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
				return true;
			}

			if ($options !== null && $options->maxDeclarations !== null && $declarationCount >= $options->maxDeclarations) {
				$message = sprintf('Maximum CSS declaration limit of %d exceeded.', $options->maxDeclarations);
				if ($strict || $throwOnLimit) {
					throw new CSSParserLimitException($message);
				}
				self::recordDiagnostic($message, $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
				return false;
			}

			$declarationCount++;
			$current = $this->declarations[$name] ?? null;
			if ($current !== null && $current['priority'] === 'important' && $priority !== 'important') {
				return true;
			}
			$this->declarations[$name] = ['value' => $value, 'priority' => $priority];
			$this->cachedKeys = null;

			return true;
		}

		$slice = substr($source, $start, $end - $start);
		if (CSSSyntaxScanner::hasControlCharacters($slice)) {
			self::handleInvalidDeclaration('Disallowed control characters in CSS declaration.', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}
		if (CSSSyntaxScanner::hasUnclosedCommentOrString($slice)) {
			self::handleInvalidDeclaration('Unclosed comment or string in CSS declaration.', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		$raw = str_contains($slice, '/*') ? CSSSyntaxScanner::removeComments($slice) : $slice;
		$declaration = trim($raw, self::CSS_WHITESPACE);
		if ($declaration === '') {
			return true;
		}

		$colon = CSSSyntaxScanner::findTopLevel($declaration, ':');
		if ($colon === null) {
			self::handleInvalidDeclaration('Missing colon in declaration: ' . $declaration, $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		$property = trim(substr($declaration, 0, $colon), self::CSS_WHITESPACE);
		$value = trim(substr($declaration, $colon + 1), self::CSS_WHITESPACE);

		try {
			$name = self::normalizePropertyName($property);
			static::assertAllowedDescriptor($name);
		} catch (InvalidArgumentException $exception) {
			self::handleInvalidDeclaration($exception->getMessage(), $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		if ($value !== '' && (!CSSSyntaxScanner::isBalanced($value) || CSSSyntaxScanner::findNextTopLevelDelimiterBytes($value, ';{}') !== null)) {
			self::handleInvalidDeclaration('Disallowed structural delimiter or unbalanced syntax in CSS property value (' . $name . ')', $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
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
				$value = rtrim(substr($value, 0, $important), self::CSS_WHITESPACE);
			}
		}

		if ($value === '' && !str_starts_with($name, '--')) {
			self::handleInvalidDeclaration('CSS declaration value cannot be empty: ' . $name, $segmentOffset, $strict, $diagnostics, $options, $allowSentinelSlot);
			return true;
		}

		if ($options !== null && $options->maxDeclarations !== null && $declarationCount >= $options->maxDeclarations) {
			$message = sprintf('Maximum CSS declaration limit of %d exceeded.', $options->maxDeclarations);
			if ($strict || $throwOnLimit) {
				throw new CSSParserLimitException($message);
			}
			self::recordDiagnostic($message, $segmentOffset, $diagnostics, $options, $allowSentinelSlot);
			return false;
		}

		$declarationCount++;

		$current = $this->declarations[$name] ?? null;
		if ($current !== null && $current['priority'] === 'important' && $priority !== 'important') {
			return true;
		}
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
	): void {
		$options ??= new CSSParserOptions();
		if ($options->maxBytes !== null && strlen($cssText) > $options->maxBytes) {
			$message = sprintf('CSS declaration block exceeds maximum size of %d bytes.', $options->maxBytes);
			if ($strict) {
				throw new CSSParserLimitException($message);
			}
			$diagnostics[] = ['message' => $message, 'offset' => $baseOffset];
			return;
		}

		$this->declarations = [];
		$this->cachedKeys = null;
		$length = strlen($cssText);
		$offset = 0;
		$declarationCount = 0;
		$initialDiagCount = count($diagnostics);

		while ($offset < $length) {
			$next = CSSSyntaxScanner::findNextTopLevelDelimiter($cssText, [';'], $offset, $length);
			$segmentEnd = $next !== null ? $next['offset'] : $length;
			$ok = $this->parseDeclarationSlice(
				$cssText,
				$offset,
				$segmentEnd,
				$strict,
				$diagnostics,
				$options,
				$declarationCount,
				$strict,
			);
			if (!$ok) {
				break;
			}
			$offset = $next !== null ? $next['offset'] + 1 : $length;
		}

		if ($baseOffset !== 0) {
			$totalDiag = count($diagnostics);
			for ($i = $initialDiagCount; $i < $totalDiag; $i++) {
				$diagnostics[$i]['offset'] = $baseOffset + $diagnostics[$i]['offset'];
			}
		}
	}

	private static function findDeclarationStartOffset(string $source, int $start, int $end): int
	{
		$pos = $start;
		while ($pos < $end) {
			$pos += strspn($source, self::CSS_WHITESPACE, $pos, $end - $pos);
			if ($pos + 1 < $end && $source[$pos] === '/' && $source[$pos + 1] === '*') {
				$commentEnd = strpos($source, '*/', $pos + 2);
				if ($commentEnd === false || $commentEnd + 1 >= $end) {
					return $pos;
				}
				$pos = $commentEnd + 2;
				continue;
			}
			break;
		}

		return $pos;
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
