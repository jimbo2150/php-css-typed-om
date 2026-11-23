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
	private readonly CSSStyleProperties $style;

	public function __construct(CSSStyleDeclaration $style)
	{
		$this->style = CSSStyleProperties::fromDeclaration($style);
		$this->style->setParentRule($this);
	}

	public function getType(): int
	{
		return self::NESTED_DECLARATIONS_RULE;
	}

	public function getStyle(): CSSStyleProperties
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

	public function __isset(string $name): bool
	{
		return $name === 'style' || parent::__isset($name);
	}

	public function __clone(): void
	{
		parent::__clone();
		$this->style = clone $this->style;
		$this->style->setParentRule($this);
	}
}
