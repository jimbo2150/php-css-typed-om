<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use ReflectionClass;

/**
 * Retains unknown CSS at-rules verbatim while enforcing control-byte and syntax balance invariants.
 */
final class CSSOpaqueRule extends CSSRule
{
	private string $source;

	/**
	 * @throws InvalidArgumentException When $source contains control characters or unbalanced syntax
	 */
	public function __construct(string $source, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES)
	{
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && strlen($source) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS opaque rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		$this->maxBytes = $maxBytes;
		if (CSSSyntaxScanner::hasControlCharacters($source)) {
			throw new InvalidArgumentException('CSS opaque rule contains disallowed control characters.');
		}
		if (!CSSSyntaxScanner::isBalanced($source)) {
			throw new InvalidArgumentException('CSS opaque rule has unbalanced syntax or unclosed comments/strings.');
		}
		self::assertSingleAtRule($source);
		$this->source = $source;
	}

	/** @internal Fast path for already-delimited opaque rules produced by CSSParser. */
	public static function createParsedInternal(string $source, ?int $maxBytes = CSSParserOptions::DEFAULT_MAX_BYTES): self
	{
		self::assertValidMaxBytesInternal($maxBytes);
		if ($maxBytes !== null && strlen($source) > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS opaque rule exceeds maximum size of %d bytes.', $maxBytes));
		}
		if (CSSSyntaxScanner::hasControlCharacters($source)) {
			throw new InvalidArgumentException('CSS opaque rule contains disallowed control characters.');
		}
		if (!CSSSyntaxScanner::isBalanced($source)) {
			throw new InvalidArgumentException('CSS opaque rule has unbalanced syntax or unclosed comments/strings.');
		}
		$length = strlen($source);
		$identEnd = ($length > 1 && $source[0] === '@') ? CSSSyntaxScanner::scanIdentEnd($source, 1, $length) : null;
		if ($identEnd === null) {
			throw new InvalidArgumentException('CSS opaque rule must begin with a valid @at-rule identifier.');
		}
		$rawName = substr($source, 1, $identEnd - 1);
		if (str_contains($rawName, '\\') && CSSSyntaxScanner::hasControlCharacters(CSSSyntaxScanner::decodeCssIdentEscapes($rawName))) {
			throw new InvalidArgumentException('CSS opaque rule contains disallowed control characters.');
		}

		static $reflection = null;
		$reflection ??= new ReflectionClass(self::class);
		/** @var self $instance */
		$instance = $reflection->newInstanceWithoutConstructor();
		$instance->source = $source;
		$instance->maxBytes = $maxBytes;

		return $instance;
	}

	private static function assertSingleAtRule(string $source): void
	{
		$length = strlen($source);
		$pos = self::skipWhitespaceAndComments($source, 0, $length);
		if ($pos + 1 >= $length || $source[$pos] !== '@') {
			throw new InvalidArgumentException('CSS opaque rule must be a single @at-rule.');
		}
		$identEnd = CSSSyntaxScanner::scanIdentEnd($source, $pos + 1, $length);
		if ($identEnd === null) {
			throw new InvalidArgumentException('CSS opaque rule must begin with a valid @at-rule identifier.');
		}
		$rawName = substr($source, $pos + 1, $identEnd - ($pos + 1));
		if (str_contains($rawName, '\\')) {
			$decoded = CSSSyntaxScanner::decodeCssIdentEscapes($rawName);
			if (CSSSyntaxScanner::hasControlCharacters($decoded)) {
				throw new InvalidArgumentException('CSS opaque rule contains disallowed control characters.');
			}
		}

		$delim = CSSSyntaxScanner::findNextTopLevelDelimiterBytes($source, ';{}', $identEnd, $length);
		if ($delim === null || $delim['delimiter'] === '}') {
			throw new InvalidArgumentException('CSS opaque rule must terminate with ";" or a balanced "{...}" block.');
		}
		if ($delim['delimiter'] === ';') {
			$afterEnd = $delim['offset'] + 1;
		} else {
			$close = CSSSyntaxScanner::findMatchingBlockEnd($source, $delim['offset'], $length);
			if ($close === null) {
				throw new InvalidArgumentException('CSS opaque rule has an unclosed block.');
			}
			$afterEnd = $close + 1;
		}

		$trailing = self::skipWhitespaceAndComments($source, $afterEnd, $length);
		if ($trailing < $length) {
			throw new InvalidArgumentException('CSS opaque rule must contain exactly one rule.');
		}
	}

	private static function skipWhitespaceAndComments(string $source, int $pos, int $length): int
	{
		while ($pos < $length) {
			$pos += strspn($source, CSSSyntaxScanner::CSS_WHITESPACE, $pos, $length - $pos);
			if ($pos + 1 < $length && $source[$pos] === '/' && $source[$pos + 1] === '*') {
				$commentEnd = strpos($source, '*/', $pos + 2);
				if ($commentEnd === false) {
					throw new InvalidArgumentException('CSS opaque rule has unbalanced syntax or unclosed comments/strings.');
				}
				$pos = $commentEnd + 2;
				continue;
			}
			break;
		}

		return $pos;
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
