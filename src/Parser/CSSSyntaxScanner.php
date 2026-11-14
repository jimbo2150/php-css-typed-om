<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use InvalidArgumentException;

/**
 * Linear-time CSS component-value scanner supporting strings, comments, escapes,
 * and balanced blocks without treating nested delimiters as top-level.
 */
final class CSSSyntaxScanner
{
	/** @var array<string, string> */
	private const array CLOSING_DELIMITERS = [
		'(' => ')',
		'[' => ']',
		'{' => '}',
	];

	/** @var array<string, true> */
	private const array CLOSING_SET = [
		')' => true,
		']' => true,
		'}' => true,
	];

	/**
	 * Splits a CSS slice at top-level occurrences of a single-byte delimiter.
	 *
	 * @return list<string>
	 * @throws InvalidArgumentException When $delimiter is not a single byte
	 */
	public static function splitTopLevel(string $source, string $delimiter, int $start = 0, ?int $end = null): array
	{
		if (strlen($delimiter) !== 1) {
			throw new InvalidArgumentException('The delimiter must be a single byte.');
		}

		$parts = [];
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		$segmentStart = max(0, $start);
		$stack = [];
		$quote = null;

		for ($offset = $segmentStart; $offset < $length; $offset++) {
			$character = $source[$offset];

			if ($quote !== null) {
				if ($character === '\\') {
					$offset++;
				} elseif ($character === $quote) {
					$quote = null;
				}
				continue;
			}

			if ($character === '/' && ($offset + 1 < $length) && $source[$offset + 1] === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false || $commentEnd + 1 >= $length) {
					break;
				}
				$offset = $commentEnd + 1;
				continue;
			}

			if ($character === '\\') {
				$offset++;
				continue;
			}

			if ($character === '"' || $character === "'") {
				$quote = $character;
				continue;
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				continue;
			}

			if (isset(self::CLOSING_SET[$character])) {
				if ($stack !== [] && end($stack) === $character) {
					array_pop($stack);
				}
				continue;
			}

			if ($character === $delimiter && $stack === []) {
				$parts[] = substr($source, $segmentStart, $offset - $segmentStart);
				$segmentStart = $offset + 1;
			}
		}

		$parts[] = substr($source, $segmentStart, $length - $segmentStart);

