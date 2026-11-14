<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSPageRule extends CSSAtRule
{
	/** @param list<CSSMarginRule> $marginRules */
	public function __construct(string $selector, CSSStyleDeclaration $style, private readonly array $marginRules = [])
	{
		$descriptors = new CSSPageDescriptors();
		foreach ($style as $property => $declaration) {
			$descriptors->setProperty($property, $declaration['value'], $declaration['priority']);
		}
		parent::__construct('page', $selector, $descriptors, true);
	}

	public function getStyle(): ?CSSPageDescriptors
	{
		return $this->style;
	}

	/** @return list<CSSMarginRule> */
	public function getMarginRules(): array
	{
		return $this->marginRules;
	}

	public function getSelectorText(): string
	{
		return $this->prelude;
	}

	public function setSelectorText(string $selector): void
	{
		$selector = trim($selector);
		if (str_contains($selector, '{') || str_contains($selector, '}')) {
			throw new \InvalidArgumentException('Invalid @page selector text.');
		}
		$this->prelude = $selector;
	}

	public function getCssText(): string
	{
		$parts = [];
		if ($this->style !== null && $this->style->getCssText() !== '') {
			$parts[] = $this->style->getCssText();
		}
		foreach ($this->marginRules as $rule) {
			$parts[] = $rule->getCssText();
		}
		$prelude = $this->prelude === '' ? '' : ' ' . $this->prelude;
		return '@page' . $prelude . ' { ' . implode(' ', $parts) . ' }';
	}

	public function __get(string $name): mixed
	{
		return $name === 'selectorText' ? $this->getSelectorText() : parent::__get($name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'selectorText' && is_string($value)) {
			$this->setSelectorText($value);
			return;
		}
		parent::__set($name, $value);
	}
}
