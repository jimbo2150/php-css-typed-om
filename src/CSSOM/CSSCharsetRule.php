<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;

/**
 * Represents a CSS `@charset` rule.
 *
 * @property-read string $encoding
 */
final class CSSCharsetRule extends CSSAtRule
{
	public function __construct(string $prelude, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && strlen($prelude) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS @charset rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		$trimmed = CSSSyntaxScanner::trimCssWhitespace($prelude);
		if ($trimmed === '') {
			throw new InvalidArgumentException('@charset requires an encoding string.');
		}
		CSSSyntaxScanner::assertValidFragment($trimmed, '@charset prelude', [';', '{', '}']);
		if (CSSSyntaxScanner::trimCssWhitespace(CSSSyntaxScanner::removeComments($trimmed)) === '') {
			throw new InvalidArgumentException('@charset requires an encoding string.');
		}
		parent::__construct('charset', $trimmed, null, false, $maxBytes);
	}

	public function getEncoding(): string
	{
		$encoding = $this->prelude;
		if (str_contains($encoding, '/*')) {
			$encoding = CSSSyntaxScanner::removeComments($encoding);
		}
		$encoding = CSSSyntaxScanner::trimCssWhitespace($encoding);
		$len = strlen($encoding);
		if ($len >= 2 && ($encoding[0] === '"' || $encoding[0] === "'")) {
			$valid = false;
			$end = CSSSyntaxScanner::scanQuotedStringEnd($encoding, 0, $len, $valid);
			if ($valid && $end === $len - 1) {
				return substr($encoding, 1, -1);
			}
		}

		return $encoding;
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