		return $parts;
	}

	/**
	 * Splits a CSS slice on top-level whitespace outside strings, comments, and blocks.
	 *
	 * @return list<string>
	 */
	public static function splitTopLevelWhitespace(string $source, int $start = 0, ?int $end = null): array
	{
		$parts = [];
		$segmentStart = null;
		$stack = [];
		$quote = null;
		$length = $end === null ? strlen($source) : min($end, strlen($source));

		for ($offset = max(0, $start); $offset < $length; $offset++) {
			$character = $source[$offset];
			if ($quote !== null) {
				if ($character === '\\') {
					$offset++;
				} elseif ($character === $quote) {
					$quote = null;
				}
				continue;
			}
			if ($character === '/' && ($offset + 1 < $length) && $source[$offset + 1] === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($stack === [] && $segmentStart !== null) {
					$parts[] = substr($source, $segmentStart, $offset - $segmentStart);
					$segmentStart = null;
				}
				if ($commentEnd === false || $commentEnd + 1 >= $length) {
					break;
				}
				$offset = $commentEnd + 1;
				continue;
			}
			if ($character === '\\') {
				$segmentStart ??= $offset;
				$offset++;
				continue;
			}
			if ($character === '"' || $character === "'") {
				$segmentStart ??= $offset;
				$quote = $character;
				continue;
			}
			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$segmentStart ??= $offset;
				$stack[] = self::CLOSING_DELIMITERS[$character];
				continue;
			}
			if (isset(self::CLOSING_SET[$character])) {
				$segmentStart ??= $offset;
				if ($stack !== [] && end($stack) === $character) {
					array_pop($stack);
				}
				continue;
			}
			if (ctype_space($character) && $stack === []) {
				if ($segmentStart !== null) {
					$parts[] = substr($source, $segmentStart, $offset - $segmentStart);
					$segmentStart = null;
				}
				continue;
			}
			$segmentStart ??= $offset;
		}

		if ($segmentStart !== null) {
			$parts[] = substr($source, $segmentStart, $length - $segmentStart);
		}

		return $parts;
	}

	/**
	 * Finds the first top-level occurrence of a single-byte delimiter.
	 *
	 * @throws InvalidArgumentException When $delimiter is not a single byte
	 */
	public static function findTopLevel(string $source, string $delimiter, int $start = 0, ?int $end = null): ?int
	{
		if (strlen($delimiter) !== 1) {
			throw new InvalidArgumentException('The delimiter must be a single byte.');
		}

		$stack = [];
		$quote = null;
		$length = $end === null ? strlen($source) : min($end, strlen($source));

		for ($offset = max(0, $start); $offset < $length; $offset++) {
			$character = $source[$offset];

			if ($quote !== null) {
				if ($character === '\\') {
					$offset++;
				} elseif ($character === $quote) {
					$quote = null;
				}
				continue;
			}

			if ($character === '/' && ($offset + 1 < $length) && $source[$offset + 1] === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false || $commentEnd + 1 >= $length) {
					return null;
				}
				$offset = $commentEnd + 1;
				continue;
			}

			if ($character === '\\') {
				$offset++;
				continue;
			}

			if ($character === '"' || $character === "'") {
				$quote = $character;
				continue;
			}

			if ($character === $delimiter && $stack === []) {
				return $offset;
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				continue;
			}

			if (isset(self::CLOSING_SET[$character])) {
				if ($stack !== [] && end($stack) === $character) {
					array_pop($stack);
				}
			}
		}

		return null;
	}

	/**
	 * Finds the next top-level occurrence of any delimiter byte in $delimiters.
	 *
	 * @param list<string> $delimiters
	 * @return array{offset: int, delimiter: string}|null
	 */
	public static function findNextTopLevelDelimiter(string $source, array $delimiters, int $start = 0, ?int $end = null): ?array
	{
		$delimiterMap = array_fill_keys($delimiters, true);
		$stack = [];
		$quote = null;
		$length = $end === null ? strlen($source) : min($end, strlen($source));

		for ($offset = max(0, $start); $offset < $length; $offset++) {
			$character = $source[$offset];

			if ($quote !== null) {
				if ($character === '\\') {
					$offset++;
				} elseif ($character === $quote) {
					$quote = null;
				}
				continue;
			}

			if ($character === '/' && ($offset + 1 < $length) && $source[$offset + 1] === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false || $commentEnd + 1 >= $length) {
					return null;
				}
				$offset = $commentEnd + 1;
				continue;
			}

			if ($character === '\\') {
				$offset++;
				continue;
			}

			if ($character === '"' || $character === "'") {
				$quote = $character;
				continue;
			}

			if ($stack === [] && isset($delimiterMap[$character])) {
				return ['offset' => $offset, 'delimiter' => $character];
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				continue;
			}

			if (isset(self::CLOSING_SET[$character]) && $stack !== [] && end($stack) === $character) {
				array_pop($stack);
			}
		}

		return null;
	}

	/**
	 * Finds the matching closing delimiter offset for an opening '(', '[', or '{'.
	 */
	public static function findMatchingDelimiter(string $source, int $openOffset, ?int $end = null): ?int
	{
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		if ($openOffset < 0 || $openOffset >= $length) {
			return null;
		}

		$opening = $source[$openOffset];
		if (!isset(self::CLOSING_DELIMITERS[$opening])) {
			return null;
		}

		$stack = [self::CLOSING_DELIMITERS[$opening]];
		$quote = null;

		for ($offset = $openOffset + 1; $offset < $length; $offset++) {
			$character = $source[$offset];

			if ($quote !== null) {
				if ($character === '\\') {
					$offset++;
				} elseif ($character === $quote) {
					$quote = null;
				}
				continue;
			}

			if ($character === '/' && ($offset + 1 < $length) && $source[$offset + 1] === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false || $commentEnd + 1 >= $length) {
					return null;
				}
				$offset = $commentEnd + 1;
				continue;
			}

			if ($character === '\\') {
				$offset++;
				continue;
			}

			if ($character === '"' || $character === "'") {
				$quote = $character;
				continue;
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				continue;
			}

			if (isset(self::CLOSING_SET[$character])) {
				if (end($stack) !== $character) {
					return null;
				}
				array_pop($stack);
				if ($stack === []) {
					return $offset;
				}
			}
		}

		return null;
	}

	/**
	 * Removes CSS comments outside quoted strings, preserving strings intact.
	 */
	public static function removeComments(string $source, int $start = 0, ?int $end = null): string
	{
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		$offset = max(0, $start);
		$firstComment = strpos($source, '/*', $offset);
		if ($firstComment === false || $firstComment >= $length) {
			return substr($source, $offset, $length - $offset);
		}

		$result = '';
		$quote = null;

		for (; $offset < $length; $offset++) {
			$character = $source[$offset];
			if ($quote !== null) {
				$result .= $character;
				if ($character === '\\' && $offset + 1 < $length) {
					$result .= $source[++$offset];
				} elseif ($character === $quote) {
					$quote = null;
				}
				continue;
			}

			if ($character === '"' || $character === "'") {
				$quote = $character;
				$result .= $character;
				continue;
			}

			if ($character === '\\') {
				$result .= $character;
				if ($offset + 1 < $length) {
					$result .= $source[++$offset];
				}
				continue;
			}

			if ($character === '/' && ($offset + 1 < $length) && $source[$offset + 1] === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false || $commentEnd + 1 >= $length) {
					return $result . ' ';
				}
				$result .= ' ';
				$offset = $commentEnd + 1;
				continue;
			}

			$result .= $character;
		}

		return $result;
	}

	/**
	 * Checks whether all brackets, quotes, comments, and escapes in $source are balanced.
	 */
	public static function isBalanced(string $source, int $start = 0, ?int $end = null): bool
	{
		$stack = [];
		$quote = null;
		$length = $end === null ? strlen($source) : min($end, strlen($source));

		for ($offset = max(0, $start); $offset < $length; $offset++) {
			$character = $source[$offset];
			if ($quote !== null) {
				if ($character === '\\') {
					if (++$offset >= $length) {
						return false;
					}
				} elseif ($character === "\n" || $character === "\r" || $character === "\f") {
					return false;
				} elseif ($character === $quote) {
					$quote = null;
				}
				continue;
			}
			if ($character === '/' && ($offset + 1 < $length) && $source[$offset + 1] === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false || $commentEnd + 1 >= $length) {
					return false;
				}
				$offset = $commentEnd + 1;
				continue;
			}
			if ($character === '\\') {
				if (++$offset >= $length) {
					return false;
				}
				continue;
			}
			if ($character === '"' || $character === "'") {
				$quote = $character;
				continue;
			}
			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				continue;
			}
			if (isset(self::CLOSING_SET[$character])) {
				if ($stack === [] || end($stack) !== $character) {
					return false;
				}
				array_pop($stack);
			}
		}

		return $quote === null && $stack === [];
	}

	/**
	 * Returns true if $source contains an unclosed CSS comment or quoted string.
	 */
	public static function hasUnclosedCommentOrString(string $source, int $start = 0, ?int $end = null): bool
	{
		$quote = null;
		$length = $end === null ? strlen($source) : min($end, strlen($source));

		for ($offset = max(0, $start); $offset < $length; $offset++) {
			$character = $source[$offset];
			if ($quote !== null) {
				if ($character === '\\') {
					if (++$offset >= $length) {
						return true;
					}
				} elseif ($character === $quote) {
					$quote = null;
				}
				continue;
			}
			if ($character === '/' && ($offset + 1 < $length) && $source[$offset + 1] === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false || $commentEnd + 1 >= $length) {
					return true;
				}
				$offset = $commentEnd + 1;
				continue;
			}
			if ($character === '\\') {
				if (++$offset >= $length) {
					return true;
				}
				continue;
			}
			if ($character === '"' || $character === "'") {
				$quote = $character;
			}
		}

		return $quote !== null;
	}

	/**
	 * Returns true if $source contains disallowed ASCII control characters (including NUL).
	 */
	public static function hasControlCharacters(string $source): bool
	{
		return preg_match('/[\x00-\x08\x0B\x0E-\x1F\x7F]/', $source) === 1;
	}

	/**
	 * Validates that a CSS fragment (selector, prelude, condition, or declaration value)
	 * is balanced, contains no control bytes, and contains none of the forbidden delimiters
	 * at top level (and optionally anywhere outside strings/comments).
	 *
	 * @param list<string> $forbiddenTopLevel
	 * @param list<string> $forbiddenAnywhere
	 */
	public static function assertValidFragment(
		string $source,
		string $label,
		array $forbiddenTopLevel = [';', '{', '}'],
		array $forbiddenAnywhere = [],
	): void {
		if (self::hasControlCharacters($source)) {
			throw new InvalidArgumentException($label . ' contains disallowed control characters.');
		}
		if (!self::isBalanced($source)) {
			throw new InvalidArgumentException($label . ' has unbalanced syntax or unclosed comments/strings.');
		}

		$topMap = $forbiddenTopLevel === [] ? [] : array_fill_keys($forbiddenTopLevel, true);
		$anyMap = $forbiddenAnywhere === [] ? [] : array_fill_keys($forbiddenAnywhere, true);
		if ($topMap === [] && $anyMap === []) {
			return;
		}

		$stack = [];
		$quote = null;
		$length = strlen($source);
		for ($offset = 0; $offset < $length; $offset++) {
			$character = $source[$offset];
			if ($quote !== null) {
				if ($character === '\\') {
					$offset++;
				} elseif ($character === $quote) {
					$quote = null;
				}
				continue;
			}
			if ($character === '/' && ($offset + 1 < $length) && $source[$offset + 1] === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				$offset = $commentEnd === false ? $length : $commentEnd + 1;
				continue;
			}
			if ($character === '\\') {
				$offset++;
				continue;
			}
			if ($character === '"' || $character === "'") {
				$quote = $character;
				continue;
			}
			if (isset($anyMap[$character]) || ($stack === [] && isset($topMap[$character]))) {
				throw new InvalidArgumentException(sprintf('%s cannot contain "%s".', $label, $character));
			}
			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				continue;
			}
			if (isset(self::CLOSING_SET[$character]) && $stack !== [] && end($stack) === $character) {
				array_pop($stack);
			}
		}
	}
}
