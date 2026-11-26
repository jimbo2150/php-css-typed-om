<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;

/**
 * Implements CSS Syntax Level 3 §3.2–§3.3 input stream preprocessing:
 * strips at most one leading UTF-8 BOM, normalizes CR/CRLF/FF to LF,
 * replaces NUL, UTF-8 surrogates, and ill-formed UTF-8 maximal subparts
 * with U+FFFD, and enforces `maxBytes` against 3× byte amplification.
 */
final class CSSInputPreprocessor
{
	private const string UTF8_BOM = "\xEF\xBB\xBF";
	private const string REPLACEMENT_CHAR = "\u{FFFD}";
	private const string PREPROCESS_ASCII_TRIGGERS = "\r\f\x00";

	private const string NON_FAST_ASCII_BYTES = "\r\f\x00"
		. "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x8D\x8E\x8F"
		. "\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9A\x9B\x9C\x9D\x9E\x9F"
		. "\xA0\xA1\xA2\xA3\xA4\xA5\xA6\xA7\xA8\xA9\xAA\xAB\xAC\xAD\xAE\xAF"
		. "\xB0\xB1\xB2\xB3\xB4\xB5\xB6\xB7\xB8\xB9\xBA\xBB\xBC\xBD\xBE\xBF"
		. "\xC0\xC1\xC2\xC3\xC4\xC5\xC6\xC7\xC8\xC9\xCA\xCB\xCC\xCD\xCE\xCF"
		. "\xD0\xD1\xD2\xD3\xD4\xD5\xD6\xD7\xD8\xD9\xDA\xDB\xDC\xDD\xDE\xDF"
		. "\xE0\xE1\xE2\xE3\xE4\xE5\xE6\xE7\xE8\xE9\xEA\xEB\xEC\xED\xEE\xEF"
		. "\xF0\xF1\xF2\xF3\xF4\xF5\xF6\xF7\xF8\xF9\xFA\xFB\xFC\xFD\xFE\xFF";

	/**
	 * Preprocesses raw CSS bytes according to CSS Syntax Level 3 §3.3 and WHATWG Encoding §8.1.1.
	 *
	 * @throws CSSParserLimitException When raw or preprocessed byte size exceeds `$options->maxBytes`
	 */
	public static function preprocess(string $input, ?CSSParserOptions $options = null): string
	{
		$options ??= CSSParserOptions::default();
		$maxBytes = $options->maxBytes;
		$length = strlen($input);

		if ($maxBytes !== null && $length > $maxBytes) {
			throw new CSSParserLimitException(sprintf('CSS input exceeds maximum size of %d bytes.', $maxBytes));
		}

		// Zero-copy fast path for clean ASCII inputs (excludes BOM 0xEF, CR, FF, NUL, and >= 0x80).
		if ($length === 0 || strcspn($input, self::NON_FAST_ASCII_BYTES) === $length) {
			return $input;
		}

		// C-level UTF-8 fast path: PCRE2 /u strictly rejects UTF-8 surrogates, overlongs, and >U+10FFFF.
		if (preg_match('//u', $input) === 1) {
			$baseStart = str_starts_with($input, self::UTF8_BOM) ? 3 : 0;
			$remLen = $length - $baseStart;
			if (strcspn($input, self::PREPROCESS_ASCII_TRIGGERS, $baseStart, $remLen) === $remLen) {
				return $baseStart === 0 ? $input : substr($input, 3);
			}
			if (strpos($input, "\x00", $baseStart) === false) {
				$slice = $baseStart === 0 ? $input : substr($input, 3);
				if (str_contains($slice, "\f")) {
					return str_replace(["\r\n", "\r", "\f"], "\n", $slice);
				}
				return str_replace(["\r\n", "\r"], "\n", $slice);
			}

			return self::preprocessLoop($input, $baseStart, $length, $maxBytes, true);
		}

		$baseStart = str_starts_with($input, self::UTF8_BOM) ? 3 : 0;

		return self::preprocessLoop($input, $baseStart, $length, $maxBytes, false);
	}

	/**
	 * Checks without allocating whether `$input` requires any §3.3 modification.
	 */
	public static function needsPreprocessing(string $input): bool
	{
		$length = strlen($input);
		if ($length === 0) {
			return false;
		}
		$span = strcspn($input, self::NON_FAST_ASCII_BYTES);
		if ($span === $length) {
			return false;
		}
		if (str_starts_with($input, self::UTF8_BOM)) {
			return true;
		}
		$rem = $length - $span;
		if (strcspn($input, self::PREPROCESS_ASCII_TRIGGERS, $span, $rem) < $rem) {
			return true;
		}

		return preg_match('//u', $input) !== 1;
	}

