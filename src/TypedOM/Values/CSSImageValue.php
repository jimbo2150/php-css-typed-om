<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

final class CSSImageValue extends CSSStyleValue
{
	private string $image;

	public function __construct(string $image)
	{
		$image = trim($image);
		if ($image === '' || !CSSSyntaxScanner::isBalanced($image)
			|| !preg_match('/^(?:url|image|image-set|cross-fade|paint|element|(?:repeating-)?(?:linear|radial|conic)-gradient)\(/i', $image)) {
			throw new InvalidArgumentException('Invalid CSS image value.');
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
		throw new \Error('Undefined property: ' . static::class . '::$' . $name);
	}
}
