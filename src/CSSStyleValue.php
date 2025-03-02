<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm;

use Jimbo2150\PhpCssTypedOm\Css\CSSValue;

class CSSStyleValue
{
	protected string $m_customPropertyName;
	protected CSSValue $m_propertyValue;

	public static function parse(string $property, string $cssText): self
	{
		return CSSStyleValueFactory::parseStyleValue($property, $cssText);
	}

	public static function parseAll(string $property, string $value): self
	{
		return CSSStyleValueFactory::parseStyleValue($property, $value);
	}

	public function create(?CSSValue &$cssValue = null, ?string $property = null): self
	{
		return new static($cssValue, $property);
	}

	public function __construct(CSSValue &$cssValue, string $property)
	{
		$this->m_customPropertyName = $property;
		$this->m_propertyValue = $cssValue;
	}

	public function __toString(): string
	{
		return $this->m_propertyValue->cssText();
	}
}
