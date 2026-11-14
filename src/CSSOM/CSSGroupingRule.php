<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use OutOfBoundsException;

class CSSGroupingRule extends CSSRule
{
	private CSSRuleList $cssRules;

	public function __construct(
		protected readonly string $name,
		protected string $prelude = '',
	) {
		$this->cssRules = new CSSRuleList(null, $this);
	}

	public function getType(): int
	{
		return self::UNKNOWN_RULE;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function getConditionText(): string
	{
		return $this->prelude;
	}

	public function setConditionText(string $conditionText): void
	{
		$conditionText = trim($conditionText);
		if (str_contains($conditionText, '{') || str_contains($conditionText, '}')) {
			throw new \InvalidArgumentException('A CSS condition cannot contain rule braces.');
		}
		$this->prelude = $conditionText;
	}

	public function getCssRules(): CSSRuleList
	{
		return $this->cssRules;
	}

	public function insertRule(string $ruleText, ?int $index = null): int
	{
		$sheet = $this->getParentStyleSheet();
		if ($sheet === null) {
			throw new \LogicException('A detached grouping rule cannot insert rules.');
		}

		$rule = CSSParser::parseSingleRule($ruleText);
		$index ??= count($this->cssRules);
		$this->cssRules->insert($index, $rule);

		return $index;
	}

	public function deleteRule(int $index): void
	{
		if ($index < 0 || $index >= count($this->cssRules)) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}
		$this->cssRules->remove($index);
	}

	public function getCssText(): string
	{
		$prelude = $this->prelude === '' ? '' : ' ' . $this->prelude;
		$rules = array_map(static fn (CSSRule $rule): string => $rule->getCssText(), iterator_to_array($this->cssRules));
		$body = implode("\n", $rules);
		return '@' . $this->name . $prelude . ' { ' . $body . ' }';
	}

	public function initializeRules(CSSStyleSheet $sheet): void
	{
		$this->attach($sheet, $this->getParentRule());
	}

	public function attach(?CSSStyleSheet $sheet, ?CSSGroupingRule $parentRule): void
	{
		parent::attach($sheet, $parentRule);
		$this->cssRules->reparent($sheet);
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'cssRules' => $this->getCssRules(),
			'conditionText' => $this->getConditionText(),
			default => parent::__get($name),
		};
	}
}
