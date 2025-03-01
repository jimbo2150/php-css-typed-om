<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WebCore\css\parser;

use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenizer;
use Jimbo2150\PhpCssTypedOm\WTF\icu\unicode\UChar;
use Jimbo2150\PhpCssTypedOm\WTF\wtf\text\LChar;

use function Jimbo2150\PhpCssTypedOm\WTF\wtf\ASCIICType\isASCIIWhitespace;

class CSSTokenizerInputStream
{
	private int $m_offset;
	private int $m_stringLength;
	private string $m_string;

	public function __construct(string $input)
	{
		$this->m_stringLength = strlen($input);
		$this->m_string = $input;
	}

	public function nextInputChar(): UChar
	{
		if ($this->m_offset >= $this->m_stringLength) {
			return kEndOfFileMarker;
		}

		return new UChar($this->m_string[$this->m_offset]);
	}

	// Gets the char at lookaheadOffset from the current stream position. Will
	// return NUL (kEndOfFileMarker) if the stream position is at the end.
	public function peek(int $lookaheadOffset): UChar
	{
		assert($lookaheadOffset >= 0);
		if (($this->m_offset + $lookaheadOffset) >= $this->m_stringLength) {
			return kEndOfFileMarker;
		}

		return new UChar($this->m_string[$this->m_offset + $lookaheadOffset]);
	}

	public function advance(int $offset = 1): void
	{
		$this->m_offset += $offset;
	}

	public function pushBack(UChar $cc): void
	{
		--$this->m_offset;
		assert($cc, $this->nextInputChar() == $cc);
	}

	public function getDouble(int $start, int $end): float
	{
		assert($start <= $end && (($this->m_offset + $end) <= $this->m_stringLength));
		$isResultOK = false;
		$result = 0.0;
		if ($start < $end) {
			if ($this->m_string->is8Bit()) {
				$result = charactersToDouble(
					$this->m_string->span8()->subspan($this->m_offset + $start, $end - $start),
					$isResultOK
				);
			} else {
				$result = charactersToDouble(
					$this->m_string->span16()->subspan($this->m_offset + $start, $end - $start),
					$isResultOK
				);
			}
		}

		// FIXME: It looks like callers ensure we have a valid number
		return $isResultOK ? $result : 0.0;
	}

	/**
	 * @template template<bool characterPredicate(UChar)>
	 */
	public function skipWhilePredicate(int $offset): int
	{
		assert($offset >= 0);
		if ($this->m_string->is8Bit()) {
			$characters8 = $this->m_string->span8();
			while (
				($this->m_offset + $offset) < $this->m_stringLength &&
				$this->characterPredicate($characters8[$this->m_offset + $offset])
			) {
				++$offset;
			}
		} else {
			$characters16 = $this->m_string->span16();
			while (
				($this->m_offset + $offset) < $this->m_stringLength &&
				$this->characterPredicate($characters16[$this->m_offset + $offset])
			) {
				++$offset;
			}
		}

		return $offset;
	}

	public function advanceUntilNonWhitespace(): void
	{
		// Using ASCII whitespace here rather than CSS space since we don't do preprocessing
		$advance = function (string $characters): void {
			while (
				$this->m_offset < $this->m_stringLength &&
				isASCIIWhitespace(
					new LChar($characters[$this->m_offset])
				)
			) {
				++$this->m_offset;
			}
		};

		if ($this->m_string->is8Bit()) {
			$advance($this->m_string->span8());
		} else {
			$advance($this->m_string->span16());
		}
	}

	public function advanceUntilNewlineOrNonWhitespace(): void
	{
		$advance = function (string $characters) {
			while (
				$this->m_offset < $this->m_stringLength &&
				isASCIIWhitespace(
					new LChar($characters[$this->m_offset])
				)
			) {
				if (
					CSSTokenizer::isNewline(
						new UChar($characters[$this->m_offset])
					)
				) {
					return;
				}
				++$this->m_offset;
			}
		};

		if ($this->m_string->is8Bit()) {
			$advance($this->m_string->span8());
		} else {
			$advance($this->m_string->span16());
		}
	}

	public function length(): int
	{
		return $this->m_stringLength;
	}

	public function offset(): int
	{
		return min($this->m_offset, $this->m_stringLength);
	}

	public function rangeAt(int $start, int $length): StringView
	{
		assert($start + $length <= $this->m_stringLength);

		// FIXME: Should make a constructor on StringView for this.
		return StringView($this->m_string->get())->substring($start, $length);
	}
}