	/**
	 * @throws CSSParserLimitException When preprocessed byte size exceeds `$maxBytes`
	 */
	private static function preprocessLoop(string $input, int $baseStart, int $length, ?int $maxBytes, bool $validUtf8): string
	{
		$pos = $baseStart;
		$copyStart = $baseStart;
		$chunks = [];
		$outBytes = 0;
		$mask = $validUtf8 ? self::PREPROCESS_ASCII_TRIGGERS : self::NON_FAST_ASCII_BYTES;

		while ($pos < $length) {
			$span = strcspn($input, $mask, $pos, $length - $pos);
			if ($span > 0) {
				$outBytes += $span;
				self::assertOutputBytes($maxBytes, $outBytes);
				$pos += $span;
				if ($pos >= $length) {
					break;
				}
			}

			$byte = $input[$pos];
			if (!$validUtf8) {
				$ord = ord($byte);
				if ($ord >= 0x80) {
					$seqLen = self::classifyUtf8Sequence($input, $pos, $length, $ord);
					if ($seqLen > 0) {
						$outBytes += $seqLen;
						self::assertOutputBytes($maxBytes, $outBytes);
						$pos += $seqLen;
						continue;
					}

					if ($pos > $copyStart) {
						$chunks[] = substr($input, $copyStart, $pos - $copyStart);
					}
					$outBytes += 3;
					self::assertOutputBytes($maxBytes, $outBytes);
					$chunks[] = self::REPLACEMENT_CHAR;
					$pos -= $seqLen;
					$copyStart = $pos;
					continue;
				}
			}

			if ($pos > $copyStart) {
				$chunks[] = substr($input, $copyStart, $pos - $copyStart);
			}

			if ($byte === "\x00") {
				$outBytes += 3;
				self::assertOutputBytes($maxBytes, $outBytes);
				$chunks[] = self::REPLACEMENT_CHAR;
				$pos++;
			} elseif ($byte === "\r") {
				$outBytes++;
				self::assertOutputBytes($maxBytes, $outBytes);
				$chunks[] = "\n";
				$pos += ($pos + 1 < $length && $input[$pos + 1] === "\n") ? 2 : 1;
			} else {
				// $byte === "\f"
				$outBytes++;
				self::assertOutputBytes($maxBytes, $outBytes);
				$chunks[] = "\n";
				$pos++;
			}

			$copyStart = $pos;
		}

		if ($chunks === []) {
			return $baseStart === 0 ? $input : substr($input, $baseStart);
		}

		if ($copyStart < $length) {
			$chunks[] = substr($input, $copyStart, $length - $copyStart);
		}

		return implode('', $chunks);
	}

	private static function assertOutputBytes(?int $maxBytes, int $outBytes): void
	{
		if ($maxBytes !== null && $outBytes > $maxBytes) {
			throw new CSSParserLimitException(sprintf('Preprocessed CSS input exceeds maximum size of %d bytes.', $maxBytes));
		}
	}

	/**
	 * Returns `+2..+4` for a valid UTF-8 sequence starting with `$b0 >= 0x80`, or negative maximal subpart length `-1..-3`.
	 */
	private static function classifyUtf8Sequence(string $input, int $pos, int $length, int $b0): int
	{
		if ($b0 >= 0xC2 && $b0 <= 0xDF) {
			if ($pos + 1 >= $length) {
				return -1;
			}
			$b1 = ord($input[$pos + 1]);
			return ($b1 >= 0x80 && $b1 <= 0xBF) ? 2 : -1;
		}

		if ($b0 >= 0xE0 && $b0 <= 0xEF) {
			if ($pos + 1 >= $length) {
				return -1;
			}
			$b1 = ord($input[$pos + 1]);
			$validB1 = match ($b0) {
				0xE0 => $b1 >= 0xA0 && $b1 <= 0xBF,
				0xED => $b1 >= 0x80 && $b1 <= 0x9F,
				default => $b1 >= 0x80 && $b1 <= 0xBF,
			};
			if (!$validB1) {
				return -1;
			}
			if ($pos + 2 >= $length) {
				return -2;
			}
			$b2 = ord($input[$pos + 2]);
			return ($b2 >= 0x80 && $b2 <= 0xBF) ? 3 : -2;
		}

		if ($b0 >= 0xF0 && $b0 <= 0xF4) {
			if ($pos + 1 >= $length) {
				return -1;
			}
			$b1 = ord($input[$pos + 1]);
			$validB1 = match ($b0) {
				0xF0 => $b1 >= 0x90 && $b1 <= 0xBF,
				0xF4 => $b1 >= 0x80 && $b1 <= 0x8F,
				default => $b1 >= 0x80 && $b1 <= 0xBF,
			};
			if (!$validB1) {
				return -1;
			}
			if ($pos + 2 >= $length) {
				return -2;
			}
			$b2 = ord($input[$pos + 2]);
			if ($b2 < 0x80 || $b2 > 0xBF) {
				return -2;
			}
			if ($pos + 3 >= $length) {
				return -3;
			}
			$b3 = ord($input[$pos + 3]);
			return ($b3 >= 0x80 && $b3 <= 0xBF) ? 4 : -3;
		}

		return -1;
	}
}
