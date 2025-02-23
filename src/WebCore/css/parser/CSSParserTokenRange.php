<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\WebCore\css\parser;

use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenizer;

use function Jimbo2150\PhpCssTypedOm\WTF\wtf\text\equalLettersIgnoringASCIICase;

class CSSParserTokenRange implements \Iterator
{
	private \Iterator $m_tokens;

	public function __construct(?\Iterator $span = null)
	{
		if (null === $span) {
			$this->m_tokens = new \EmptyIterator();
		} else {
			$this->m_tokens = $span;
		}
	}

	public function current(): mixed
	{
		return $this->m_tokens->current();
	}

	public function key(): mixed
	{
		return $this->m_tokens->key();
	}

	public function next(): void
	{
		$this->m_tokens->next();
	}

	public function rewind(): void
	{
		$this->m_tokens->rewind();
	}

	public function valid(): bool
	{
		return $this->m_tokens->valid();
	}

	public function rangeUntil(CSSParserTokenRange &$end): static
	{
		return $this->span()->first($end->begin() - $this->begin());
	}

	public function atEnd(): bool
	{
		return $this->m_tokens->empty();
	}

	public function begin(): CSSParserToken
	{
		return $this->m_tokens->begin();
	}

	public function end(): CSSParserToken
	{
		return $this->m_tokens->end();
	}

	public function size(): int
	{
		return $this->m_tokens->size();
	}

	public function peek(int $offset = 0): CSSParserToken
	{
		if ($offset < $this->m_tokens->size()) {
			return $this->m_tokens[$offset];
		}

		return static::eofToken();
	}

	public function consume(): CSSParserToken
	{
		if ($this->m_tokens->empty()) {
			return static::eofToken();
		}

		return WTF::consume($this->m_tokens);
	}

	public function consumeIncludingWhitespace(): CSSParserToken
	{
		$result = $this->consume();
		$this->consumeWhitespace();

		return $result;
	}

	// The returned range doesn't include the brackets
	public function consumeBlock(): static
	{
		assert(CSSParserToken::BlockStart == $this->peek()->getBlockType());
		$start = $this->m_tokens->subspan(1);
		$nestingLevel = 0;
		do {
			$token = $this->consume();
			if (CSSParserToken::BlockStart == $token->getBlockType()) {
				++$nestingLevel;
			} elseif (CSSParserToken::BlockEnd == $token->getBlockType()) {
				--$nestingLevel;
			}
		} while ($nestingLevel && !$this->m_tokens->empty());

		if ($nestingLevel) {
			return $start->first($this->m_tokens->data() - $start->data());
		} // Ended at EOF

		return $start->first($this->m_tokens->data() - $start->data() - 1);
	}

	public function consumeBlockCheckingForEditability(StyleSheetContents $styleSheet): static
	{
		assert(CSSParserToken::BlockStart == $this->peek()->getBlockType());
		$start = $this->m_tokens->subspan(1);
		$nestingLevel = 0;
		do {
			$token = $this->consume();
			if (CSSParserToken::BlockStart == $token->getBlockType()) {
				++$nestingLevel;
			} elseif (CSSParserToken::BlockEnd == $token->getBlockType()) {
				--$nestingLevel;
			}

			if (
				$styleSheet &&
				!$styleSheet->usesStyleBasedEditability() &&
				CSSParserTokenType::IdentToken == $token->type() &&
				equalLettersIgnoringASCIICase(
					$token->value(),
					'-webkit-user-modify'
				)
			) {
				$styleSheet->parserSetUsesStyleBasedEditability();
			}
		} while ($nestingLevel && !$this->m_tokens->empty());

		if ($nestingLevel) {
			return $start->first($this->m_tokens->data() - $start->data());
		} // Ended at EOF

		return $start->first($this->m_tokens->data() - $start->data() - 1);
	}

	public function consumeComponentValue(): void
	{
		// FIXME: This is going to do multiple passes over large sections of a stylesheet.
		// We should consider optimising this by precomputing where each block ends.
		$nestingLevel = 0;
		do {
			$token = $this->consume();
			if (CSSParserToken::BlockStart == $token->getBlockType()) {
				++$nestingLevel;
			} elseif (CSSParserToken::BlockEnd == $token->getBlockType()) {
				--$nestingLevel;
			}
		} while ($nestingLevel && !$this->m_tokens->empty());
	}

	public function consumeWhitespace(): void
	{
		$i = 0;
		for (;
			$i < $this->m_tokens->size() &&
				CSSTokenizer::isWhitespace($this->m_tokens[$i]->type());
			++$i
		) {
		}
		$this->skip($this->m_tokens, $i);
	}

	public function trimTrailingWhitespace(): void
	{
		$i = $this->m_tokens->size();
		for (; $i > 0 && CSSTokenizer::isWhitespace($this->m_tokens[$i - 1]->type()); --$i) {
		}
		$this->dropLast($this->m_tokens, $this->m_tokens->size() - $i);
	}

	public function consumeLast(): CSSParserToken
	{
		if ($this->atEnd()) {
			static::eofToken();
		}

		return WTF::consumeLast($this->m_tokens);
	}

	public function consumeAll(): static
	{
		return new static($this->m_tokens);
	}

	public function serialize(SerializationMode $mode = SerializationMode::Normal): string
	{
		$builder = '';
		for ($i = 0; $i < $this->m_tokens->size(); ++$i) {
			$this->m_tokens[$i]->serialize(
				$builder,
				($i + 1) == $this->m_tokens->size() ? null : $this->m_tokens[$i + 1],
				$mode
			);
		}

		return $builder;
	}

	public function __toString(): string
	{
		return $this->serialize();
	}

	public function __serialize(): array
	{
		return iterator_to_array($this->m_tokens);
	}

	public function span(): \Iterator
	{
		return $this->m_tokens;
	}

	public static function eofToken(): CSSParserToken
	{
		static $eofToken = CSSParserTokenType::EOFToken->get();

		return $eofToken;
	}
}
