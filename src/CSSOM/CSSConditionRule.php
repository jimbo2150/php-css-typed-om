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
	public function getConditionText(): string
	{
		return $this->prelude;
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
