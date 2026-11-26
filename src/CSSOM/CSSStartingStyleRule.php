<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSStartingStyleRule extends CSSGroupingRule
{
	public function __construct(?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		self::assertValidMaxBytesInternal($maxBytes);
		$this->initGroupingRuleInternal('starting-style', '', $maxBytes);
	}

	public function getType(): int
	{
		return self::STARTING_STYLE_RULE;
	}
}
