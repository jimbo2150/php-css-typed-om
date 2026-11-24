<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS image value such as `url(...)` or a CSS gradient function.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSImageValue
 */
final class CSSImageValue extends CSSStyleValue
{
	public readonly string $image;

	/**
	 * @throws InvalidArgumentException When $image is not a single balanced CSS image function or exceeds size limits
	 */
	public function __construct(string $image, ?CSSParserOptions $options = null)
	{
		$maxBytes = $options !== null ? $options->maxBytes : CSSParserOptions::DEFAULT_MAX_BYTES;
		if ($maxBytes !== null && strlen($image) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS image value exceeds maximum size of %d bytes.', $maxBytes));
		}
		$image = CSSSyntaxScanner::trimCssWhitespace($image);
		if ($image === '' || CSSSyntaxScanner::hasControlCharacters($image) || !CSSSyntaxScanner::isBalanced($image)) {
			throw new InvalidArgumentException('Invalid CSS image value.');
		}
		$open = strpos($image, '(');
		$fn = $open !== false ? CSSSyntaxScanner::decodeLeadingFunctionName($image, $open) : null;
		if ($fn === null || preg_match('/^(?:url|image|image-set|cross-fade|paint|element|(?:repeating-)?(?:linear|radial|conic)-gradient)$/D', $fn) !== 1) {
			throw new InvalidArgumentException('Invalid CSS image value.');
		}
		if ($fn === 'url') {
			$close = CSSSyntaxScanner::findMatchingUrlEnd($image, $open);
		} else {
			CSSSyntaxScanner::assertValidFragment($image, 'CSS image value', [';', '{', '}'], [';', '{', '}']);
			$close = CSSSyntaxScanner::findMatchingDelimiter($image, $open);
		}
		if ($close !== strlen($image) - 1) {
			throw new InvalidArgumentException('CSS image value cannot contain trailing tokens.');
		}

		$this->image = $image;
	}

	public function getImage(): string
	{
		return $this->image;
	}

	public function clone(): static
	{
		return clone $this;
	}

	public function __toString(): string
	{
		return $this->image;
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
