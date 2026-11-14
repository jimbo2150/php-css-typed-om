<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSNestedDeclarations extends CSSRule
{
	public function __construct(private readonly CSSStyleDeclaration $style)
	{
	}

	public function getType(): int
	{
		return self::UNKNOWN_RULE;
	}

	public function getStyle(): CSSStyleDeclaration
	{
		return $this->style;
	}

	public function getCssText(): string
	{
		return $this->style->getCssText();
	}
}
