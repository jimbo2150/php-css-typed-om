<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS page margin box at-rule (e.g., `@top-left-corner`) inside `@page`.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/API/CSSMarginRule
 */
final class CSSMarginRule extends CSSAtRule
{
	/** @var array<string, true> */
	private const array VALID_MARGIN_BOXES = [
		'top-left-corner' => true,
		'top-left' => true,
		'top-center' => true,
		'top-right' => true,
		'top-right-corner' => true,
		'right-top' => true,
		'right-middle' => true,
		'right-bottom' => true,
		'bottom-right-corner' => true,
		'bottom-right' => true,
		'bottom-center' => true,
		'bottom-left' => true,
		'bottom-left-corner' => true,
		'left-bottom' => true,
		'left-middle' => true,
		'left-top' => true,
	];

	public function __construct(string $name, CSSStyleDeclaration $style)
	{
		$normalized = strtolower(CSSSyntaxScanner::trimCssWhitespace($name));
		if (!isset(self::VALID_MARGIN_BOXES[$normalized])) {
			throw new InvalidArgumentException('Invalid CSS page margin box name: ' . $name);
		}
		parent::__construct($normalized, '', $style, true);
	}

	public function getType(): int
	{
		return self::MARGIN_RULE;
	}
}
