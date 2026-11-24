<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use InvalidArgumentException;

/**
 * Linear-time CSS component-value scanner supporting strings, unquoted `url(...)` tokens,
 * comments, CSS escapes, and balanced blocks without treating nested delimiters as top-level.
 */
final class CSSSyntaxScanner
{
	public const string CSS_WHITESPACE = " \t\n\r\f";

	public const string IDENT_ESCAPE_REGEX = '\\\\(?>(?:[0-9a-fA-F]{1,6}(?:\r\n|[ \t\n\r\f])?)|[^0-9a-fA-F\x00-\x1F\x7F])';

	private const string CONTROL_BYTES = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";

	private const string ASCII_WHITESPACE = self::CSS_WHITESPACE;

	private const int ESCAPED_URL_WINDOW = 40;

	private const string HEX_DIGITS = '0123456789abcdefABCDEF';

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
		$parts = [];
		self::scanTopLevel($source, $delimiter, $start, $end, $parts);

		return $parts;
	}

	/**
	 * Counts top-level occurrences of a single-byte delimiter without allocating substrings.
	 *
	 * @throws InvalidArgumentException When $delimiter is not a single byte
	 */
	public static function countTopLevelDelimiters(string $source, string $delimiter, int $start = 0, ?int $end = null): int
	{
		$parts = null;

		return self::scanTopLevel($source, $delimiter, $start, $end, $parts);
	}

	/**
	 * @param ?list<string> $parts
	 */
	private static function scanTopLevel(string $source, string $delimiter, int $start, ?int $end, ?array &$parts): int
	{
		if (strlen($delimiter) !== 1) {
			throw new InvalidArgumentException('The delimiter must be a single byte.');
		}

		$count = 0;
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		$offset = max(0, $start);
		$segmentStart = $offset;
		$stack = [];
		$lastEscapeOffset = -1;
		$mask = "\\/\"'()[]{}" . $delimiter;

		while ($offset < $length) {
			$offset += strcspn($source, $mask, $offset, $length - $offset);
			if ($offset >= $length) {
				break;
			}

			$character = $source[$offset];

			if ($character === '"' || $character === "'") {
				$valid = true;
				$strEnd = self::scanQuotedString($source, $offset, $length, $valid);
				$offset = $valid ? $strEnd + 1 : $strEnd;
				continue;
			}

			if ($character === '/') {
				if ($offset + 1 < $length && $source[$offset + 1] === '*') {
					$commentEnd = strpos($source, '*/', $offset + 2);
					if ($commentEnd === false || $commentEnd + 1 >= $length) {
						break;
					}
					$offset = $commentEnd + 2;
					continue;
				}
				if ($character === $delimiter && $stack === []) {
					$count++;
					if ($parts !== null) {
						$parts[] = substr($source, $segmentStart, $offset - $segmentStart);
					}
					$segmentStart = $offset + 1;
				}
				$offset++;
				continue;
			}

			if ($character === '\\') {
				$lastEscapeOffset = $offset;
				$offset += 2;
				continue;
			}

			if ($character === $delimiter && $stack === []) {
				$count++;
				if ($parts !== null) {
					$parts[] = substr($source, $segmentStart, $offset - $segmentStart);
				}
				$segmentStart = $offset + 1;
				$offset++;
				continue;
			}

			if ($character === '(' && self::isUnquotedUrlStart($source, $offset, $segmentStart, $length, $lastEscapeOffset)) {
				$valid = true;
				$urlEnd = self::scanUnquotedUrl($source, $offset, $length, $valid);
				$offset = $urlEnd < $length ? $urlEnd + 1 : $length;
				continue;
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				$offset++;
				continue;
			}

			if (isset(self::CLOSING_SET[$character]) && $stack !== [] && end($stack) === $character) {
				array_pop($stack);
			}
			$offset++;
		}

		if ($parts !== null) {
			$parts[] = substr($source, $segmentStart, $length - $segmentStart);
		}

		return $count;
	}

	/**
	 * Splits a CSS slice on top-level whitespace outside strings, comments, unquoted URLs, and blocks.
	 *
	 * @return list<string>
	 */
	public static function splitTopLevelWhitespace(string $source, int $start = 0, ?int $end = null): array
	{
		$parts = [];
		$segmentStart = null;
		$stack = [];
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		$baseStart = max(0, $start);
		$offset = $baseStart;
		$lastEscapeOffset = -1;
		$mask = "\\/\"'()[]{} \t\n\r\f";

		while ($offset < $length) {
			$span = strcspn($source, $mask, $offset, $length - $offset);
			if ($span > 0) {
				$segmentStart ??= $offset;
				$offset += $span;
				if ($offset >= $length) {
					break;
				}
			}

			$character = $source[$offset];

			if ($character === '"' || $character === "'") {
				$segmentStart ??= $offset;
				$valid = true;
				$strEnd = self::scanQuotedString($source, $offset, $length, $valid);
				$offset = $valid ? $strEnd + 1 : $strEnd;
				continue;
			}

			if ($character === '/') {
				if ($offset + 1 < $length && $source[$offset + 1] === '*') {
					$commentEnd = strpos($source, '*/', $offset + 2);
					if ($stack === [] && $segmentStart !== null) {
						$parts[] = substr($source, $segmentStart, $offset - $segmentStart);
						$segmentStart = null;
					}
					if ($commentEnd === false || $commentEnd + 1 >= $length) {
						break;
					}
					$offset = $commentEnd + 2;
					continue;
				}
				$segmentStart ??= $offset;
				$offset++;
				continue;
			}

			if ($character === '\\') {
				$segmentStart ??= $offset;
				$lastEscapeOffset = $offset;
				$offset++;
				if ($offset < $length) {
					$hexLen = strspn($source, self::HEX_DIGITS, $offset, min(6, $length - $offset));
					if ($hexLen > 0) {
						$offset += $hexLen;
						if ($offset < $length) {
							$ws = $source[$offset];
							if ($ws === "\r" && $offset + 1 < $length && $source[$offset + 1] === "\n") {
								$offset += 2;
							} elseif ($ws === ' ' || $ws === "\t" || $ws === "\n" || $ws === "\r" || $ws === "\f") {
								$offset++;
							}
						}
					} elseif ($source[$offset] !== "\n" && $source[$offset] !== "\r" && $source[$offset] !== "\f") {
						$offset++;
					}
				}
				continue;
			}

			if ($character === ' ' || $character === "\t" || $character === "\n" || $character === "\r" || $character === "\f") {
				if ($stack === []) {
					if ($segmentStart !== null) {
						$parts[] = substr($source, $segmentStart, $offset - $segmentStart);
						$segmentStart = null;
					}
					$offset += strspn($source, self::CSS_WHITESPACE, $offset, $length - $offset);
				} else {
					$offset++;
				}
				continue;
			}

			$segmentStart ??= $offset;

			if ($character === '(' && self::isUnquotedUrlStart($source, $offset, $baseStart, $length, $lastEscapeOffset)) {
				$valid = true;
				$urlEnd = self::scanUnquotedUrl($source, $offset, $length, $valid);
				$offset = $urlEnd < $length ? $urlEnd + 1 : $length;
				continue;
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				$offset++;
				continue;
			}

			if (isset(self::CLOSING_SET[$character]) && $stack !== [] && end($stack) === $character) {
				array_pop($stack);
			}
			$offset++;
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
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		$baseStart = max(0, $start);
		$offset = $baseStart;
		$lastEscapeOffset = -1;
		$mask = "\\/\"'()[]{}" . $delimiter;

		while ($offset < $length) {
			$offset += strcspn($source, $mask, $offset, $length - $offset);
			if ($offset >= $length) {
				break;
			}

			$character = $source[$offset];

			if ($character === '"' || $character === "'") {
				$valid = true;
				$strEnd = self::scanQuotedString($source, $offset, $length, $valid);
				$offset = $valid ? $strEnd + 1 : $strEnd;
				continue;
			}

			if ($character === '/') {
				if ($offset + 1 < $length && $source[$offset + 1] === '*') {
					$commentEnd = strpos($source, '*/', $offset + 2);
					if ($commentEnd === false || $commentEnd + 1 >= $length) {
						return null;
					}
					$offset = $commentEnd + 2;
					continue;
				}
				if ($character === $delimiter && $stack === []) {
					return $offset;
				}
				$offset++;
				continue;
			}

			if ($character === '\\') {
				$lastEscapeOffset = $offset;
				$offset += 2;
				continue;
			}

			if ($character === $delimiter && $stack === []) {
				return $offset;
			}

			if ($character === '(' && self::isUnquotedUrlStart($source, $offset, $baseStart, $length, $lastEscapeOffset)) {
				$valid = true;
				$urlEnd = self::scanUnquotedUrl($source, $offset, $length, $valid);
				$offset = $urlEnd < $length ? $urlEnd + 1 : $length;
				continue;
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				$offset++;
				continue;
			}

			if (isset(self::CLOSING_SET[$character]) && $stack !== [] && end($stack) === $character) {
				array_pop($stack);
			}
			$offset++;
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
		if ($delimiters === []) {
			return null;
		}

		return self::findNextTopLevelDelimiterBytes($source, implode('', $delimiters), $start, $end);
	}

	/**
	 * Hot-path byte-string variant of findNextTopLevelDelimiter() avoiding array/implode overhead.
	 *
	 * @return array{offset: int, delimiter: string}|null
	 */
	public static function findNextTopLevelDelimiterBytes(
		string $source,
		string $delimiterBytes,
		int $start = 0,
		?int $end = null,
	): ?array {
		if ($delimiterBytes === '') {
			return null;
		}

		$stack = [];
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		$baseStart = max(0, $start);
		$offset = $baseStart;
		$lastEscapeOffset = -1;
		$mask = "\\/\"'()[]{}" . $delimiterBytes;

		while ($offset < $length) {
			$offset += strcspn($source, $mask, $offset, $length - $offset);
			if ($offset >= $length) {
				break;
			}

			$character = $source[$offset];

			if ($character === '"' || $character === "'") {
				$valid = true;
				$strEnd = self::scanQuotedString($source, $offset, $length, $valid);
				$offset = $valid ? $strEnd + 1 : $strEnd;
				continue;
			}

			if ($character === '/') {
				if ($offset + 1 < $length && $source[$offset + 1] === '*') {
					$commentEnd = strpos($source, '*/', $offset + 2);
					if ($commentEnd === false || $commentEnd + 1 >= $length) {
						return null;
					}
					$offset = $commentEnd + 2;
					continue;
				}
				if ($stack === [] && str_contains($delimiterBytes, $character)) {
					return ['offset' => $offset, 'delimiter' => $character];
				}
				$offset++;
				continue;
			}

			if ($character === '\\') {
				$lastEscapeOffset = $offset;
				$offset += 2;
				continue;
			}

			if ($stack === [] && str_contains($delimiterBytes, $character)) {
				return ['offset' => $offset, 'delimiter' => $character];
			}

			if ($character === '(' && self::isUnquotedUrlStart($source, $offset, $baseStart, $length, $lastEscapeOffset)) {
				$valid = true;
				$urlEnd = self::scanUnquotedUrl($source, $offset, $length, $valid);
				$offset = $urlEnd < $length ? $urlEnd + 1 : $length;
				continue;
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				$offset++;
				continue;
			}

			if (isset(self::CLOSING_SET[$character]) && $stack !== [] && end($stack) === $character) {
				array_pop($stack);
			}
			$offset++;
		}

		return null;
	}

	/**
	 * Scans an unquoted `url(...)` argument starting at `'('` and returns the closing `')'` offset if valid.
	 */
	public static function findMatchingUrlEnd(string $source, int $openParenOffset, ?int $end = null): ?int
	{
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		if ($openParenOffset < 0 || $openParenOffset >= $length || $source[$openParenOffset] !== '(') {
			return null;
		}
		$afterParen = $openParenOffset + 1;
		$afterParen += strspn($source, self::ASCII_WHITESPACE, $afterParen, $length - $afterParen);
		if ($afterParen < $length && ($source[$afterParen] === '"' || $source[$afterParen] === "'")) {
			$strValid = false;
			$strEnd = self::scanQuotedString($source, $afterParen, $length, $strValid);
			if (!$strValid) {
				return null;
			}
			$pos = $strEnd + 1;
			while ($pos < $length) {
				$pos += strspn($source, self::CSS_WHITESPACE, $pos, $length - $pos);
				if ($pos + 1 < $length && $source[$pos] === '/' && $source[$pos + 1] === '*') {
					$commentEnd = strpos($source, '*/', $pos + 2);
					if ($commentEnd === false || $commentEnd + 1 >= $length) {
						return null;
					}
					$pos = $commentEnd + 2;
					continue;
				}
				break;
			}

			return ($pos < $length && $source[$pos] === ')') ? $pos : null;
		}
		$valid = false;
		$urlEnd = self::scanUnquotedUrl($source, $openParenOffset, $length, $valid);

		return $valid && $urlEnd < $length ? $urlEnd : null;
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
		$offset = $openOffset + 1;
		$lastEscapeOffset = -1;
		$mask = "\\/\"'()[]{}";

		while ($offset < $length) {
			$offset += strcspn($source, $mask, $offset, $length - $offset);
			if ($offset >= $length) {
				break;
			}

			$character = $source[$offset];

			if ($character === '"' || $character === "'") {
				$valid = true;
				$strEnd = self::scanQuotedString($source, $offset, $length, $valid);
				$offset = $valid ? $strEnd + 1 : $strEnd;
				continue;
			}

			if ($character === '/') {
				if ($offset + 1 < $length && $source[$offset + 1] === '*') {
					$commentEnd = strpos($source, '*/', $offset + 2);
					if ($commentEnd === false || $commentEnd + 1 >= $length) {
						return null;
					}
					$offset = $commentEnd + 2;
					continue;
				}
				$offset++;
				continue;
			}

			if ($character === '\\') {
				$lastEscapeOffset = $offset;
				$offset += 2;
				continue;
			}

			if ($character === '(' && self::isUnquotedUrlStart($source, $offset, $openOffset + 1, $length, $lastEscapeOffset)) {
				$valid = true;
				$urlEnd = self::scanUnquotedUrl($source, $offset, $length, $valid);
				$offset = $urlEnd < $length ? $urlEnd + 1 : $length;
				continue;
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				$offset++;
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
			$offset++;
		}

		return null;
	}

	/**
	 * Finds the closing '}' for an opening '{' while ignoring stray unmatched ')' or ']' per CSS Syntax L3 §5.4.7.
	 */
	public static function findMatchingBlockEnd(string $source, int $openBraceOffset, ?int $end = null): ?int
	{
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		if ($openBraceOffset < 0 || $openBraceOffset >= $length || $source[$openBraceOffset] !== '{') {
			return null;
		}

		$stack = ['}'];
		$offset = $openBraceOffset + 1;
		$lastEscapeOffset = -1;
		$mask = "\\/\"'()[]{}";

		while ($offset < $length) {
			$offset += strcspn($source, $mask, $offset, $length - $offset);
			if ($offset >= $length) {
				break;
			}

			$character = $source[$offset];

			if ($character === '"' || $character === "'") {
				$valid = true;
				$strEnd = self::scanQuotedString($source, $offset, $length, $valid);
				$offset = $valid ? $strEnd + 1 : $strEnd;
				continue;
			}

			if ($character === '/') {
				if ($offset + 1 < $length && $source[$offset + 1] === '*') {
					$commentEnd = strpos($source, '*/', $offset + 2);
					if ($commentEnd === false || $commentEnd + 1 >= $length) {
						return null;
					}
					$offset = $commentEnd + 2;
					continue;
				}
				$offset++;
				continue;
			}

			if ($character === '\\') {
				$lastEscapeOffset = $offset;
				$offset += 2;
				continue;
			}

			if ($character === '(' && self::isUnquotedUrlStart($source, $offset, $openBraceOffset + 1, $length, $lastEscapeOffset)) {
				$valid = true;
				$urlEnd = self::scanUnquotedUrl($source, $offset, $length, $valid);
				$offset = $urlEnd < $length ? $urlEnd + 1 : $length;
				continue;
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				$offset++;
				continue;
			}

			if (isset(self::CLOSING_SET[$character])) {
				$top = end($stack);
				if ($top === $character) {
					array_pop($stack);
					if ($stack === []) {
						return $offset;
					}
				} elseif ($character === '}' && count($stack) > 1) {
					// If '}' closes an enclosing '{' past unclosed inner '(' or '[', unwind to that '{' or outer block.
					do {
						array_pop($stack);
					} while ($stack !== [] && end($stack) !== '}');
					if ($stack !== [] && end($stack) === '}') {
						array_pop($stack);
						if ($stack === []) {
							return $offset;
						}
					}
				}
			}
			$offset++;
		}

		return null;
	}

	/**
	 * Removes CSS comments outside quoted strings and unquoted `url(...)` tokens.
	 * Emits a separating space only when needed to avoid merging adjacent tokens.
	 */
	public static function removeComments(string $source, int $start = 0, ?int $end = null): string
	{
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		$baseStart = max(0, $start);
		$sliceLen = max(0, $length - $baseStart);
		if ($sliceLen === 0) {
			return '';
		}
		if ($baseStart === 0 && $length === strlen($source)) {
			if (!str_contains($source, '/*')) {
				return $source;
			}
		} else {
			$probe = $baseStart;
			$hasComment = false;
			while ($probe + 1 < $length) {
				$probe += strcspn($source, '/', $probe, $length - $probe);
				if ($probe + 1 >= $length) {
					break;
				}
				if ($source[$probe + 1] === '*') {
					$hasComment = true;
					break;
				}
				$probe++;
			}
			if (!$hasComment) {
				return substr($source, $baseStart, $sliceLen);
			}
		}

		$chunks = [];
		$chunkStart = $baseStart;
		$offset = $baseStart;
		$lastEscapeOffset = -1;
		$lastEscapeEnd = -1;
		$lastEscapeWasBareHex = false;
		$mask = "\\\"'/(";

		while ($offset < $length) {
			$offset += strcspn($source, $mask, $offset, $length - $offset);
			if ($offset >= $length) {
				break;
			}

			$character = $source[$offset];

			if ($character === '"' || $character === "'") {
				$valid = true;
				$strEnd = self::scanQuotedString($source, $offset, $length, $valid);
				$offset = $valid ? $strEnd + 1 : $strEnd;
				continue;
			}

			if ($character === '\\') {
				$lastEscapeOffset = $offset;
				if ($offset + 1 >= $length) {
					$lastEscapeEnd = $length;
					$lastEscapeWasBareHex = false;
					$offset = $length;
				} else {
					$hexLen = strspn($source, self::HEX_DIGITS, $offset + 1, min(6, $length - ($offset + 1)));
					if ($hexLen > 0) {
						$endEsc = $offset + 1 + $hexLen;
						$consumedWs = false;
						if ($endEsc < $length) {
							$ws = $source[$endEsc];
							if ($ws === "\r" && $endEsc + 1 < $length && $source[$endEsc + 1] === "\n") {
								$endEsc += 2;
								$consumedWs = true;
							} elseif ($ws === ' ' || $ws === "\t" || $ws === "\n" || $ws === "\r" || $ws === "\f") {
								$endEsc++;
								$consumedWs = true;
							}
						}
						$lastEscapeEnd = $endEsc;
						$lastEscapeWasBareHex = !$consumedWs;
						$offset = $endEsc;
					} else {
						$escapedByte = $source[$offset + 1];
						if ($escapedByte === "\n" || $escapedByte === "\r" || $escapedByte === "\f") {
							$lastEscapeWasBareHex = false;
							$offset++;
						} else {
							$lastEscapeEnd = $offset + 2;
							$lastEscapeWasBareHex = false;
							$offset = $lastEscapeEnd;
						}
					}
				}
				continue;
			}

			if ($character === '(') {
				if (self::isUnquotedUrlStart($source, $offset, $baseStart, $length, $lastEscapeOffset)) {
					$valid = true;
					$urlEnd = self::scanUnquotedUrl($source, $offset, $length, $valid);
					$offset = $urlEnd < $length ? $urlEnd + 1 : $length;
				} else {
					$offset++;
				}
				continue;
			}

			// $character === '/'
			if ($offset + 1 < $length && $source[$offset + 1] === '*') {
				if ($offset > $chunkStart) {
					$chunks[] = substr($source, $chunkStart, $offset - $chunkStart);
				}

				$prevOffset = $offset - 1;
				$prevByte = $prevOffset >= $baseStart ? $source[$prevOffset] : null;
				$prevWasEscape = $prevByte !== null && $lastEscapeEnd === $offset;

				// Consume an entire contiguous run of /*...*/ comments before deciding separator emission.
				$runEnd = $offset;
				while ($runEnd + 1 < $length && $source[$runEnd] === '/' && $source[$runEnd + 1] === '*') {
					$commentEnd = strpos($source, '*/', $runEnd + 2);
					if ($commentEnd === false || $commentEnd + 1 >= $length) {
						return implode('', $chunks);
					}
					$runEnd = $commentEnd + 2;
				}

				$nextByte = $runEnd < $length ? $source[$runEnd] : null;
				if ($prevWasEscape && $lastEscapeWasBareHex && $nextByte !== null) {
					// CSS Syntax L3 consumes one trailing whitespace after a bare hex escape, so emit
					// one space before whitespace (or two spaces before a merging token) to keep tokens separate.
					if ($nextByte === ' ' || $nextByte === "\t" || $nextByte === "\n" || $nextByte === "\r" || $nextByte === "\f") {
						$chunks[] = ' ';
					} else {
						$nextNextByte = $runEnd + 1 < $length ? $source[$runEnd + 1] : null;
						if (self::needsCommentSeparatorSpace($prevByte, true, $nextByte, $nextNextByte)) {
							$chunks[] = '  ';
						}
					}
				} elseif ($prevByte !== null
					&& $nextByte !== null
					&& $nextByte !== ' ' && $nextByte !== "\t" && $nextByte !== "\n" && $nextByte !== "\r" && $nextByte !== "\f"
					&& ($prevWasEscape || ($prevByte !== ' ' && $prevByte !== "\t" && $prevByte !== "\n" && $prevByte !== "\r" && $prevByte !== "\f"))
				) {
					$nextNextByte = $runEnd + 1 < $length ? $source[$runEnd + 1] : null;
					if (self::needsCommentSeparatorSpace($prevByte, $prevWasEscape, $nextByte, $nextNextByte)) {
						$chunks[] = ' ';
					}
				}

				$offset = $runEnd;
				$chunkStart = $runEnd;
				continue;
			}

			$offset++;
		}

		if ($chunkStart < $length) {
			$chunks[] = substr($source, $chunkStart, $length - $chunkStart);
		}

		return implode('', $chunks);
	}

	private static function needsCommentSeparatorSpace(
		?string $prevByte,
		bool $prevWasEscape,
		?string $nextByte,
		?string $nextNextByte,
	): bool {
		if ($prevByte === null || $nextByte === null) {
			return false;
		}
		if ((!$prevWasEscape && str_contains(self::CSS_WHITESPACE, $prevByte)) || str_contains(self::CSS_WHITESPACE, $nextByte)) {
			return false;
		}

		$prevIsDigit = $prevByte >= '0' && $prevByte <= '9';
		$nextIsDigit = $nextByte >= '0' && $nextByte <= '9';
		$nextNextIsDigit = $nextNextByte !== null && $nextNextByte >= '0' && $nextNextByte <= '9';
		$prevIsIdent = $prevWasEscape || self::isIdentByte($prevByte);
		$nextIsIdentStart = self::isIdentByte($nextByte) || $nextByte === '\\';

		if ($prevIsIdent && ($nextIsIdentStart || $nextByte === '(' || $nextByte === '%')) {
			return true;
		}
		if ($prevIsDigit && $nextByte === '.' && $nextNextIsDigit) {
			return true;
		}
		if ($prevByte === '.' && $nextIsDigit) {
			return true;
		}
		if (($prevByte === '+' || $prevByte === '-') && ($nextIsIdentStart || ($nextByte === '.' && $nextNextIsDigit))) {
			return true;
		}
		if (($prevByte === '#' || $prevByte === '@') && $nextIsIdentStart) {
			return true;
		}
		if ($prevByte === '/' && $nextByte === '*') {
			return true;
		}
		if (($prevByte === '<' && $nextByte === '!') || ($prevByte === '-' && $nextByte === '>')) {
			return true;
		}
		if ((($prevByte === '~' || $prevByte === '|' || $prevByte === '^' || $prevByte === '$' || $prevByte === '*') && $nextByte === '=')
			|| ($prevByte === '|' && $nextByte === '|')
		) {
			return true;
		}
		if (($prevByte === 'e' || $prevByte === 'E') && $nextByte === '+' && $nextNextIsDigit) {
			return true;
		}
		if ($prevByte === '!' && $nextByte === '-' && $nextNextByte === '-') {
			return true;
		}
		if ($prevByte === ')' && $nextIsIdentStart) {
			return true;
		}

		return false;
	}

	/**
	 * Checks whether all brackets, quotes, unquoted URLs, comments, and escapes in $source are balanced.
	 */
	public static function isBalanced(string $source, int $start = 0, ?int $end = null): bool
	{
		$stack = [];
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		$baseStart = max(0, $start);
		$offset = $baseStart;
		$lastEscapeOffset = -1;
		$mask = "\\/\"'()[]{}";

		while ($offset < $length) {
			$offset += strcspn($source, $mask, $offset, $length - $offset);
			if ($offset >= $length) {
				break;
			}

			$character = $source[$offset];

			if ($character === '"' || $character === "'") {
				$valid = true;
				$strEnd = self::scanQuotedString($source, $offset, $length, $valid);
				if (!$valid) {
					return false;
				}
				$offset = $strEnd + 1;
				continue;
			}

			if ($character === '/') {
				if ($offset + 1 < $length && $source[$offset + 1] === '*') {
					$commentEnd = strpos($source, '*/', $offset + 2);
					if ($commentEnd === false || $commentEnd + 1 >= $length) {
						return false;
					}
					$offset = $commentEnd + 2;
					continue;
				}
				$offset++;
				continue;
			}

			if ($character === '\\') {
				if ($offset + 1 >= $length) {
					return false;
				}
				$next = $source[$offset + 1];
				if ($next === "\n" || $next === "\r" || $next === "\f") {
					return false;
				}
				$lastEscapeOffset = $offset;
				$offset += 2;
				continue;
			}

			if ($character === '(' && self::isUnquotedUrlStart($source, $offset, $baseStart, $length, $lastEscapeOffset)) {
				$valid = true;
				$urlEnd = self::scanUnquotedUrl($source, $offset, $length, $valid);
				if (!$valid) {
					return false;
				}
				$offset = $urlEnd + 1;
				continue;
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				$offset++;
				continue;
			}

			if (isset(self::CLOSING_SET[$character])) {
				if ($stack === [] || end($stack) !== $character) {
					return false;
				}
				array_pop($stack);
			}
			$offset++;
		}

		return $stack === [];
	}

	/**
	 * Returns true if $source contains an unclosed CSS comment, quoted string, or unclosed unquoted URL.
	 */
	public static function hasUnclosedCommentOrString(string $source, int $start = 0, ?int $end = null): bool
	{
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		$baseStart = max(0, $start);
		$offset = $baseStart;
		$lastEscapeOffset = -1;
		$mask = "\\/\"'(";

		while ($offset < $length) {
			$offset += strcspn($source, $mask, $offset, $length - $offset);
			if ($offset >= $length) {
				break;
			}

			$character = $source[$offset];

			if ($character === '"' || $character === "'") {
				$valid = true;
				$strEnd = self::scanQuotedString($source, $offset, $length, $valid);
				if (!$valid) {
					return true;
				}
				$offset = $strEnd + 1;
				continue;
			}

			if ($character === '/') {
				if ($offset + 1 < $length && $source[$offset + 1] === '*') {
					$commentEnd = strpos($source, '*/', $offset + 2);
					if ($commentEnd === false || $commentEnd + 1 >= $length) {
						return true;
					}
					$offset = $commentEnd + 2;
					continue;
				}
				$offset++;
				continue;
			}

			if ($character === '\\') {
				if ($offset + 1 >= $length) {
					return true;
				}
				$lastEscapeOffset = $offset;
				$offset += 2;
				continue;
			}

			if ($character === '(' && self::isUnquotedUrlStart($source, $offset, $baseStart, $length, $lastEscapeOffset)) {
				$valid = true;
				$urlEnd = self::scanUnquotedUrl($source, $offset, $length, $valid);
				if (!$valid) {
					return true;
				}
				$offset = $urlEnd + 1;
				continue;
			}

			$offset++;
		}

		return false;
	}

	/**
	 * Returns true if $source contains disallowed ASCII control characters (including NUL).
	 */
	public static function hasControlCharacters(string $source): bool
	{
		return strcspn($source, self::CONTROL_BYTES) !== strlen($source);
	}

	/**
	 * Validates that a CSS fragment (selector, prelude, condition, or declaration value)
	 * is balanced, contains no control bytes, and contains none of the forbidden delimiters
	 * at top level (and optionally anywhere outside strings/comments/unquoted URLs) in a single pass.
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
		$length = strlen($source);
		if (strcspn($source, self::CONTROL_BYTES) !== $length) {
			throw new InvalidArgumentException($label . ' contains disallowed control characters.');
		}

		$topBytes = $forbiddenTopLevel === [] ? '' : implode('', $forbiddenTopLevel);
		$anyBytes = $forbiddenAnywhere === [] ? '' : implode('', $forbiddenAnywhere);
		$mask = "\\/\"'()[]{}" . $topBytes . $anyBytes;

		$stack = [];
		$offset = 0;
		$lastEscapeOffset = -1;

		while ($offset < $length) {
			$offset += strcspn($source, $mask, $offset, $length - $offset);
			if ($offset >= $length) {
				break;
			}

			$character = $source[$offset];

			if ($character === '"' || $character === "'") {
				$valid = true;
				$strEnd = self::scanQuotedString($source, $offset, $length, $valid);
				if (!$valid) {
					throw new InvalidArgumentException($label . ' has unbalanced syntax or unclosed comments/strings.');
				}
				$offset = $strEnd + 1;
				continue;
			}

			if ($character === '/') {
				if ($offset + 1 < $length && $source[$offset + 1] === '*') {
					$commentEnd = strpos($source, '*/', $offset + 2);
					if ($commentEnd === false) {
						throw new InvalidArgumentException($label . ' has unbalanced syntax or unclosed comments/strings.');
					}
					$offset = $commentEnd + 2;
					continue;
				}
				if (($anyBytes !== '' && str_contains($anyBytes, $character)) || ($stack === [] && $topBytes !== '' && str_contains($topBytes, $character))) {
					throw new InvalidArgumentException(sprintf('%s cannot contain "%s".', $label, $character));
				}
				$offset++;
				continue;
			}

			if ($character === '\\') {
				if ($offset + 1 >= $length) {
					throw new InvalidArgumentException($label . ' has unbalanced syntax or unclosed comments/strings.');
				}
				$next = $source[$offset + 1];
				if ($next === "\n" || $next === "\r" || $next === "\f") {
					throw new InvalidArgumentException($label . ' has unbalanced syntax or unclosed comments/strings.');
				}
				$lastEscapeOffset = $offset;
				$offset += 2;
				continue;
			}

			if (($anyBytes !== '' && str_contains($anyBytes, $character)) || ($stack === [] && $topBytes !== '' && str_contains($topBytes, $character))) {
				throw new InvalidArgumentException(sprintf('%s cannot contain "%s".', $label, $character));
			}

			if ($character === '(' && self::isUnquotedUrlStart($source, $offset, 0, $length, $lastEscapeOffset)) {
				$valid = true;
				$urlEnd = self::scanUnquotedUrl($source, $offset, $length, $valid);
				if (!$valid) {
					throw new InvalidArgumentException($label . ' has unbalanced syntax or unclosed comments/strings.');
				}
				$offset = $urlEnd + 1;
				continue;
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				$offset++;
				continue;
			}

			if (isset(self::CLOSING_SET[$character])) {
				if ($stack === [] || end($stack) !== $character) {
					throw new InvalidArgumentException($label . ' has unbalanced syntax or unclosed comments/strings.');
				}
				array_pop($stack);
			}
			$offset++;
		}

		if ($stack !== []) {
			throw new InvalidArgumentException($label . ' has unbalanced syntax or unclosed comments/strings.');
		}
	}

	/**
	 * Finds the byte offset of the `!` starting a trailing top-level `!important` annotation,
	 * decoding CSS escapes in the `important` keyword and ignoring earlier `!` tokens.
	 */
	public static function findLastTopLevelImportant(string $source, int $start = 0, ?int $end = null): ?int
	{
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		$baseStart = max(0, $start);
		$offset = $baseStart;
		$stack = [];
		$lastBang = null;
		$lastEscapeOffset = -1;
		$mask = "\\/\"'()[]{}!";

		while ($offset < $length) {
			$offset += strcspn($source, $mask, $offset, $length - $offset);
			if ($offset >= $length) {
				break;
			}

			$character = $source[$offset];

			if ($character === '"' || $character === "'") {
				$valid = true;
				$strEnd = self::scanQuotedString($source, $offset, $length, $valid);
				$offset = $valid ? $strEnd + 1 : $strEnd;
				continue;
			}

			if ($character === '/') {
				if ($offset + 1 < $length && $source[$offset + 1] === '*') {
					$commentEnd = strpos($source, '*/', $offset + 2);
					if ($commentEnd === false || $commentEnd + 1 >= $length) {
						break;
					}
					$offset = $commentEnd + 2;
					continue;
				}
				$offset++;
				continue;
			}

			if ($character === '\\') {
				$lastEscapeOffset = $offset;
				$offset += 2;
				continue;
			}

			if ($character === '!' && $stack === []) {
				$lastBang = $offset;
				$offset++;
				continue;
			}

			if ($character === '(' && self::isUnquotedUrlStart($source, $offset, $baseStart, $length, $lastEscapeOffset)) {
				$valid = true;
				$urlEnd = self::scanUnquotedUrl($source, $offset, $length, $valid);
				$offset = $urlEnd < $length ? $urlEnd + 1 : $length;
				continue;
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				$offset++;
				continue;
			}

			if (isset(self::CLOSING_SET[$character]) && $stack !== [] && end($stack) === $character) {
				array_pop($stack);
			}
			$offset++;
		}

		if ($lastBang === null) {
			return null;
		}

		$tail = self::trimCssWhitespace(self::removeComments($source, $lastBang + 1, $length));
		if ($tail === '') {
			return null;
		}

		if (str_contains($tail, '\\')) {
			$tail = self::decodeCssIdentEscapes($tail);
		}

		return strcasecmp($tail, 'important') === 0 ? $lastBang : null;
	}

	/**
	 * Decodes CSS identifier escape sequences per CSS Syntax Level 3 §4.3.7.
	 */
	public static function decodeCssIdentEscapes(string $ident): string
	{
		if (!str_contains($ident, '\\')) {
			return $ident;
		}

		$length = strlen($ident);
		$offset = 0;
		$chunks = [];

		while ($offset < $length) {
			$span = strcspn($ident, '\\', $offset, $length - $offset);
			if ($span > 0) {
				$chunks[] = substr($ident, $offset, $span);
				$offset += $span;
				if ($offset >= $length) {
					break;
				}
			}

			// Skip '\'
			$offset++;
			if ($offset >= $length) {
				$chunks[] = "\u{FFFD}";
				break;
			}

			$hexLen = strspn($ident, self::HEX_DIGITS, $offset, min(6, $length - $offset));
			if ($hexLen > 0) {
				$codepoint = (int) hexdec(substr($ident, $offset, $hexLen));
				$offset += $hexLen;

				// Consume optional single whitespace (treating \r\n as one)
				if ($offset < $length) {
					$ws = $ident[$offset];
					if ($ws === "\r" && $offset + 1 < $length && $ident[$offset + 1] === "\n") {
						$offset += 2;
					} elseif ($ws === ' ' || $ws === "\t" || $ws === "\n" || $ws === "\r" || $ws === "\f") {
						$offset++;
					}
				}

				if ($codepoint === 0 || ($codepoint >= 0xD800 && $codepoint <= 0xDFFF) || $codepoint > 0x10FFFF) {
					$chunks[] = "\u{FFFD}";
				} else {
					$chunks[] = self::encodeUtf8Codepoint($codepoint);
				}
				continue;
			}

			$next = $ident[$offset];
			if ($next === "\n" || $next === "\r" || $next === "\f") {
				$chunks[] = '\\';
				continue;
			}

			$chunks[] = $next;
			$offset++;
		}

		return implode('', $chunks);
	}

	/**
	 * Scans and decodes a CSS identifier starting at $start, returning null on invalid syntax or control bytes.
	 *
	 * @return array{ident: string, end: int}|null
	 */
	public static function decodeIdentAt(string $source, int $start, ?int $end = null): ?array
	{
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		$identEnd = self::scanIdentEnd($source, $start, $length);
		if ($identEnd === null || $identEnd <= $start) {
			return null;
		}
		$raw = substr($source, $start, $identEnd - $start);
		$decoded = str_contains($raw, '\\') ? self::decodeCssIdentEscapes($raw) : $raw;
		if (self::hasControlCharacters($decoded)) {
			return null;
		}

		return ['ident' => $decoded, 'end' => $identEnd];
	}

	/**
	 * Locates the start offset of a CSS identifier ending at $openParen matching lowercase $expectedLower, or null.
	 */
	public static function findPrecedingEscapedIdentStart(
		string $source,
		int $openParen,
		int $sliceStart,
		string $expectedLower,
	): ?int {
		if ($openParen <= $sliceStart) {
			return null;
		}

		// Scan backward within a bounded window to find the start of the escaped identifier token.
		$windowStart = max($sliceStart, $openParen - self::ESCAPED_URL_WINDOW);
		$cursor = $openParen - 1;

		while ($cursor >= $windowStart) {
			$ch = $source[$cursor];
			if (self::isIdentByte($ch)) {
				$cursor--;
				continue;
			}

			// A single whitespace immediately following a hex escape (e.g. `\75 rl(`), or an escaped space (`\ `).
			if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r" || $ch === "\f") {
				$hexEnd = $cursor - 1;
				if ($ch === "\n" && $hexEnd >= $windowStart && $source[$hexEnd] === "\r") {
					$hexEnd--;
				}
				$hexCursor = $hexEnd;
				$hexCount = 0;
				while ($hexCursor >= $windowStart && $hexCount < 6 && str_contains(self::HEX_DIGITS, $source[$hexCursor])) {
					$hexCursor--;
					$hexCount++;
				}
				if ($hexCount > 0 && $hexCursor >= $windowStart && $source[$hexCursor] === '\\' && !self::isEscapedBackslash($source, $hexCursor, $sliceStart)) {
					$cursor = $hexCursor - 1;
					continue;
				}
				if ($hexCount > 0 && $hexCursor < $windowStart && $windowStart > $sliceStart) {
					return null;
				}
				if ($hexCount === 0 && ($ch === ' ' || $ch === "\t") && $cursor > $sliceStart && $source[$cursor - 1] === '\\' && !self::isEscapedBackslash($source, $cursor - 1, $sliceStart)) {
					$cursor -= 2;
					continue;
				}
				break;
			}

			if ($ch === '\\') {
				if ($cursor > $sliceStart && $source[$cursor - 1] === '\\') {
					return null;
				}
				$cursor--;
				continue;
			}

			if ($cursor > $sliceStart && $source[$cursor - 1] === '\\' && !self::isEscapedBackslash($source, $cursor - 1, $sliceStart)) {
				$cursor -= 2;
				continue;
			}

			break;
		}

		$identStart = $cursor + 1;
		if ($identStart >= $openParen) {
			return null;
		}
		if ($identStart > $sliceStart) {
			$prev = $source[$identStart - 1];
			if ($prev === '#' || $prev === '@') {
				return null;
			}
			if ($cursor < $windowStart && $windowStart > $sliceStart) {
				if (self::isIdentByte($prev) || $prev === '\\') {
					return null;
				}
				if (str_contains(self::CSS_WHITESPACE, $prev)) {
					$beforeWs = $identStart - 2;
					if ($prev === "\n" && $beforeWs >= $sliceStart && $source[$beforeWs] === "\r") {
						$beforeWs--;
					}
					if ($beforeWs >= $sliceStart && self::endsWithBareHexEscape(substr($source, max($sliceStart, $beforeWs - 6), $beforeWs - max($sliceStart, $beforeWs - 6) + 1))) {
						return null;
					}
				}
			}
		}

		if (self::scanIdentEnd($source, $identStart, $openParen) !== $openParen) {
			return null;
		}
		$raw = substr($source, $identStart, $openParen - $identStart);
		$decoded = str_contains($raw, '\\') ? self::decodeCssIdentEscapes($raw) : $raw;
		if (self::hasControlCharacters($decoded) || strtolower($decoded) !== $expectedLower) {
			return null;
		}

		return $identStart;
	}

	/**
	 * Scans a CSS identifier per CSS Syntax Level 3 §4.3.9 and returns its end offset, or null if invalid.
	 */
	public static function scanIdentEnd(string $source, int $start, ?int $end = null): ?int
	{
		$length = $end === null ? strlen($source) : min($end, strlen($source));
		$pos = $start;
		if ($pos >= $length) {
			return null;
		}

		if ($source[$pos] === '-') {
			$pos++;
			if ($pos >= $length) {
				return null;
			}
		}

		$first = $source[$pos];
		if ($first === '-') {
			$pos++;
		} elseif (($first >= 'a' && $first <= 'z') || ($first >= 'A' && $first <= 'Z') || $first === '_' || ord($first) >= 0x80) {
			$pos++;
		} elseif ($first === '\\' && $pos + 1 < $length && $source[$pos + 1] !== "\n" && $source[$pos + 1] !== "\r" && $source[$pos + 1] !== "\f") {
			$pos = self::advanceIdentEscapeEnd($source, $pos, $length);
		} else {
			return null;
		}

		while ($pos < $length) {
			$asciiLen = strspn($source, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_', $pos, $length - $pos);
			if ($asciiLen > 0) {
				$pos += $asciiLen;
				if ($pos >= $length) {
					break;
				}
			}
			$ch = $source[$pos];
			if (ord($ch) >= 0x80) {
				$pos++;
				continue;
			}
			if ($ch === '\\' && $pos + 1 < $length && $source[$pos + 1] !== "\n" && $source[$pos + 1] !== "\r" && $source[$pos + 1] !== "\f") {
				$pos = self::advanceIdentEscapeEnd($source, $pos, $length);
				continue;
			}
			break;
		}

		return $pos;
	}

	/**
	 * Decodes and lowercases a single CSS function name immediately preceding `$openParen`, or returns null.
	 */
	public static function decodeLeadingFunctionName(string $source, int $openParen): ?string
	{
		if ($openParen <= 0 || $openParen >= strlen($source)) {
			return null;
		}

		if (!str_contains(substr($source, 0, $openParen), '\\')
			&& strspn($source, 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_', 0, $openParen) === $openParen
		) {
			$c0 = $source[0];
			if ($c0 >= '0' && $c0 <= '9') {
				return null;
			}
			if ($c0 === '-' && ($openParen < 2 || ($source[1] >= '0' && $source[1] <= '9'))) {
				return null;
			}
			return strtolower(substr($source, 0, $openParen));
		}

		if (self::scanIdentEnd($source, 0, $openParen) !== $openParen) {
			return null;
		}
		$raw = substr($source, 0, $openParen);
		$decoded = str_contains($raw, '\\') ? self::decodeCssIdentEscapes($raw) : $raw;
		if (self::hasControlCharacters($decoded)) {
			return null;
		}

		return strtolower($decoded);
	}

	/**
	 * Returns true if $token ends with a 1–6 hex digit escape sequence with no trailing whitespace.
	 */
	public static function endsWithBareHexEscape(string $token): bool
	{
		$length = strlen($token);
		if ($length < 2) {
			return false;
		}
		$pos = $length - 1;
		$hexCount = 0;
		while ($pos >= 0 && $hexCount < 6 && str_contains(self::HEX_DIGITS, $token[$pos])) {
			$pos--;
			$hexCount++;
		}

		return $hexCount > 0 && $pos >= 0 && $token[$pos] === '\\' && !self::isEscapedBackslash($token, $pos, 0);
	}

	private static function advanceIdentEscapeEnd(string $source, int $slashOffset, int $length): int
	{
		$hexLen = strspn($source, self::HEX_DIGITS, $slashOffset + 1, min(6, $length - ($slashOffset + 1)));
		if ($hexLen === 0) {
			return $slashOffset + 2;
		}
		$end = $slashOffset + 1 + $hexLen;
		if ($end < $length) {
			$ws = $source[$end];
			if ($ws === "\r" && $end + 1 < $length && $source[$end + 1] === "\n") {
				$end += 2;
			} elseif ($ws === ' ' || $ws === "\t" || $ws === "\n" || $ws === "\r" || $ws === "\f") {
				$end++;
			}
		}

		return $end;
	}

	public static function trimCssWhitespace(string $value): string
	{
		return trim($value, self::CSS_WHITESPACE);
	}

	/**
	 * @internal Zero-allocation control-byte scan over a slice of $source.
	 */
	public static function hasControlCharactersInSlice(string $source, int $start, int $length): bool
	{
		return $length > 0 && strcspn($source, self::CONTROL_BYTES, $start, $length) < $length;
	}

	/**
	 * @internal Scans a quoted string starting at $openQuoteOffset and returns its closing quote (or bad-string end) offset.
	 */
	public static function scanQuotedStringEnd(string $source, int $openQuoteOffset, int $length, bool &$valid): int
	{
		return self::scanQuotedString($source, $openQuoteOffset, $length, $valid);
	}

	private static function scanQuotedString(string $source, int $openQuoteOffset, int $length, bool &$valid): int
	{
		$quote = $source[$openQuoteOffset];
		$mask = $quote . "\\\n\r\f";
		$pos = $openQuoteOffset + 1;

		while ($pos < $length) {
			$pos += strcspn($source, $mask, $pos, $length - $pos);
			if ($pos >= $length) {
				$valid = false;
				return $length;
			}

			$ch = $source[$pos];
			if ($ch === $quote) {
				$valid = true;
				return $pos;
			}

			if ($ch === '\\') {
				if ($pos + 1 >= $length) {
					$valid = false;
					return $length;
				}
				$hexLen = strspn($source, self::HEX_DIGITS, $pos + 1, min(6, $length - ($pos + 1)));
				if ($hexLen > 0) {
					$pos += 1 + $hexLen;
					if ($pos < $length) {
						$ws = $source[$pos];
						if ($ws === "\r" && $pos + 1 < $length && $source[$pos + 1] === "\n") {
							$pos += 2;
						} elseif ($ws === ' ' || $ws === "\t" || $ws === "\n" || $ws === "\r" || $ws === "\f") {
							$pos++;
						}
					}
				} elseif ($source[$pos + 1] === "\r" && $pos + 2 < $length && $source[$pos + 2] === "\n") {
					$pos += 3;
				} else {
					$pos += 2;
				}
				continue;
			}

			// Unescaped newline inside string: <bad-string-token>
			$valid = false;
			return $pos;
		}

		$valid = false;
		return $length;
	}

	/** @internal */
	public static function isUnquotedUrlStartInternal(
		string $source,
		int $openParenOffset,
		int $start,
		int $length,
		int $lastEscapeOffset,
	): bool {
		return self::isUnquotedUrlStart($source, $openParenOffset, $start, $length, $lastEscapeOffset);
	}

	/** @internal */
	public static function scanUnquotedUrlEnd(string $source, int $openParenOffset, int $length, bool &$valid): int
	{
		return self::scanUnquotedUrl($source, $openParenOffset, $length, $valid);
	}

	private static function isUnquotedUrlStart(
		string $source,
		int $openParenOffset,
		int $start,
		int $length,
		int $lastEscapeOffset,
	): bool {
		if ($openParenOffset <= $start) {
			return false;
		}

		if ($lastEscapeOffset < $start) {
			if ($openParenOffset < $start + 3) {
				return false;
			}
			$lastChar = $source[$openParenOffset - 1];
			if ($lastChar !== 'l' && $lastChar !== 'L') {
				return false;
			}
			if (substr_compare($source, 'url', $openParenOffset - 3, 3, true) !== 0) {
				return false;
			}
			if ($openParenOffset - 4 >= $start) {
				$prev = $source[$openParenOffset - 4];
				if ($prev === '#' || $prev === '@' || self::isIdentByte($prev)) {
					return false;
				}
			}
		} else {
			if (!self::precedingEscapedIdentIsUrl($source, $openParenOffset, $start, $lastEscapeOffset)) {
				return false;
			}
		}

		$afterParen = $openParenOffset + 1;
		$afterParen += strspn($source, self::ASCII_WHITESPACE, $afterParen, $length - $afterParen);
		if ($afterParen < $length && ($source[$afterParen] === '"' || $source[$afterParen] === "'")) {
			return false;
		}

		return true;
	}

	private static function precedingEscapedIdentIsUrl(
		string $source,
		int $openParenOffset,
		int $start,
		int $lastEscapeOffset,
	): bool {
		if ($lastEscapeOffset < $openParenOffset - self::ESCAPED_URL_WINDOW) {
			$lastChar = $source[$openParenOffset - 1];
			if ($lastChar !== 'l' && $lastChar !== 'L') {
				return false;
			}
			if ($openParenOffset < $start + 3 || substr_compare($source, 'url', $openParenOffset - 3, 3, true) !== 0) {
				return false;
			}
			if ($openParenOffset - 4 < $start) {
				return true;
			}
			$prev = $source[$openParenOffset - 4];
			return $prev !== '#' && $prev !== '@' && !self::isIdentByte($prev);
		}

		return self::findPrecedingEscapedIdentStart($source, $openParenOffset, $start, 'url') !== null;
	}

	private static function isEscapedBackslash(string $source, int $slashOffset, int $start): bool
	{
		$count = 0;
		$pos = $slashOffset - 1;
		while ($pos >= $start && $source[$pos] === '\\') {
			$count++;
			$pos--;
		}

		return ($count & 1) === 1;
	}

	private static function scanUnquotedUrl(string $source, int $openParenOffset, int $length, bool &$valid): int
	{
		$pos = $openParenOffset + 1;
		$pos += strspn($source, self::ASCII_WHITESPACE, $pos, $length - $pos);
		$mask = "\\)\"'(" . self::CONTROL_BYTES . self::ASCII_WHITESPACE;
		$valid = false;

		while ($pos < $length) {
			$pos += strcspn($source, $mask, $pos, $length - $pos);
			if ($pos >= $length) {
				return $length;
			}

			$ch = $source[$pos];
			if ($ch === ')') {
				$valid = true;
				return $pos;
			}

			if ($ch === '\\') {
				if ($pos + 1 >= $length) {
					return $length;
				}
				$next = $source[$pos + 1];
				if ($next === "\n" || $next === "\r" || $next === "\f") {
					$pos += 2;
					break;
				}
				$hexLen = strspn($source, self::HEX_DIGITS, $pos + 1, min(6, $length - ($pos + 1)));
				if ($hexLen > 0) {
					$pos += 1 + $hexLen;
					if ($pos < $length) {
						$ws = $source[$pos];
						if ($ws === "\r" && $pos + 1 < $length && $source[$pos + 1] === "\n") {
							$pos += 2;
						} elseif ($ws === ' ' || $ws === "\t" || $ws === "\n" || $ws === "\r" || $ws === "\f") {
							$pos++;
						}
					}
				} else {
					$pos += 2;
				}
				continue;
			}

			if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r" || $ch === "\f") {
				$pos += strspn($source, self::ASCII_WHITESPACE, $pos, $length - $pos);
				if ($pos < $length && $source[$pos] === ')') {
					$valid = true;
					return $pos;
				}
				break;
			}

			// Disallowed unescaped ", ', (, or control byte inside unquoted url(...)
			$pos++;
			break;
		}

		// Consume <bad-url-token> remnants up to the first unescaped ')' or EOF.
		while ($pos < $length) {
			$pos += strcspn($source, "\\)", $pos, $length - $pos);
			if ($pos >= $length) {
				return $length;
			}
			if ($source[$pos] === ')') {
				return $pos;
			}
			$pos += 2;
		}

		return $length;
	}

	private static function isIdentByte(string $byte): bool
	{
		$ord = ord($byte);
		return ($ord >= 0x61 && $ord <= 0x7A)
			|| ($ord >= 0x41 && $ord <= 0x5A)
			|| ($ord >= 0x30 && $ord <= 0x39)
			|| $ord === 0x5F
			|| $ord === 0x2D
			|| $ord >= 0x80;
	}

	private static function encodeUtf8Codepoint(int $cp): string
	{
		if ($cp <= 0x7F) {
			return chr($cp);
		}
		if ($cp <= 0x7FF) {
			return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
		}
		if ($cp <= 0xFFFF) {
			return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
		}

		return chr(0xF0 | ($cp >> 18))
			. chr(0x80 | (($cp >> 12) & 0x3F))
			. chr(0x80 | (($cp >> 6) & 0x3F))
			. chr(0x80 | ($cp & 0x3F));
	}
}
