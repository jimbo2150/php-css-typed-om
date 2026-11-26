<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSSourceSpan;

/**
 * @internal Precomputed newline index for mapping CSS byte offsets to 1-based line and UTF-8 column positions.
 */
final class CSSSourceLocationIndex
{
	private const string HIGH_BYTES = "\x80\x81\x82\x83\x84\x85\x86\x87\x88\x89\x8A\x8B\x8C\x8D\x8E\x8F"
		. "\x90\x91\x92\x93\x94\x95\x96\x97\x98\x99\x9A\x9B\x9C\x9D\x9E\x9F"
		. "\xA0\xA1\xA2\xA3\xA4\xA5\xA6\xA7\xA8\xA9\xAA\xAB\xAC\xAD\xAE\xAF"
		. "\xB0\xB1\xB2\xB3\xB4\xB5\xB6\xB7\xB8\xB9\xBA\xBB\xBC\xBD\xBE\xBF"
		. "\xC0\xC1\xC2\xC3\xC4\xC5\xC6\xC7\xC8\xC9\xCA\xCB\xCC\xCD\xCE\xCF"
		. "\xD0\xD1\xD2\xD3\xD4\xD5\xD6\xD7\xD8\xD9\xDA\xDB\xDC\xDD\xDE\xDF"
		. "\xE0\xE1\xE2\xE3\xE4\xE5\xE6\xE7\xE8\xE9\xEA\xEB\xEC\xED\xEE\xEF"
		. "\xF0\xF1\xF2\xF3\xF4\xF5\xF6\xF7\xF8\xF9\xFA\xFB\xFC\xFD\xFE\xFF";

	private readonly int $length;
	private readonly bool $isAscii;

	/** @var list<int> */
	private readonly array $lineStarts;

	private int $lastLineIdx = -1;
	private int $lastByteOffset = -1;
	private int $lastColumn = 1;

	public function __construct(
		private readonly string $source,
		private readonly int $baseOffset = 0,
	) {
		$this->length = strlen($source);
		$rem = max(0, $this->length - $baseOffset);
		$this->isAscii = $rem === 0 || strcspn($source, self::HIGH_BYTES, $baseOffset, $rem) === $rem;
		$starts = [$baseOffset];
		$pos = $baseOffset;
		$len = $this->length;

		while ($pos < $len) {
			$pos += strcspn($source, "\n\r\f", $pos, $len - $pos);
			if ($pos >= $len) {
				break;
			}
			if ($source[$pos] === "\r" && $pos + 1 < $len && $source[$pos + 1] === "\n") {
				$pos += 2;
			} else {
				$pos++;
			}
			$starts[] = $pos;
		}

		$this->lineStarts = $starts;
	}

	public function span(int $startOffset, int $endOffset): CSSSourceSpan
	{
		$start = $startOffset < $this->baseOffset ? $this->baseOffset : ($startOffset > $this->length ? $this->length : $startOffset);
		$end = $endOffset < $start ? $start : ($endOffset > $this->length ? $this->length : $endOffset);
		[$startLine, $startColumn] = $this->resolve($start);
		[$endLine, $endColumn] = $this->resolve($end);

		return new CSSSourceSpan($start, $end, $startLine, $startColumn, $endLine, $endColumn);
	}

	/**
	 * @return array{0: int, 1: int}
	 */
	private function resolve(int $offset): array
	{
		$starts = $this->lineStarts;
		$low = 0;
		$high = count($starts) - 1;
		while ($low < $high) {
			$mid = ($low + $high + 1) >> 1;
			if ($starts[$mid] <= $offset) {
				$low = $mid;
			} else {
				$high = $mid - 1;
			}
		}

		$lineStart = $starts[$low];
		$byteDist = $offset - $lineStart;
		if ($byteDist <= 0) {
			$this->lastLineIdx = $low;
			$this->lastByteOffset = $offset;
			$this->lastColumn = 1;

			return [$low + 1, 1];
		}

		if ($this->isAscii) {
			return [$low + 1, $byteDist + 1];
		}

		if ($low === $this->lastLineIdx && $offset === $this->lastByteOffset) {
			return [$low + 1, $this->lastColumn];
		}

		if ($low === $this->lastLineIdx && $offset > $this->lastByteOffset && $this->lastByteOffset >= $lineStart) {
			$deltaStart = $this->lastByteOffset;
			$deltaLen = $offset - $deltaStart;
			$baseCol = $this->lastColumn;
		} else {
			$deltaStart = $lineStart;
			$deltaLen = $byteDist;
			$baseCol = 1;
		}

		if (strcspn($this->source, self::HIGH_BYTES, $deltaStart, $deltaLen) === $deltaLen) {
			$col = $baseCol + $deltaLen;
		} else {
			// Count UTF-8 code points while treating isolated invalid bytes gracefully as 1 column via mb_strlen.
			$col = $baseCol + mb_strlen(substr($this->source, $deltaStart, $deltaLen), 'UTF-8');
		}

		$this->lastLineIdx = $low;
		$this->lastByteOffset = $offset;
		$this->lastColumn = $col;

		return [$low + 1, $col];
	}
}
