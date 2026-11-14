<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSKeyframesRule extends CSSGroupingRule
{
	public function __construct(string $name, string $atRuleName = 'keyframes')
	{
		parent::__construct($atRuleName, $name);
	}

	public function getNameText(): string
	{
		return $this->prelude;
	}

	public function getType(): int
	{
		return self::KEYFRAMES_RULE;
	}

	public function appendRule(string $ruleText): void
	{
		$this->insertRule($ruleText);
	}

	public function insertRule(string $ruleText, ?int $index = null): int
	{
		if ($this->getParentStyleSheet() === null) {
			throw new \LogicException('A detached keyframes rule cannot insert keyframes.');
		}
		$rule = CSSParser::parseSingleRule($ruleText);
		if (!$rule instanceof CSSStyleRule) {
			throw new \InvalidArgumentException('A keyframe rule requires a selector and declaration block.');
		}
		$keyframe = new CSSKeyframeRule($rule->getSelectorText(), $rule->getStyle());
		$keyframe->setKeyText($rule->getSelectorText());
		$index ??= count($this->getCssRules());
		$this->getCssRules()->insert($index, $keyframe);

		return $index;
	}

	public function deleteRule(int|string $keyText): void
	{
		if (is_int($keyText)) {
			parent::deleteRule($keyText);
			return;
		}
		foreach ($this->getCssRules() as $index => $rule) {
			if ($rule instanceof CSSKeyframeRule && strcasecmp($rule->getKeyText(), trim($keyText)) === 0) {
				$this->getCssRules()->remove($index);
				return;
			}
		}
	}

	public function findRule(string $keyText): ?CSSKeyframeRule
	{
		foreach ($this->getCssRules() as $rule) {
			if ($rule instanceof CSSKeyframeRule && strcasecmp($rule->getKeyText(), trim($keyText)) === 0) {
				return $rule;
			}
		}
		return null;
	}

	public function __get(string $name): mixed
	{
		return $name === 'name' ? $this->getNameText() : parent::__get($name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'name' && is_string($value)) {
			$this->prelude = trim($value);
			return;
		}
		parent::__set($name, $value);
	}
}
