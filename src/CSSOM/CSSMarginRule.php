<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

final class CSSMarginRule extends CSSAtRule
{
	public const int MARGIN_RULE = 23;

	public function __construct(string $name, CSSStyleDeclaration $style)
	{
		parent::__construct($name, '', $style, true);
	}

	public function getType(): int
	{
		return self::MARGIN_RULE;
	}
}
