<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Represents declarations interleaved inside a nested grouping or style rule.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNestedDeclarations
 */
final class CSSNestedDeclarations extends CSSRule
{
	public function __construct(private readonly CSSStyleDeclaration $style)
	{
		$this->style->setParentRule($this);
	}

	public function getType(): int
	{
		return self::NESTED_DECLARATIONS_RULE;
	}

	public function getStyle(): CSSStyleDeclaration
	{
		return $this->style;
	}

	public function getCssText(): string
	{
		return $this->style->getCssText();
	}

	public function __get(string $name): mixed
	{
		return $name === 'style' ? $this->style : parent::__get($name);
	}
}
