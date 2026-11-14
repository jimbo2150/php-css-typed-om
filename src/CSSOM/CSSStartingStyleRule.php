<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSStartingStyleRule extends CSSGroupingRule
{
	public function __construct()
	{
		parent::__construct('starting-style');
	}

	public function getType(): int
	{
		return self::STARTING_STYLE_RULE;
	}
}
