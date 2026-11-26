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
			if (strcspn($input, "\x00", $baseStart, $remLen) === $remLen) {
				$slice = $baseStart === 0 ? $input : substr($input, 3);
				return str_replace(["\r\n", "\r", "\f"], "\n", $slice);
			}

			return self::preprocessValidUtf8WithNul($input, $baseStart, $length, $maxBytes);
		}

		$baseStart = str_starts_with($input, self::UTF8_BOM) ? 3 : 0;
		$pos = $baseStart;
		$copyStart = $baseStart;
		$chunks = [];
		$outBytes = 0;

		while ($pos < $length) {
			$span = strcspn($input, self::NON_FAST_ASCII_BYTES, $pos, $length - $pos);
			if ($span > 0) {
				$outBytes += $span;
				if ($maxBytes !== null && $outBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('Preprocessed CSS input exceeds maximum size of %d bytes.', $maxBytes));
				}
				$pos += $span;
				if ($pos >= $length) {
					break;
				}
			}

			$byte = $input[$pos];
			$ord = ord($byte);

			if ($ord >= 0x80) {
				$validLen = self::validUtf8SequenceLength($input, $pos, $length, $ord);
				if ($validLen > 0) {
					$outBytes += $validLen;
					if ($maxBytes !== null && $outBytes > $maxBytes) {
						throw new CSSParserLimitException(sprintf('Preprocessed CSS input exceeds maximum size of %d bytes.', $maxBytes));
					}
					$pos += $validLen;
					continue;
				}

				if ($pos > $copyStart) {
					$chunks[] = substr($input, $copyStart, $pos - $copyStart);
				}
				$outBytes += 3;
				if ($maxBytes !== null && $outBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('Preprocessed CSS input exceeds maximum size of %d bytes.', $maxBytes));
				}
				$chunks[] = self::REPLACEMENT_CHAR;
				$pos += self::illFormedUtf8MaximalSubpartLength($input, $pos, $length, $ord);
				$copyStart = $pos;
				continue;
			}

			if ($pos > $copyStart) {
				$chunks[] = substr($input, $copyStart, $pos - $copyStart);
			}

			if ($byte === "\x00") {
				$outBytes += 3;
				if ($maxBytes !== null && $outBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('Preprocessed CSS input exceeds maximum size of %d bytes.', $maxBytes));
				}
				$chunks[] = self::REPLACEMENT_CHAR;
				$pos++;
			} elseif ($byte === "\r") {
				$outBytes++;
				if ($maxBytes !== null && $outBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('Preprocessed CSS input exceeds maximum size of %d bytes.', $maxBytes));
				}
				$chunks[] = "\n";
				$pos += ($pos + 1 < $length && $input[$pos + 1] === "\n") ? 2 : 1;
			} else {
				// $byte === "\f"
				$outBytes++;
				if ($maxBytes !== null && $outBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('Preprocessed CSS input exceeds maximum size of %d bytes.', $maxBytes));
				}
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

	/**
	 * Checks without allocating whether `$input` requires any §3.3 modification.
	 */
	public static function needsPreprocessing(string $input): bool
	{
		$length = strlen($input);
		if ($length === 0 || strcspn($input, self::NON_FAST_ASCII_BYTES) === $length) {
			return false;
		}
		if (str_starts_with($input, self::UTF8_BOM)) {
			return true;
		}
		if (strcspn($input, self::PREPROCESS_ASCII_TRIGGERS) < $length) {
			return true;
		}

		return preg_match('//u', $input) !== 1;
	}

	/**
	 * Preprocesses validated UTF-8 bytes containing at least one NUL byte while skipping valid multibyte runs in C.
	 *
	 * @throws CSSParserLimitException When preprocessed byte size exceeds `$maxBytes`
	 */
	private static function preprocessValidUtf8WithNul(string $input, int $baseStart, int $length, ?int $maxBytes): string
	{
		$pos = $baseStart;
		$copyStart = $baseStart;
		$chunks = [];
		$outBytes = 0;

		while ($pos < $length) {
			$span = strcspn($input, self::PREPROCESS_ASCII_TRIGGERS, $pos, $length - $pos);
			if ($span > 0) {
				$outBytes += $span;
				if ($maxBytes !== null && $outBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('Preprocessed CSS input exceeds maximum size of %d bytes.', $maxBytes));
				}
				$pos += $span;
				if ($pos >= $length) {
					break;
				}
			}

			if ($pos > $copyStart) {
				$chunks[] = substr($input, $copyStart, $pos - $copyStart);
			}

			$byte = $input[$pos];
			if ($byte === "\x00") {
				$outBytes += 3;
				if ($maxBytes !== null && $outBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('Preprocessed CSS input exceeds maximum size of %d bytes.', $maxBytes));
				}
				$chunks[] = self::REPLACEMENT_CHAR;
				$pos++;
			} elseif ($byte === "\r") {
				$outBytes++;
				if ($maxBytes !== null && $outBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('Preprocessed CSS input exceeds maximum size of %d bytes.', $maxBytes));
				}
				$chunks[] = "\n";
				$pos += ($pos + 1 < $length && $input[$pos + 1] === "\n") ? 2 : 1;
			} else {
				// $byte === "\f"
				$outBytes++;
				if ($maxBytes !== null && $outBytes > $maxBytes) {
					throw new CSSParserLimitException(sprintf('Preprocessed CSS input exceeds maximum size of %d bytes.', $maxBytes));
				}
				$chunks[] = "\n";
				$pos++;
			}

			$copyStart = $pos;
		}

		if ($copyStart < $length) {
			$chunks[] = substr($input, $copyStart, $length - $copyStart);
		}

		return implode('', $chunks);
	}

	private static function validUtf8SequenceLength(string $input, int $pos, int $length, int $b0): int
	{
		if ($b0 >= 0xC2 && $b0 <= 0xDF) {
			if ($pos + 1 >= $length) {
				return 0;
			}
			$b1 = ord($input[$pos + 1]);
			return ($b1 >= 0x80 && $b1 <= 0xBF) ? 2 : 0;
		}

		if ($b0 >= 0xE0 && $b0 <= 0xEF) {
			if ($pos + 2 >= $length) {
				return 0;
			}
			$b1 = ord($input[$pos + 1]);
			$b2 = ord($input[$pos + 2]);
			if ($b2 < 0x80 || $b2 > 0xBF) {
				return 0;
			}
			if ($b0 === 0xE0) {
				return ($b1 >= 0xA0 && $b1 <= 0xBF) ? 3 : 0;
			}
			if ($b0 === 0xED) {
				// Reject UTF-8 encoded surrogates U+D800..U+DFFF.
				return ($b1 >= 0x80 && $b1 <= 0x9F) ? 3 : 0;
			}
			return ($b1 >= 0x80 && $b1 <= 0xBF) ? 3 : 0;
		}

		if ($b0 >= 0xF0 && $b0 <= 0xF4) {
			if ($pos + 3 >= $length) {
				return 0;
			}
			$b1 = ord($input[$pos + 1]);
			$b2 = ord($input[$pos + 2]);
			$b3 = ord($input[$pos + 3]);
			if ($b2 < 0x80 || $b2 > 0xBF || $b3 < 0x80 || $b3 > 0xBF) {
				return 0;
			}
			if ($b0 === 0xF0) {
				return ($b1 >= 0x90 && $b1 <= 0xBF) ? 4 : 0;
			}
			if ($b0 === 0xF4) {
				return ($b1 >= 0x80 && $b1 <= 0x8F) ? 4 : 0;
			}
			return ($b1 >= 0x80 && $b1 <= 0xBF) ? 4 : 0;
		}

		return 0;
	}

	private static function illFormedUtf8MaximalSubpartLength(string $input, int $pos, int $length, int $b0): int
	{
		if ($b0 >= 0xE0 && $b0 <= 0xEF) {
			if ($pos + 1 >= $length) {
				return 1;
			}
			$b1 = ord($input[$pos + 1]);
			$validB1 = match ($b0) {
				0xE0 => $b1 >= 0xA0 && $b1 <= 0xBF,
				0xED => $b1 >= 0x80 && $b1 <= 0x9F,
				default => $b1 >= 0x80 && $b1 <= 0xBF,
			};
			return $validB1 ? 2 : 1;
		}

		if ($b0 >= 0xF0 && $b0 <= 0xF4) {
			if ($pos + 1 >= $length) {
				return 1;
			}
			$b1 = ord($input[$pos + 1]);
			$validB1 = match ($b0) {
				0xF0 => $b1 >= 0x90 && $b1 <= 0xBF,
				0xF4 => $b1 >= 0x80 && $b1 <= 0x8F,
				default => $b1 >= 0x80 && $b1 <= 0xBF,
			};
			if (!$validB1) {
				return 1;
			}
			if ($pos + 2 >= $length) {
				return 2;
			}
			$b2 = ord($input[$pos + 2]);
			return ($b2 >= 0x80 && $b2 <= 0xBF) ? 3 : 2;
		}

		return 1;
	}
}
