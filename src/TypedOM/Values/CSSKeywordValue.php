<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use ReflectionClass;

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

	/**
	 * @internal Fast factory for hardcoded or pre-validated CSS keywords (e.g., `'none'`, `'currentcolor'`).
	 */
	public static function fromValidated(string $keyword): self
	{
		/** @var ReflectionClass<self>|null $reflection */
		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $instance */
		$instance = $reflection->newInstanceWithoutConstructor();
		$instance->keyword = $keyword;

		return $instance;
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
		if (strlen($value) > CSSParserOptions::DEFAULT_MAX_BYTES) {
			throw new CSSParserLimitException(sprintf('CSS keyword value exceeds maximum size of %d bytes.', CSSParserOptions::DEFAULT_MAX_BYTES));
		}
		$value = CSSSyntaxScanner::trimCssWhitespace($value);
		if ($value === '' || CSSSyntaxScanner::hasControlCharacters($value)) {
			throw new InvalidArgumentException('CSS keyword value must be a non-empty identifier.');
		}
		$length = strlen($value);
		$decoded = CSSSyntaxScanner::decodeIdentAt($value, 0, $length);
		if ($decoded === null || $decoded['end'] !== $length || $decoded['ident'] === '--') {
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
}
