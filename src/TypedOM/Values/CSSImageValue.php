<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\TypedOM\Values;

/**
 * Represents a CSS image value.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSImageValue
 */
class CSSImageValue extends CSSStyleValue
{
	private string $url;

	public function __construct(string $url)
	{
		$this->url = $url;
		parent::__construct('image');
	}

	public function getUrl(): string
	{
		return $this->url;
	}

	public function toString(): string
	{
		return 'url('.$this->url.')';
	}

	public function isValid(): bool
	{
		// Basic validation for URL format.
		return false !== filter_var($this->url, FILTER_VALIDATE_URL);
	}

	public function clone(): CSSStyleValue
	{
		return new self($this->url);
	}
}
