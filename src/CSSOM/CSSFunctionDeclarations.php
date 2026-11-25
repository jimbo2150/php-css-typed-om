<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

/**
 * Represents descriptor declarations nested inside a `@function` rule.
 *
 * @property-read CSSFunctionDescriptors $style
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSFunctionDeclarations
 */
final class CSSFunctionDeclarations extends CSSRule
{
	private readonly CSSFunctionDescriptors $styleDescriptors;

	public function __construct(
		CSSFunctionDescriptors $style,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
	) {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null) {
			$style->assertContentBytesWithinInternal($maxBytes);
		}
		$this->maxBytes = $maxBytes;
		$this->styleDescriptors = $style->getParentRule() !== null ? clone $style : $style;
		if ($maxBytes !== null && ($this->styleDescriptors->resolveMaxBytesInternal() === null || $maxBytes < $this->styleDescriptors->resolveMaxBytesInternal())) {
			$this->styleDescriptors->setMaxBytesInternal($maxBytes);
		}
		$this->styleDescriptors->setParentRule($this);
	}

	protected function syncChildLimitsInternal(?int $maxBytes, ?int $maxDeclarations, bool $syncDeclarations = false): void
	{
		if ($syncDeclarations) {
			$this->styleDescriptors->setLimitsInternal($maxBytes, $maxDeclarations);
		} else {
			$this->styleDescriptors->setMaxBytesInternal($maxBytes);
		}
	}

	public function getType(): int
	{
		return self::FUNCTION_DECLARATIONS_RULE;
	}

	public function getStyle(): CSSFunctionDescriptors
	{
		return $this->styleDescriptors;
	}

	public function getCssText(): string
	{
		return $this->styleDescriptors->getCssText();
	}

	public function __get(string $name): mixed
	{
		return $name === 'style' ? $this->getStyle() : parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'style' || parent::__isset($name);
	}

	public function __clone(): void
	{
		parent::__clone();
		$this->styleDescriptors = clone $this->styleDescriptors;
		$this->styleDescriptors->setParentRule($this);
	}
}
