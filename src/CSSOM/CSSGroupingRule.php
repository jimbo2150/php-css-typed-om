<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use LogicException;
use OutOfBoundsException;

/**
 * Base class for CSS rules that contain a nested CSSRuleList.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSGroupingRule
 */
class CSSGroupingRule extends CSSRule
{
	private CSSRuleList $cssRules;

	/**
	 * @throws InvalidArgumentException When $name or $prelude contains invalid syntax
	 */
	public function __construct(
		protected readonly string $name,
		protected string $prelude = '',
	) {
		$trimmedName = trim($name);
		if (!preg_match('/^-?[a-zA-Z_][a-zA-Z0-9_-]*$/D', $trimmedName)) {
			throw new InvalidArgumentException('Invalid CSS grouping rule name: ' . $name);
		}

		$this->prelude = trim($prelude);
		if ($this->prelude !== '') {
			CSSSyntaxScanner::assertValidFragment($this->prelude, 'CSS rule prelude', [';', '{', '}']);
		}

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

	public function getCssRules(): CSSRuleList
	{
		return $this->cssRules;
	}

	/**
	 * Validates and inserts a rule into this grouping rule's list atomically.
	 *
	 * @throws LogicException When the grouping rule is detached from a stylesheet
	 * @throws OutOfBoundsException When $index is outside [0, count]
	 * @throws InvalidArgumentException When $ruleText is malformed or exceeds parser limits
	 */
	public function insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int
	{
		$sheet = $this->getParentStyleSheet();
		if ($sheet === null) {
			throw new LogicException('A detached grouping rule cannot insert rules.');
		}
		if ($index < 0 || $index > count($this->cssRules)) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}

		$rule = CSSParser::parseSingleRule($ruleText, $options);
		$this->cssRules->insert($index, $rule);

		return $index;
	}

	/**
	 * Deletes the rule at $index.
	 *
	 * @throws OutOfBoundsException When $index is invalid
	 */
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
		$rules = [];
		foreach ($this->cssRules as $rule) {
			$rules[] = $rule->getCssText();
		}

		return '@' . $this->name . $prelude . ' { ' . implode("\n", $rules) . ' }';
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
			default => parent::__get($name),
		};
	}
}
