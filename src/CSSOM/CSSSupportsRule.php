<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSSupportsRule extends CSSConditionRule
{
	public function __construct(string $conditionText)
	{
		parent::__construct('supports', $conditionText);
	}

	public function getType(): int
	{
		return self::SUPPORTS_RULE;
	}
}
