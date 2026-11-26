<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Base class for conditional grouping rules such as `@media`, `@supports`, and `@container`.
 *
 * @property string $conditionText
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSConditionRule
 */
class CSSConditionRule extends CSSGroupingRule
{
	protected function initConditionRuleInternal(
		string $ruleName,
		string $conditionText,
		?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES,
		bool $requireNonEmpty = false,
	): void {
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && strlen($conditionText) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS grouping rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($conditionText);
		if ($trimmed !== '') {
			CSSSyntaxScanner::assertValidFragment($trimmed, 'CSS rule prelude', [';', '{', '}']);
		}
		$this->initGroupingRuleInternal($ruleName, $trimmed, $maxBytes);
		if ($requireNonEmpty) {
			$this->assertConditionNotEmpty($this->prelude);
		}
	}

	public function getConditionText(): string
	{
		return $this->prelude;
	}

	protected function requiresNonEmptyCondition(): bool
	{
		return false;
	}

	protected function assertConditionNotEmpty(string $trimmedCondition): void
	{
		if ($trimmedCondition === '' || (str_contains($trimmedCondition, '/*') && CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmedCondition)) === '')) {
			throw new InvalidArgumentException(sprintf('CSS @%s condition cannot be empty.', $this->ruleName));
		}
	}

	/**
	 * @throws InvalidArgumentException When $conditionText contains invalid structural syntax
	 */
	public function setConditionText(string $conditionText): void
	{
		$maxBytes = $this->resolveMaxBytesInternal();
		if ($maxBytes !== null && strlen($conditionText) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS condition text exceeds maximum size of %d bytes.', $maxBytes));
		}
		$conditionText = CSSSyntaxScanner::trimCssWhitespace($conditionText);
		if ($conditionText !== '') {
			CSSSyntaxScanner::assertValidFragment($conditionText, 'CSS condition text', [';', '{', '}']);
		}
		if ($this->requiresNonEmptyCondition()) {
			$this->assertConditionNotEmpty($conditionText);
		}
		$this->prelude = $conditionText;
	}

	public function __get(string $name): mixed
	{
		return match ($name) {
			'conditionText' => $this->getConditionText(),
			default => parent::__get($name),
		};
	}

	public function __isset(string $name): bool
	{
		return $name === 'conditionText' || parent::__isset($name);
	}

	public function __set(string $name, mixed $value): void
	{
		if ($name === 'conditionText' && is_string($value)) {
			$this->setConditionText($value);
			return;
		}
		parent::__set($name, $value);
	}
}
