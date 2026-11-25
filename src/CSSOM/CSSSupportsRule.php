<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSSupportsRule extends CSSConditionRule
{
	public function __construct(string $conditionText, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		parent::__construct('supports', $conditionText, $maxBytes);
		$this->assertConditionNotEmpty($this->prelude);
	}

	protected function requiresNonEmptyCondition(): bool
	{
		return true;
	}

	public function getType(): int
	{
		return self::SUPPORTS_RULE;
	}
}
