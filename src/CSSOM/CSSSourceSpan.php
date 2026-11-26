<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\CSSOM;

use Stringable;

/**
 * Immutable 1-based line/column and 0-based byte-offset span within parsed CSS source.
 */
final readonly class CSSSourceSpan implements Stringable
{
	public function __construct(
		public int $startOffset,
		public int $endOffset,
		public int $startLine,
		public int $startColumn,
		public int $endLine,
		public int $endColumn,
	) {
	}

	/**
	 * @return array{
	 *     startOffset: int,
	 *     endOffset: int,
	 *     startLine: int,
	 *     startColumn: int,
	 *     endLine: int,
	 *     endColumn: int,
	 * }
	 */
	public function toArray(): array
	{
		return [
			'startOffset' => $this->startOffset,
			'endOffset' => $this->endOffset,
			'startLine' => $this->startLine,
			'startColumn' => $this->startColumn,
			'endLine' => $this->endLine,
			'endColumn' => $this->endColumn,
		];
	}

	public function __toString(): string
	{
		return $this->startLine . ':' . $this->startColumn . '-' . $this->endLine . ':' . $this->endColumn;
	}
}
