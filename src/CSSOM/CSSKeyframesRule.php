<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use LogicException;
use OutOfBoundsException;

/**
 * Represents a CSS `@keyframes` rule.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSKeyframesRule
 */
final class CSSKeyframesRule extends CSSGroupingRule
{
	/**
	 * @throws InvalidArgumentException When the keyframes name is empty or invalid
	 */
	public function __construct(string $name, string $atRuleName = 'keyframes')
	{
		$validated = self::validateName($name);
		parent::__construct($atRuleName, $validated);
	}

	public function getNameText(): string
	{
		return $this->prelude;
	}

	/**
	 * @throws InvalidArgumentException When $name is empty or structurally invalid
	 */
	public function setNameText(string $name): void
	{
		$this->prelude = self::validateName($name);
	}

	public function getType(): int
	{
		return self::KEYFRAMES_RULE;
	}

	public function getLength(): int
	{
		return count($this->getCssRules());
	}

	public function appendRule(string $ruleText, ?CSSParserOptions $options = null): void
	{
		$this->insertRule($ruleText, count($this->getCssRules()), $options);
	}

	public function insertRule(string $ruleText, int $index = 0, ?CSSParserOptions $options = null): int
	{
		if ($this->getParentStyleSheet() === null) {
			throw new LogicException('A detached keyframes rule cannot insert keyframes.');
		}
		if ($index < 0 || $index > count($this->getCssRules())) {
			throw new OutOfBoundsException('Rule index is outside the CSSRuleList.');
		}

		$sheet = $this->getParentStyleSheet();
		$keyframe = CSSParser::parseSingleKeyframeRule(
			$ruleText,
			$options,
			$this->getNestingDepth(),
			$sheet->getTotalRuleCount(),
		);
		$this->getCssRules()->insert($index, $keyframe);

		return $index;
	}

	public function deleteRule(int|string $keyText): void
	{
		if (is_int($keyText)) {
			parent::deleteRule($keyText);
			return;
		}

		$target = CSSSyntaxScanner::trimCssWhitespace($keyText);
		foreach ($this->getCssRules() as $index => $rule) {
			if ($rule instanceof CSSKeyframeRule && strcasecmp($rule->getKeyText(), $target) === 0) {
				$this->getCssRules()->remove($index);
				return;
			}
		}
	}

	public function findRule(string $keyText): ?CSSKeyframeRule
	{
		$target = CSSSyntaxScanner::trimCssWhitespace($keyText);
		foreach ($this->getCssRules() as $rule) {
			if ($rule instanceof CSSKeyframeRule && strcasecmp($rule->getKeyText(), $target) === 0) {
				return $rule;
			}
		}
		return null;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'name' => $this->getNameText(),
			'length' => $this->getLength(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return $name === 'name' || $name === 'length' || parent::__isset($name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'name' && is_string($value)) {
			$this->setNameText($value);
			return;
		}
		parent::__set($name, $value);
	}

	private static function validateName(string $name): string
	{
		$name = CSSSyntaxScanner::trimCssWhitespace($name);
		if ($name === '') {
			throw new InvalidArgumentException('Keyframes name cannot be empty.');
		}
		CSSSyntaxScanner::assertValidFragment($name, 'Keyframes name', [';', '{', '}', ',']);

		return $name;
	}
}
