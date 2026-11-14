<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

/**
 * Scans CSS component values without treating delimiters in nested blocks as top-level.
 */
final class CSSSyntaxScanner
{
	/** @var array<string, string> */
	private const CLOSING_DELIMITERS = [
		'(' => ')',
		'[' => ']',
		'{' => '}',
	];

	/** @return list<string> */
	public static function splitTopLevel(string $source, string $delimiter): array
	{
		if (strlen($delimiter) !== 1) {
			throw new \InvalidArgumentException('The delimiter must be a single byte.');
		}

		$parts = [];
		$start = 0;
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

			if ($character === '/' && ($source[$offset + 1] ?? '') === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false) {
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

			if (in_array($character, [')', ']', '}'], true)) {
				if ($stack !== [] && end($stack) === $character) {
					array_pop($stack);
				}
				continue;
			}

			if ($character === $delimiter && $stack === []) {
				$parts[] = substr($source, $start, $offset - $start);
				$start = $offset + 1;
			}
		}

		$parts[] = substr($source, $start);

		return $parts;
	}

	/** @return list<string> */
	public static function splitTopLevelWhitespace(string $source): array
	{
		$parts = [];
		$start = null;
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
			if ($character === '/' && ($source[$offset + 1] ?? '') === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false) {
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
			if (in_array($character, [')', ']', '}'], true)) {
				if ($stack !== [] && end($stack) === $character) {
					array_pop($stack);
				}
				continue;
			}
			if (ctype_space($character) && $stack === []) {
				if ($start !== null) {
					$parts[] = substr($source, $start, $offset - $start);
					$start = null;
				}
				continue;
			}
			$start ??= $offset;
		}

		if ($start !== null) {
			$parts[] = substr($source, $start);
		}
		return $parts;
	}

	public static function findTopLevel(string $source, string $delimiter): ?int
	{
		if (strlen($delimiter) !== 1) {
			throw new \InvalidArgumentException('The delimiter must be a single byte.');
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

			if ($character === '/' && ($source[$offset + 1] ?? '') === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false) {
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

			if (in_array($character, [')', ']', '}'], true)) {
				if ($stack !== [] && end($stack) === $character) {
					array_pop($stack);
				}
				continue;
			}

		}

		return null;
	}

	/**
	 * @param list<string> $delimiters
	 * @return array{offset: int, delimiter: string}|null
	 */
	public static function findNextTopLevelDelimiter(string $source, array $delimiters, int $start = 0): ?array
	{
		$delimiters = array_fill_keys($delimiters, true);
		$stack = [];
		$quote = null;
		$length = strlen($source);

		for ($offset = $start; $offset < $length; $offset++) {
			$character = $source[$offset];

			if ($quote !== null) {
				if ($character === '\\') {
					$offset++;
				} elseif ($character === $quote) {
					$quote = null;
				}
				continue;
			}

			if ($character === '/' && ($source[$offset + 1] ?? '') === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false) {
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

			if ($stack === [] && isset($delimiters[$character])) {
				return ['offset' => $offset, 'delimiter' => $character];
			}

			if (isset(self::CLOSING_DELIMITERS[$character])) {
				$stack[] = self::CLOSING_DELIMITERS[$character];
				continue;
			}

			if (in_array($character, [')', ']', '}'], true) && $stack !== [] && end($stack) === $character) {
				array_pop($stack);
			}
		}

		return null;
	}

	public static function findMatchingDelimiter(string $source, int $openOffset): ?int
	{
		$opening = $source[$openOffset] ?? null;
		if ($opening === null || !isset(self::CLOSING_DELIMITERS[$opening])) {
			return null;
		}

		$stack = [self::CLOSING_DELIMITERS[$opening]];
		$quote = null;
		$length = strlen($source);

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

			if ($character === '/' && ($source[$offset + 1] ?? '') === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false) {
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

			if (in_array($character, [')', ']', '}'], true)) {
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

	public static function removeComments(string $source): string
	{
		$result = '';
		$quote = null;
		$length = strlen($source);

		for ($offset = 0; $offset < $length; $offset++) {
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

			if (($character === '"' || $character === "'")) {
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

			if ($character === '/' && ($source[$offset + 1] ?? '') === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false) {
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

	public static function isBalanced(string $source): bool
	{
		$stack = [];
		$quote = null;
		$length = strlen($source);

		for ($offset = 0; $offset < $length; $offset++) {
			$character = $source[$offset];
			if ($quote !== null) {
				if ($character === '\\') {
					if (++$offset >= $length) {
						return false;
					}
				} elseif ($character === $quote) {
					$quote = null;
				}
				continue;
			}
			if ($character === '/' && ($source[$offset + 1] ?? '') === '*') {
				$commentEnd = strpos($source, '*/', $offset + 2);
				if ($commentEnd === false) {
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
			if (in_array($character, [')', ']', '}'], true)) {
				if ($stack === [] || end($stack) !== $character) {
					return false;
				}
				array_pop($stack);
			}
		}

		return $quote === null && $stack === [];
	}
}
