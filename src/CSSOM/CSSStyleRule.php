<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap;
use LogicException;
use OutOfBoundsException;

/**
 * Represents a CSS style rule with a selector, declaration block, and optional nested rules.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSStyleRule
 */
class CSSStyleRule extends CSSGroupingRule
{
	private string $selectorText;
	private CSSStyleProperties $style;

	/**
	 * @throws InvalidArgumentException When $selectorText is empty or invalid
	 */
	public function __construct(string $selectorText, ?CSSStyleDeclaration $style = null)
	{
		$selectorText = trim($selectorText);
		if ($selectorText === '') {
			throw new InvalidArgumentException('A CSS style rule requires a selector.');
		}
		CSSSyntaxScanner::assertValidFragment($selectorText, 'CSS selector', [';', '{', '}']);
		parent::__construct('style', $selectorText);
		$this->selectorText = $selectorText;
		$this->style = CSSStyleProperties::fromDeclaration($style);
		$this->style->setParentRule($this);
	}

	public function getType(): int
	{
		return self::STYLE_RULE;
	}

	public function getSelectorText(): string
	{
		return $this->selectorText;
	}

	/**
	 * @throws InvalidArgumentException When $selectorText is empty or structurally invalid
	 */
	public function setSelectorText(string $selectorText): void
	{
		$selectorText = trim($selectorText);
		if ($selectorText === '') {
			throw new InvalidArgumentException('Invalid CSS selector text.');
		}
		CSSSyntaxScanner::assertValidFragment($selectorText, 'CSS selector', [';', '{', '}']);

		$this->selectorText = $selectorText;
		$this->prelude = $selectorText;
	}

	public function getStyle(): CSSStyleProperties
	{
		return $this->style;
	}

	public function getStyleMap(): StylePropertyMap
	{
		return new StylePropertyMap($this->style);
	}

	/**
	 * Validates and inserts a nested rule into this style rule.
	 *
	 * @throws LogicException When the style rule is detached from a stylesheet
	 * @throws OutOfBoundsException When $index is outside [0, count]
	 * @throws InvalidArgumentException When $ruleText is invalid or exceeds parser limits
	 */
	public function insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int
	{
		if ($this->getParentStyleSheet() === null) {
			throw new LogicException('A detached style rule cannot insert nested rules.');
		}
		if ($index < 0 || $index > count($this->getCssRules())) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}
		$rule = CSSParser::parseSingleNestedRule($ruleText, $options);
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
