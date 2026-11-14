<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS image value such as `url(...)` or a CSS gradient function.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSImageValue
 */
final class CSSImageValue extends CSSStyleValue
{
	private string $image;

	/**
	 * @throws InvalidArgumentException When $image is not a single balanced CSS image function
	 */
	public function __construct(string $image)
	{
		$image = trim($image);
		if ($image === '' || CSSSyntaxScanner::hasControlCharacters($image) || !CSSSyntaxScanner::isBalanced($image)) {
			throw new InvalidArgumentException('Invalid CSS image value.');
		}
		if (!preg_match('/^(?:url|image|image-set|cross-fade|paint|element|(?:repeating-)?(?:linear|radial|conic)-gradient)\(/i', $image)) {
			throw new InvalidArgumentException('Invalid CSS image value.');
		}
		$open = strpos($image, '(');
		$close = $open === false ? null : CSSSyntaxScanner::findMatchingDelimiter($image, $open);
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
		return new static($this->image);
	}

	public function __toString(): string
	{
		return $this->image;
	}

	public function __get(string $name): mixed
	{
		if ($name === 'image') {
			return $this->getImage();
		}
		throw new Error('Undefined property: ' . static::class . '::$' . $name);
	}
}
