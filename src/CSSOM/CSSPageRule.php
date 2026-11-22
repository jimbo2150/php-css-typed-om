<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@page` rule, including its page descriptors and nested `@margin` rules.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSPageRule
 */
final class CSSPageRule extends CSSGroupingRule
{
	private CSSPageDescriptors $style;

	/** @param list<CSSMarginRule> $marginRules */
	public function __construct(string $selector, ?CSSStyleDeclaration $style = null, array $marginRules = [])
	{
		$selector = trim($selector);
		parent::__construct('page', $selector);

		if ($style instanceof CSSPageDescriptors) {
			$descriptors = $style;
		} else {
			$descriptors = new CSSPageDescriptors();
			foreach ($style ?? [] as $property => $declaration) {
				$descriptors->setProperty($property, $declaration['value'], $declaration['priority']);
			}
		}
		$descriptors->setParentRule($this);
		$this->style = $descriptors;

		if ($marginRules !== []) {
			$sheet = new CSSStyleSheet();
			$this->initializeRules($sheet);
			foreach ($marginRules as $rule) {
				$this->getCssRules()->append($rule);
			}
			$this->attach(null, null);
		}
	}

	public function getType(): int
	{
		return self::PAGE_RULE;
	}

	public function getStyle(): CSSPageDescriptors
	{
		return $this->style;
	}

	/** @return list<CSSMarginRule> */
	public function getMarginRules(): array
	{
		$rules = [];
		foreach ($this->getCssRules() as $rule) {
			if ($rule instanceof CSSMarginRule) {
				$rules[] = $rule;
			}
		}

		return $rules;
	}

	public function getSelectorText(): string
	{
		return $this->prelude;
	}

	/**
	 * @throws InvalidArgumentException When $selector contains disallowed syntax
	 */
	public function setSelectorText(string $selector): void
	{
		$selector = trim($selector);
		if ($selector !== '') {
			CSSSyntaxScanner::assertValidFragment($selector, '@page selector', [';', '{', '}']);
		}
		$this->prelude = $selector;
	}

	public function getCssText(): string
	{
		$parts = [];
		$declarations = $this->style->getCssText();
		if ($declarations !== '') {
			$parts[] = $declarations;
		}
		foreach ($this->getCssRules() as $rule) {
			$parts[] = $rule->getCssText();
		}
		$prelude = $this->prelude === '' ? '' : ' ' . $this->prelude;

		return '@page' . $prelude . ' { ' . implode(' ', $parts) . ' }';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'selectorText' => $this->getSelectorText(),
			'style' => $this->getStyle(),
			default => parent::__get($name),
		};
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
