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
	/** @var array<string, array{value: string, priority: string}> */
	private array $declarations = [];

	/** @var ?list<string> */
	private ?array $cachedKeys = null;

	private ?CSSRule $parentRule = null;

	/**
	 * @throws InvalidArgumentException When $cssText contains invalid declarations
	 */
	public function __construct(string $cssText = '')
	{
		if ($cssText !== '') {
			$this->setCssText($cssText);
		}
	}

	/**
	 * Parses a CSS declaration block.
	 *
	 * @param list<string>|null $diagnostics Populated with error messages in non-strict mode
	 * @throws InvalidArgumentException In strict mode when a declaration is invalid
	 */
	public static function parse(string $cssText, bool $strict = true, ?array &$diagnostics = null): self
	{
		$style = new self();
		$detailed = [];
		$style->parseInto($cssText, $strict, $detailed, 0);
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
	): self {
		$style = new self();
		$diagnostics ??= [];
		$style->parseInto($cssText, $strict, $diagnostics, $baseOffset);

		return $style;
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
	 * Sets or removes a property declaration atomically.
	 *
	 * @throws InvalidArgumentException When the property name, value, or priority is invalid
	 */
	public function setProperty(string $property, string $value, string $priority = ''): void
	{
		$name = self::normalizePropertyName($property);
		$priority = strtolower(trim($priority));
		if ($priority !== '' && $priority !== 'important') {
			throw new InvalidArgumentException('Priority must be empty or "important".');
		}

		$value = trim($value);
		if ($value === '') {
			unset($this->declarations[$name]);
			$this->cachedKeys = null;
			return;
		}

		self::validatePropertyValue($value);
		$this->declarations[$name] = ['value' => $value, 'priority' => $priority];
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
		$serialized = [];
		foreach ($this->declarations as $property => $declaration) {
			$priority = $declaration['priority'] === '' ? '' : ' !important';
			$serialized[] = $property . ': ' . $declaration['value'] . $priority . ';';
		}

		return implode(' ', $serialized);
	}

	/**
	 * Atomically replaces all declarations with the parsed contents of $cssText.
	 *
	 * @throws InvalidArgumentException When any declaration is invalid
	 */
	public function setCssText(string $cssText): void
	{
		$replacement = self::parse($cssText, true);
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

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'cssText' && is_string($value)) {
			$this->setCssText($value);
			return;
		}
		if ($name === 'length' || $name === 'parentRule') {
			throw new Error('Cannot set read-only property ' . static::class . '::$' . $name);
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
		$property = trim($property);
		if (CSSSyntaxScanner::hasControlCharacters($property)) {
			throw new InvalidArgumentException('Invalid CSS property name.');
		}
		if (str_starts_with($property, '--')) {
			if (!preg_match('/^--(?:[-_a-zA-Z0-9]|[^\x00-\x7F]|\\\\(?:[0-9a-fA-F]{1,6}\s?|[^\r\n\f]))+$/uD', $property)) {
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

		$important = CSSSyntaxScanner::findTopLevel($value, '!');
		if ($important !== null) {
			$tail = trim(CSSSyntaxScanner::removeComments(substr($value, $important + 1)));
			if (strcasecmp($tail, 'important') === 0) {
				throw new InvalidArgumentException('Pass CSS priority separately instead of including !important in the value.');
			}
		}
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
	private function parseInto(string $cssText, bool $strict, array &$diagnostics, int $baseOffset): void
	{
		$replacement = [];
		$length = strlen($cssText);
		$offset = 0;

		while ($offset < $length) {
			$next = CSSSyntaxScanner::findNextTopLevelDelimiter($cssText, [';'], $offset, $length);
			$segmentEnd = $next !== null ? $next['offset'] : $length;
			$rawSegment = substr($cssText, $offset, $segmentEnd - $offset);
			$leadingSpace = strlen($rawSegment) - strlen(ltrim($rawSegment));
			$segmentOffset = $baseOffset + $offset + $leadingSpace;
			$offset = $next !== null ? $next['offset'] + 1 : $length;

			if (CSSSyntaxScanner::hasUnclosedCommentOrString($rawSegment)) {
				self::handleInvalidDeclaration('Unclosed comment or string in CSS declaration.', $segmentOffset, $strict, $diagnostics);
				continue;
			}

			$declaration = trim(CSSSyntaxScanner::removeComments($rawSegment));
			if ($declaration === '') {
				continue;
			}

			if (CSSSyntaxScanner::hasControlCharacters($declaration)) {
				self::handleInvalidDeclaration('Disallowed control characters in CSS declaration.', $segmentOffset, $strict, $diagnostics);
				continue;
			}

			$colon = CSSSyntaxScanner::findTopLevel($declaration, ':');
			if ($colon === null) {
				self::handleInvalidDeclaration('Missing colon in declaration: ' . $declaration, $segmentOffset, $strict, $diagnostics);
				continue;
			}

			$property = trim(substr($declaration, 0, $colon));
			$value = trim(substr($declaration, $colon + 1));
			try {
				$name = self::normalizePropertyName($property);
			} catch (InvalidArgumentException $exception) {
				self::handleInvalidDeclaration($exception->getMessage(), $segmentOffset, $strict, $diagnostics);
				continue;
			}

			try {
				CSSSyntaxScanner::assertValidFragment($value, 'CSS property value', [';', '{', '}']);
			} catch (InvalidArgumentException $exception) {
				self::handleInvalidDeclaration($exception->getMessage() . ' (' . $name . ')', $segmentOffset, $strict, $diagnostics);
				continue;
			}

			$priority = '';
			$important = CSSSyntaxScanner::findTopLevel($value, '!');
			if ($important !== null) {
				$tail = trim(CSSSyntaxScanner::removeComments(substr($value, $important + 1)));
				if (strcasecmp($tail, 'important') === 0) {
					$priority = 'important';
					$value = rtrim(substr($value, 0, $important));
				}
			}

			if ($value === '' && !str_starts_with($name, '--')) {
				self::handleInvalidDeclaration('CSS declaration value cannot be empty: ' . $name, $segmentOffset, $strict, $diagnostics);
				continue;
			}

			$current = $replacement[$name] ?? null;
			if ($current !== null && $current['priority'] === 'important' && $priority !== 'important') {
				continue;
			}
			$replacement[$name] = ['value' => $value, 'priority' => $priority];
		}

		$this->declarations = $replacement;
		$this->cachedKeys = null;
	}

	/** @param list<array{message: string, offset: int}> $diagnostics */
	private static function handleInvalidDeclaration(string $message, int $offset, bool $strict, array &$diagnostics): void
	{
		$diagnostics[] = ['message' => $message, 'offset' => $offset];
		if ($strict) {
			throw new InvalidArgumentException($message);
		}
	}
}
