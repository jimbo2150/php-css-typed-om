<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Represents descriptor declarations nested inside a `@function` rule.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSFunctionDeclarations
 */
final class CSSFunctionDeclarations extends CSSRule
{
	private readonly CSSFunctionDescriptors $style;

	public function __construct(CSSFunctionDescriptors $style)
	{
		$this->style = $style->getParentRule() !== null ? clone $style : $style;
		$this->style->setParentRule($this);
	}

	public function getType(): int
	{
		return self::FUNCTION_DECLARATIONS_RULE;
	}

	public function getStyle(): CSSFunctionDescriptors
	{
		return $this->style;
	}

	public function getCssText(): string
	{
		return $this->style->getCssText();
	}

	public function __get(string $name): mixed
	{
		return $name === 'style' ? $this->getStyle() : parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'style' || parent::__isset($name);
	}
}
