<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use ArrayAccess;
use Countable;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Traversable;

/** Mutable, ordered CSS declaration block. */
class CSSStyleDeclaration implements ArrayAccess, Countable, IteratorAggregate
{
	/** @var array<string, array{value: string, priority: string}> */
	private array $declarations = [];

	public function __construct(string $cssText = '')
	{
		if ($cssText !== '') {
			$this->setCssText($cssText);
		}
	}

	/** @param list<string>|null $diagnostics */
	public static function parse(string $cssText, bool $strict = true, ?array &$diagnostics = null): self
	{
		$style = new self();
		$style->parseInto($cssText, $strict, $diagnostics);

		return $style;
	}

	public function getLength(): int
	{
		return count($this->declarations);
	}

	public function item(int $index): string
	{
		if ($index < 0 || $index >= $this->getLength()) {
			return '';
		}

		return array_keys($this->declarations)[$index];
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
			return;
		}
		if (!CSSSyntaxScanner::isBalanced($value)) {
			throw new InvalidArgumentException('CSS property value has unbalanced syntax.');
		}
		$important = CSSSyntaxScanner::findTopLevel($value, '!');
		if ($important !== null && preg_match('/^!\s*important\s*$/i', substr($value, $important))) {
			throw new InvalidArgumentException('Pass CSS priority separately instead of including !important in the value.');
		}

		$this->declarations[$name] = ['value' => $value, 'priority' => $priority];
	}

	public function removeProperty(string $property): string
	{
		$name = self::normalizePropertyName($property);
		$value = $this->declarations[$name]['value'] ?? '';
		unset($this->declarations[$name]);

		return $value;
	}

	public function clear(): void
	{
		$this->declarations = [];
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

	public function setCssText(string $cssText): void
	{
		$diagnostics = [];
		$replacement = self::parse($cssText, true, $diagnostics);
		$this->declarations = $replacement->declarations;
	}

	/** @return Traversable<string, array{value: string, priority: string}> */
	public function getIterator(): Traversable
	{
		return new \ArrayIterator($this->declarations);
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
		if ($name === 'cssText') {
			return $this->getCssText();
		}
		if ($name === 'length') {
			return $this->getLength();
		}
		return $this->getPropertyValue(self::propertyNameFromAccessor($name));
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'cssText' && is_string($value)) {
			$this->setCssText($value);
			return;
		}
		if (is_string($value) || is_int($value) || is_float($value)) {
			$this->setProperty(self::propertyNameFromAccessor($name), (string) $value);
			return;
		}
		throw new \Error('Cannot set property ' . static::class . '::$' . $name);
	}

	private static function normalizePropertyName(string $property): string
	{
		$property = trim($property);
		if (!preg_match('/^(?:--[^\s:;{}]+|-?[a-zA-Z_][a-zA-Z0-9_-]*)$/D', $property)) {
			throw new InvalidArgumentException('Invalid CSS property name: ' . $property);
		}

		return str_starts_with($property, '--') ? $property : strtolower($property);
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

	/** @param list<string>|null $diagnostics */
	private function parseInto(string $cssText, bool $strict, ?array &$diagnostics): void
	{
		$diagnostics ??= [];
		$replacement = [];
		foreach (CSSSyntaxScanner::splitTopLevel($cssText, ';') as $declaration) {
			$declaration = trim(CSSSyntaxScanner::removeComments($declaration));
			if ($declaration === '') {
				continue;
			}

			$colon = CSSSyntaxScanner::findTopLevel($declaration, ':');
			if ($colon === null) {
				self::handleInvalidDeclaration('Missing colon in declaration: ' . $declaration, $strict, $diagnostics);
				continue;
			}

			$property = trim(substr($declaration, 0, $colon));
			$value = trim(substr($declaration, $colon + 1));
			try {
				$name = self::normalizePropertyName($property);
			} catch (InvalidArgumentException $exception) {
				self::handleInvalidDeclaration($exception->getMessage(), $strict, $diagnostics);
				continue;
			}
			if (!CSSSyntaxScanner::isBalanced($value)) {
				self::handleInvalidDeclaration('Unbalanced CSS property value: ' . $name, $strict, $diagnostics);
				continue;
			}

			$priority = '';
			$important = CSSSyntaxScanner::findTopLevel($value, '!');
			if ($important !== null && preg_match('/^!\s*important\s*$/i', substr($value, $important))) {
				$priority = 'important';
				$value = rtrim(substr($value, 0, $important));
			}

			if ($value === '' && !str_starts_with($name, '--')) {
				self::handleInvalidDeclaration('CSS declaration value cannot be empty: ' . $name, $strict, $diagnostics);
				continue;
			}

			$current = $replacement[$name] ?? null;
			if ($current !== null && $current['priority'] === 'important' && $priority !== 'important') {
				continue;
			}
			$replacement[$name] = ['value' => $value, 'priority' => $priority];
		}

		$this->declarations = $replacement;
	}

	/** @param list<string> $diagnostics */
	private static function handleInvalidDeclaration(string $message, bool $strict, array &$diagnostics): void
	{
		$diagnostics[] = $message;
		if ($strict) {
			throw new InvalidArgumentException($message);
		}
	}
}
