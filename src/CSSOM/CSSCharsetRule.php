<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;

/**
 * Represents a CSS `@charset` rule.
 */
final class CSSCharsetRule extends CSSAtRule
{
	public function __construct(string $prelude)
	{
		$trimmed = trim($prelude);
		if ($trimmed === '') {
			throw new InvalidArgumentException('@charset requires an encoding string.');
		}
		parent::__construct('charset', $trimmed);
	}

	public function getEncoding(): string
	{
		return trim($this->prelude, " \t\n\r\0\x0B\"'");
	}

	public function getType(): int
	{
		return self::CHARSET_RULE;
	}

	public function __get(string $name): mixed
	{
		return $name === 'encoding' ? $this->getEncoding() : parent::__get($name);
	}
}
