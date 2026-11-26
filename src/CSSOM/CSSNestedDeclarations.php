<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Represents declarations interleaved inside a nested grouping or style rule.
 *
 * @property-read CSSStyleProperties $style
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSNestedDeclarations
 */
final class CSSNestedDeclarations extends CSSRule
{
	private readonly CSSStyleProperties $styleProperties;

	public function __construct(
		CSSStyleDeclaration $style,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null) {
			$style->assertContentBytesWithinInternal($maxBytes);
		}
		$this->maxBytes = $maxBytes;
		$this->styleProperties = CSSStyleProperties::fromDeclaration($style);
		$this->styleProperties->clampLimitsInternal($maxBytes, null, null, null, false);
		$this->styleProperties->setParentRule($this);
	}

	public function getOwnedStyleInternal(): CSSStyleProperties
	{
		return $this->styleProperties;
	}

	public function getType(): int
	{
		return self::NESTED_DECLARATIONS_RULE;
	}

	public function getStyle(): CSSStyleProperties
	{
		return $this->styleProperties;
	}

	public function getCssText(): string
	{
		return $this->styleProperties->getCssText();
	}

	public function __get(string $name): mixed
	{
		return $name === 'style' ? $this->styleProperties : parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'style' || parent::__isset($name);
	}

	public function __clone(): void
	{
		parent::__clone();
		$this->styleProperties = clone $this->styleProperties;
		$this->styleProperties->setParentRule($this);
	}
}
