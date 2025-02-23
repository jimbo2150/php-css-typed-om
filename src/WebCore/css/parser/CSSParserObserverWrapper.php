<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WebCore\css\parser;

class CSSParserObserverWrapper
{
	private CSSParserObserver $m_observer;
	/** @var array<int,int> */
	private array $m_tokenOffsets;
	private CSSParserToken $m_firstParserToken;

	/** @var array<CommentPosition> */
	private array $m_commentOffsets;
	private int $m_commentIndex = 0;

	// No instances allowed
	private function __construct(CSSParserObserver &$observer)
	{
		$this->m_observer = $observer;
	}

	public function startOffset(CSSParserTokenRange &$range): int
	{
		return $this->m_tokenOffsets[$range->begin() - $this->m_firstParserToken];
	}

	public function previousTokenStartOffset(CSSParserTokenRange &$range): int
	{
		if ($range->begin() == $this->m_firstParserToken) {
			return 0;
		}

		return $this->m_tokenOffsets[$range->begin() - $this->m_firstParserToken - 1];
	}

	public function endOffset(CSSParserTokenRange &$range): int
	{
		return $this->m_tokenOffsets[$range->end() - $this->m_firstParserToken];
	}

	public function skipCommentsBefore(
		CSSParserTokenRange &$range,
		bool $leaveDirectlyBefore,
	): void {
		$startIndex = $range->begin() - $this->m_firstParserToken;
		if (!$leaveDirectlyBefore) {
			++$startIndex;
		}
		while (
			$this->m_commentIndex < $this->m_commentOffsets->size() &&
			$this->m_commentOffsets[$this->m_commentIndex]->tokensBefore < $startIndex
		) {
			++$this->m_commentIndex;
		}
	}

	public function yieldCommentsBefore(CSSParserTokenRange &$range): void
	{
		$startIndex = $range->begin() - $this->m_firstParserToken;
		for (; $this->m_commentIndex < $this->m_commentOffsets->size(); ++$m_commentIndex) {
			$commentOffset = $this->m_commentOffsets[$this->m_commentIndex];
			if ($commentOffset->tokensBefore > $startIndex) {
				break;
			}
			$this->m_observer->observeComment(
				$commentOffset->startOffset,
				$commentOffset->endOffset
			);
		}
	}

	public function addComment(int $startOffset, int $endOffset, int $tokensBefore): void
	{
		list($position) = [$startOffset, $endOffset, $tokensBefore];
		array_push($this->m_commentOffsets, $position);
	}
}
