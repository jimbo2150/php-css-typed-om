<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS keyword identifier value (e.g., `'auto'`, `'none'`, `'inherit'`).
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSKeywordValue
 */
class CSSKeywordValue extends CSSStyleValue
{
	private string $keyword;

	public string $value {
		get => $this->keyword;
		set (string $newValue) {
			$this->setValue($newValue);
		}
	}

	/**
	 * @throws InvalidArgumentException When $keyword is not a valid CSS identifier
	 */
	public function __construct(string $keyword)
	{
		$this->setValue($keyword);
	}

	public function getKeyword(): string
	{
		return $this->keyword;
	}

	public function getValue(): string
	{
		return $this->keyword;
	}

	public function setValue(string $value): void
	{
		$value = CSSSyntaxScanner::trimCssWhitespace($value);
		if ($value === '' || CSSSyntaxScanner::hasControlCharacters($value)) {
			throw new InvalidArgumentException('CSS keyword value must be a non-empty identifier.');
		}
		if (!preg_match('/^(?:--(?:[-_a-zA-Z0-9]|[^\x00-\x7F]|' . CSSSyntaxScanner::IDENT_ESCAPE_REGEX . ')+|-?(?:[a-zA-Z_]|[^\x00-\x7F]|' . CSSSyntaxScanner::IDENT_ESCAPE_REGEX . ')(?:[-_a-zA-Z0-9]|[^\x00-\x7F]|' . CSSSyntaxScanner::IDENT_ESCAPE_REGEX . ')*)$/uD', $value)) {
			throw new InvalidArgumentException('CSS keyword value must be a non-empty identifier.');
		}
		$this->keyword = $value;
	}

	public function __toString(): string
	{
		return $this->keyword;
	}

	public function clone(): static
	{
		return clone $this;
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
}
