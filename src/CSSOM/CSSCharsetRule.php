<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@charset` rule.
 */
final class CSSCharsetRule extends CSSAtRule
{
	public function __construct(string $prelude)
	{
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if ($trimmed === '') {
			throw new InvalidArgumentException('@charset requires an encoding string.');
		}
		parent::__construct('charset', $trimmed);
	}

	public function getEncoding(): string
	{
		return trim($this->prelude, CSSSyntaxScanner::CSS_WHITESPACE . "\"'");
	}

	public function getType(): int
	{
		return self::CHARSET_RULE;
	}

	public function __get(string $name): mixed
	{
		return $name === 'encoding' ? $this->getEncoding() : parent::__get($name);
	}

	public function __isset(string $name): bool
	{
		return $name === 'encoding' || parent::__isset($name);
	}
}
