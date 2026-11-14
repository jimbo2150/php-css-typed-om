<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;

class CSSStyleRule extends CSSGroupingRule
{
	private string $selectorText;
	private CSSStyleProperties $style;

	public function __construct(string $selectorText, ?CSSStyleDeclaration $style = null)
	{
		parent::__construct('style', trim($selectorText));
		if (trim($selectorText) === '') {
			throw new \InvalidArgumentException('A CSS style rule requires a selector.');
		}
		$this->selectorText = trim($selectorText);
		$this->style = $style instanceof CSSStyleProperties ? $style : self::toStyleProperties($style);
	}

	public function getType(): int
	{
		return self::STYLE_RULE;
	}

	public function getSelectorText(): string
	{
		return $this->selectorText;
	}

	public function setSelectorText(string $selectorText): void
	{
		$selectorText = trim($selectorText);
		if ($selectorText === '' || str_contains($selectorText, '{') || str_contains($selectorText, '}')) {
			throw new \InvalidArgumentException('Invalid CSS selector text.');
		}

		$this->selectorText = $selectorText;
	}

	public function getStyle(): CSSStyleProperties
	{
		return $this->style;
	}

	public function getStyleMap(): StylePropertyMap
	{
		return new StylePropertyMap($this->style);
	}

	public function insertRule(string $ruleText, ?int $index = null): int
	{
		if ($this->getParentStyleSheet() === null) {
			throw new \LogicException('A detached style rule cannot insert nested rules.');
		}
		$rule = CSSParser::parseSingleNestedRule($ruleText);
		$index ??= count($this->getCssRules());
		$this->getCssRules()->insert($index, $rule);
		return $index;
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

		return $this->selectorText . ' { ' . implode(' ', $parts) . ' }';
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'selectorText' => $this->getSelectorText(),
			'style' => $this->getStyle(),
			'styleMap' => $this->getStyleMap(),
			'cssRules' => $this->getCssRules(),
			default => parent::__get($name),
		};
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'selectorText' && is_string($value)) {
			$this->setSelectorText($value);
			return;
		}
		throw new \Error('Cannot set property ' . static::class . '::$' . $name);
	}

	private static function toStyleProperties(?CSSStyleDeclaration $style): CSSStyleProperties
	{
		$properties = new CSSStyleProperties();
		foreach ($style ?? [] as $property => $declaration) {
			$properties->setProperty($property, $declaration['value'], $declaration['priority']);
		}
		return $properties;
	}
}
