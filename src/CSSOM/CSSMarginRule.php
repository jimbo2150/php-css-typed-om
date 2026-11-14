<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;

/**
 * Represents a CSS page margin box at-rule (e.g., `@top-left-corner`) inside `@page`.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMarginRule
 */
final class CSSMarginRule extends CSSAtRule
{
	public function __construct(string $name, CSSStyleDeclaration $style)
	{
		$normalized = strtolower(trim($name));
		if (!preg_match('/^(?:top|bottom|left|right)-(?:left|center|right|top|middle|bottom)(?:-corner)?$/D', $normalized)) {
			throw new InvalidArgumentException('Invalid CSS page margin box name: ' . $name);
		}
		parent::__construct($normalized, '', $style, true);
	}

	public function getType(): int
	{
		return self::MARGIN_RULE;
	}
}
