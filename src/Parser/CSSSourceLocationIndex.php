<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSSourceSpan;

/**
 * @internal Precomputed newline index for mapping CSS byte offsets to 1-based line and UTF-8 column positions.
 */
final class CSSSourceLocationIndex
{
	private readonly int $length;

	/** @var list<int> */
	private readonly array $lineStarts;

	public function __construct(
		private readonly string $source,
		private readonly int $baseOffset = 0,
	) {
		$this->length = strlen($source);
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
			return [$low + 1, 1];
		}

		$slice = substr($this->source, $lineStart, $byteDist);
		if (preg_match('/[\x80-\xFF]/', $slice) !== 1) {
			return [$low + 1, $byteDist + 1];
		}

		// Count UTF-8 code points while treating isolated invalid bytes gracefully as 1 column via mb_strlen.
		$columns = mb_strlen($slice, 'UTF-8');

		return [$low + 1, $columns + 1];
	}
}
