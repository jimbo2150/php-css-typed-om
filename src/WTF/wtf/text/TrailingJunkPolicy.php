<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WTF\wtf\text;

enum TrailingJunkPolicy
{
	case Disallow;
	case Allow;

	public static function default(): self
	{
		return self::Disallow;
	}
}
