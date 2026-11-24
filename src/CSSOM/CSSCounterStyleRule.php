<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@counter-style` rule.
 *
 * @property string $name
 * @property string $system
 * @property string $symbols
 * @property string $additiveSymbols
 * @property string $negative
 * @property string $prefix
 * @property string $suffix
 * @property string $range
 * @property string $pad
 * @property string $speakAs
 * @property string $fallback
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSCounterStyleRule
 */
final class CSSCounterStyleRule extends CSSAtRule
{
	/** @var list<string> */
	private const array DESCRIPTORS = [
		'system', 'symbols', 'additive-symbols', 'negative', 'prefix',
		'suffix', 'range', 'pad', 'speak-as', 'fallback',
	];

	public function __construct(string $name, CSSStyleDeclaration $descriptors)
	{
		$validated = self::validateName($name);
		parent::__construct('counter-style', $validated, $descriptors, true);
	}

	public function getNameText(): string
	{
		return $this->prelude;
	}

	public function setNameText(string $name): void
	{
		$this->prelude = self::validateName($name);
	}

	public function getDescriptor(string $name): string
	{
		return $this->styleDeclaration?->getPropertyValue($name) ?? '';
	}

	public function setDescriptor(string $name, string $value): void
	{
		$this->styleDeclaration?->setProperty($name, $value);
	}

	public function __get(string $name): mixed
	{
		if ($name === 'name') {
			return $this->getNameText();
		}
		$descriptor = self::camelToKebab($name);
		if (in_array($descriptor, self::DESCRIPTORS, true)) {
			return $this->getDescriptor($descriptor);
		}

		return parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		if ($name === 'name') {
			return true;
		}
		$descriptor = self::camelToKebab($name);
		if (in_array($descriptor, self::DESCRIPTORS, true)) {
			return true;
		}

		return parent::__isset($name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'name' && is_string($value)) {
			$this->setNameText($value);
			return;
		}
		$descriptor = self::camelToKebab($name);
		if (in_array($descriptor, self::DESCRIPTORS, true) && is_string($value)) {
			$this->setDescriptor($descriptor, $value);
			return;
		}
		parent::__set($name, $value);
	}

	private static function validateName(string $name): string
	{
		if (strlen($name) > CSSParserOptions::DEFAULT_MAX_BYTES) {
			throw new CSSParserLimitException(sprintf('CSS @counter-style name exceeds maximum size of %d bytes.', CSSParserOptions::DEFAULT_MAX_BYTES));
		}
		$name = CSSSyntaxScanner::trimCssWhitespace($name);
		if ($name === '') {
			throw new InvalidArgumentException('@counter-style name cannot be empty.');
		}
		if (CSSSyntaxScanner::hasControlCharacters($name)) {
			throw new InvalidArgumentException('Invalid @counter-style name.');
		}
		if (CSSSyntaxScanner::scanIdentEnd($name, 0, strlen($name)) !== strlen($name)) {
			throw new InvalidArgumentException('Invalid @counter-style name: ' . $name);
		}

		return $name;
	}

	private static function camelToKebab(string $name): string
	{
		return strtolower(preg_replace('/[A-Z]/', '-$0', $name) ?? $name);
	}
}
