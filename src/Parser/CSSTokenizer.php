<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use Jimbo2150\PhpCssTypedOm\WTF\icu\unicode\UChar;

use function Jimbo2150\PhpCssTypedOm\WTF\wtf\ASCIICType\isASCII;

final class CSSTokenizer
{
	protected CSSTokenizerInputStream $m_input;
	/** array<CodePoint, 128>  */
	protected const array codePoints = [
		'endOfFile',
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		'whitespace',
		'newline', // '\n'
		0,
		'newline', // '\f'
		'newline', // '\r'
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		0,
		'whitespace',
		0,
		'stringStart',
		'hash',
		'dollarSign',
		0,
		0,
		'stringStart',
		'leftParenthesis',
		'rightParenthesis',
		'asterisk',
		'plusOrFullStop',
		'comma',
		'hyphenMinus',
		'plusOrFullStop',
		'solidus',
		'asciiDigit',
		'asciiDigit',
		'asciiDigit',
		'asciiDigit',
		'asciiDigit',
		'asciiDigit',
		'asciiDigit',
		'asciiDigit',
		'asciiDigit',
		'asciiDigit',
		'colon',
		'semiColon',
		'lessThan',
		0,
		0,
		0,
		'commercialAt',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'leftBracket',
		'reverseSolidus',
		'rightBracket',
		'circumflexAccent',
		'nameStart',
		0,
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'nameStart',
		'leftBrace',
		'verticalLine',
		'rightBrace',
		'tilde',
		0,
	];
	protected const int codePointsNumber = 128;

	/** Vector<CSSParserTokenType, 8> */
	protected $m_blockStack;
	/** Vector<CSSParserToken, 32> */
	protected $m_tokens;
	// We only allocate strings when escapes are used.
	protected $m_stringPool;

	protected function __construct(
		string $string,
		?CSSParserObserverWrapper $wrapper = null,
	) {
		$this->m_input = new CSSTokenizerInputStream(
			$this->preprocessString(trim($string))
		);

		if (null === $this->m_input->peek(0)) {
			return; // String is empty
		}

		$offset = 0;
		while (true) {
			$token = $this->nextToken();
			if (EOFToken == $token->type()) {
				break;
			}
			if (CommentToken == $token->type()) {
				if ($wrapper) {
					$wrapper->addComment(
						$offset,
						$this->m_input->offset(),
						$this->m_tokens->size()
					);
				}
			} else {
				if (!$this->m_tokens->tryAppend($token)) {
					throw new \Exception('Cannot append token.');
				}
				if ($wrapper) {
					$wrapper->addToken($offset);
				}
			}
			$offset = $this->m_input->offset();
		}

		if ($wrapper) {
			$wrapper->addToken($offset);
			$wrapper->finalizeConstruction($this->m_tokens->begin());
		}
	}

	/**
	 * Replace null bytes and unpaired surrogates with the Unicode replacement
	 * 		character (U+FFFD).
	 *
	 * @return array|bool|string
	 */
	public static function preprocessString(string $string): string
	{
		$replaced = mb_convert_encoding(
			preg_replace('/\x00/', "\xEF\xBF\xBD", $string),
			'UTF-8',
			'UTF-8'
		);

		return $replaced;
	}

	public static function tryCreate(string $string, ?CSSParserObserverWrapper &$wrapper = null): self
	{
		return new self($string, $wrapper);
	}

	protected function nextToken()
	{
		// Unlike the HTMLTokenizer, the CSS Syntax spec is written
		// as a stateless, (fixed-size) look-ahead tokenizer.
		// We could move to the stateful model and instead create
		// states for all the "next 3 codepoints are X" cases.
		// State-machine tokenizers are easier to write to handle
		// incremental tokenization of partial sources.
		// However, for now we follow the spec exactly.
		$cc = $this->consume();
		$codePointFunc = 0;

		if (isASCII($cc)) {
			assert($cc < self::codePointsNumber);
			$codePointFunc = static::codePoints[(string) $cc];
		} else {
			$codePointFunc = 'nameStart';
		}

		if (method_exists($this, $codePointFunc)) {
			return self::{$codePointFunc}($cc);
		}

		return CSSParserToken(DelimiterToken, $cc);
	}

	protected function consume(): UChar
	{
		$current = $this->m_input->nextInputChar();
		$this->m_input->advance();

		return $current;
	}

	protected function nameStart(UChar $cc): CSSParserToken
	{
		$this->reconsume($cc);

		return $this->consumeIdentLikeToken();
	}

	protected function reconsume(UChar $c): void
	{
		$this->m_input->pushBack($c);
	}

	protected function consumeIdentLikeToken(): CSSParserToken
	{
		$name = $this->consumeName();
		if ($this->consumeIfNext('(')) {
			if ($this->equalLettersIgnoringASCIICase($name, 'url')) {
				// The spec is slightly different so as to avoid dropping whitespace
				// tokens, but they wouldn't be used and this is easier.
				$this->m_input->advanceUntilNonWhitespace();
				$next = $this->m_input->peek(0);
				if ('"' != $next && '\'' != $next) {
					return $this->consumeURLToken();
				}
			}

			return $this->blockStart(LeftParenthesisToken, FunctionToken, $name);
		}

		return new CSSParserToken(IdentToken, $name);
	}
}
