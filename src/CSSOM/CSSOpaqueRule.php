<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Retains unknown CSS at-rules verbatim while enforcing control-byte and syntax balance invariants.
 */
final class CSSOpaqueRule extends CSSRule
{
	private readonly string $source;

	/**
	 * @throws InvalidArgumentException When $source contains control characters or unbalanced syntax
	 */
	public function __construct(string $source)
	{
		if (CSSSyntaxScanner::hasControlCharacters($source)) {
			throw new InvalidArgumentException('CSS opaque rule contains disallowed control characters.');
		}
		if (!CSSSyntaxScanner::isBalanced($source)) {
			throw new InvalidArgumentException('CSS opaque rule has unbalanced syntax or unclosed comments/strings.');
		}
		$this->source = $source;
	}

	public function getType(): int
	{
		return self::UNKNOWN_RULE;
	}

	public function getCssText(): string
	{
		return $this->source;
	}
}
