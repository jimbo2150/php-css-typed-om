<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use Jimbo2150\PhpCssTypedOm\WebCore\css\parser\BlockType;
use Jimbo2150\PhpCssTypedOm\WebCore\css\parser\CSSParserObserverWrapper;
use Jimbo2150\PhpCssTypedOm\WebCore\css\parser\CSSParserToken;
use Jimbo2150\PhpCssTypedOm\WebCore\css\parser\CSSParserTokenRange;
use Jimbo2150\PhpCssTypedOm\WebCore\css\parser\CSSParserTokenType;
use Jimbo2150\PhpCssTypedOm\WebCore\css\parser\HashTokenType;
use Jimbo2150\PhpCssTypedOm\WebCore\css\parser\NumericSign;
use Jimbo2150\PhpCssTypedOm\WebCore\css\parser\NumericValueType;
use Jimbo2150\PhpCssTypedOm\WTF\icu\unicode\UChar;

use function Jimbo2150\PhpCssTypedOm\WTF\wtf\ASCIICType\isASCII;
use function Jimbo2150\PhpCssTypedOm\WTF\wtf\ASCIICType\isASCIIDigit;
use function Jimbo2150\PhpCssTypedOm\WTF\wtf\ASCIICType\isASCIIHexDigit;
use function Jimbo2150\PhpCssTypedOm\WTF\wtf\text\equalLettersIgnoringASCIICase;

use const Jimbo2150\PhpCssTypedOm\WTF\icu\unicode\CharacterNames\REPLACEMENT_CHARACTER;

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
			if (CSSParserTokenType::EOFToken == $token->type()) {
				break;
			}
			if (CSSParserTokenType::CommentToken == $token->type()) {
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

	public function tokenRange(): CSSParserTokenRange
	{
		return $this->m_tokens;
	}

	public function tokenCount(): int
	{
		return $this->m_tokens->size();
	}

	public static function isWhitespace(CSSParserTokenType $type): bool
	{
		return CSSParserTokenType::NonNewlineWhitespaceToken == $type ||
			CSSParserTokenType::NewlineToken == $type;
	}

	public static function isNewline(UChar $cc): bool
	{
		// We check \r and \f here, since we have no preprocessing stage
		return '\r' == $cc || '\n' == $cc || '\f' == $cc;
	}

	public static function newline(): CSSParserToken
	{
		return new CSSParserToken(CSSParserTokenType::NewlineToken);
	}

	public static function twoCharsAreValidEscape(UChar $first, UChar $second): bool
	{
		return '\\' == $first && !self::isNewline($second);
	}

	public function whitespace(): CSSParserToken
	{
		$startOffset = $this->m_input->offset();
		$this->m_input->advanceUntilNewlineOrNonWhitespace();
		$whitespaceCount = 1 + ($this->m_input->offset() - $startOffset);

		return new CSSParserToken($whitespaceCount);
	}

	public function blockStart(
		CSSParserTokenType $type,
		?CSSParserTokenType $blockType = null,
		?StringView $name = null,
	): CSSParserToken {
		$this->m_blockStack->append($type);

		return new CSSParserToken($type, $blockType ?? BlockType::BlockStart, $name);
	}

	public function blockEnd(
		CSSParserTokenType $type,
		CSSParserTokenType $startType,
	): CSSParserToken {
		if (!$this->m_blockStack->isEmpty() && $this->m_blockStack->last() == $startType) {
			$this->m_blockStack->removeLast();

			return CSSParserToken($type, BlockType::BlockEnd);
		}

		return new CSSParserToken($type);
	}

	public function leftParenthesis(): CSSParserToken
	{
		return $this->blockStart(CSSParserTokenType::LeftParenthesisToken);
	}

	public function rightParenthesis(): CSSParserToken
	{
		return $this->blockEnd(
			CSSParserTokenType::RightParenthesisToken,
			CSSParserTokenType::LeftParenthesisToken
		);
	}

	public function leftBracket(): CSSParserToken
	{
		return $this->blockStart(CSSParserTokenType::LeftBracketToken);
	}

	public function rightBracket(): CSSParserToken
	{
		return $this->blockEnd(
			CSSParserTokenType::RightBracketToken,
			CSSParserTokenType::LeftBracketToken
		);
	}

	public function leftBrace(): CSSParserToken
	{
		return $this->blockStart(CSSParserTokenType::LeftBraceToken);
	}

	public function rightBrace(): CSSParserToken
	{
		return $this->blockEnd(
			CSSParserTokenType::RightBraceToken,
			CSSParserTokenType::LeftBraceToken
		);
	}

	public function plusOrFullStop(UChar $cc): CSSParserToken
	{
		if ($this->nextCharsAreNumber($cc)) {
			$this->reconsume($cc);

			return $this->consumeNumericToken();
		}

		return new CSSParserToken(CSSParserTokenType::DelimiterToken, $cc);
	}

	public function asterisk(UChar $cc): CSSParserToken
	{
		assert('*' == $cc);
		if ($this->consumeIfNext('=')) {
			return new CSSParserToken(CSSParserTokenType::SubstringMatchToken);
		}

		return new CSSParserToken(CSSParserTokenType::DelimiterToken, '*');
	}

	public function lessThan(UChar $cc): CSSParserToken
	{
		assert($cc, '<' == $cc);
		if (
			'!' == $this->m_input->peek(0) &&
			'-' == $this->m_input->peek(1) &&
			'-' == $this->m_input->peek(2)
		) {
			$this->m_input->advance(3);

			return new CSSParserToken(CSSParserTokenType::CDOToken);
		}

		return new CSSParserToken(CSSParserTokenType::DelimiterToken, '<');
	}

	public function comma(): CSSParserToken
	{
		return new CSSParserToken(CSSParserTokenType::CommaToken);
	}

	public function hyphenMinus(UChar $cc): CSSParserToken
	{
		if ($this->nextCharsAreNumber($cc)) {
			$this->reconsume($cc);

			return $this->consumeNumericToken();
		}
		if ('-' == $this->m_input->peek(0) && '>' == $this->m_input->peek(1)) {
			$this->m_input->advance(2);

			return CSSParserToken(CSSParserTokenType::CDCToken);
		}
		if ($this->nextCharsAreIdentifier($cc)) {
			$this->reconsume($cc);

			return $this->consumeIdentLikeToken();
		}

		return new CSSParserToken(CSSParserTokenType::DelimiterToken, $cc);
	}

	public function solidus(UChar $cc): CSSParserToken
	{
		if ($this->consumeIfNext(new UChar('*'))) {
			// These get ignored, but we need a value to return.
			$this->consumeUntilCommentEndFound();

			return new CSSParserToken(CSSParserTokenType::CommentToken);
		}

		return new CSSParserToken(CSSParserTokenType::DelimiterToken, $cc);
	}

	public function colon(): CSSParserToken
	{
		return new CSSParserToken(CSSParserTokenType::ColonToken);
	}

	public function semiColon(): CSSParserToken
	{
		return new CSSParserToken(CSSParserTokenType::SemicolonToken);
	}

	public function hash(UChar $cc): CSSParserToken
	{
		$nextChar = $this->m_input->peek(0);
		if (
			isNameCodePoint($nextChar) ||
			$this->twoCharsAreValidEscape($nextChar, $this->m_input->peek(1))
		) {
			$type = $this->nextCharsAreIdentifier() ?
				HashTokenType::HashTokenId :
				HashTokenType::HashTokenUnrestricted;

			return new CSSParserToken($type, $this->consumeName());
		}

		return new CSSParserToken(CSSParserTokenType::DelimiterToken, $cc);
	}

	public function circumflexAccent(UChar $cc): CSSParserToken
	{
		assert('^' == $cc);
		if ($this->consumeIfNext(new UChar('='))) {
			return new CSSParserToken(CSSParserTokenType::PrefixMatchToken);
		}

		return new CSSParserToken(CSSParserTokenType::DelimiterToken, '^');
	}

	public function dollarSign(UChar $cc): CSSParserToken
	{
		assert('$' == $cc);
		if ($this->consumeIfNext('=')) {
			return new CSSParserToken(CSSParserTokenType::SuffixMatchToken);
		}

		return new CSSParserToken(CSSParserTokenType::DelimiterToken, '$');
	}

	public function verticalLine(UChar $cc): CSSParserToken
	{
		assert('|' == $cc);
		if ($this->consumeIfNext('=')) {
			return new CSSParserToken(CSSParserTokenType::DashMatchToken);
		}
		if ($this->consumeIfNext('|')) {
			return new CSSParserToken(CSSParserTokenType::ColumnToken);
		}

		return new CSSParserToken(DelimiterToken, '|');
	}

	public function tilde(UChar $cc): CSSParserToken
	{
		assert('~' == $cc);
		if ($this->consumeIfNext('=')) {
			return new CSSParserToken(CSSParserTokenType::IncludeMatchToken);
		}

		return new CSSParserToken(CSSParserTokenType::DelimiterToken, '~');
	}

	public function commercialAt(UChar $cc): CSSParserToken
	{
		assert('@' == $cc);
		if ($this->nextCharsAreIdentifier()) {
			return new CSSParserToken(CSSParserTokenType::AtKeywordToken, $this->consumeName());
		}

		return new CSSParserToken(CSSParserTokenType::DelimiterToken, '@');
	}

	public function reverseSolidus(UChar $cc): CSSParserToken
	{
		if ($this->twoCharsAreValidEscape($cc, $this->m_input->peek(0))) {
			$this->reconsume($cc);

			return $this->consumeIdentLikeToken();
		}

		return new CSSParserToken(CSSParserTokenType::DelimiterToken, $cc);
	}

	public function asciiDigit(UChar $cc): CSSParserToken
	{
		$this->reconsume($cc);

		return $this->consumeNumericToken();
	}

	public function nameStart(UChar $cc): CSSParserToken
	{
		$this->reconsume($cc);

		return $this->consumeIdentLikeToken();
	}

	public function stringStart(UChar $cc): CSSParserToken
	{
		return $this->consumeStringTokenUntil($cc);
	}

	public function endOfFile(): CSSParserToken
	{
		return new CSSParserToken(CSSParserTokenType::EOFToken);
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

		return CSSParserToken(CSSParserTokenType::DelimiterToken, $cc);
	}

	public function consumeNumber(): CSSParserToken
	{
		assert($this->nextCharsAreNumber());

		$startOffset = $this->m_input->offset();

		$type = NumericValueType::IntegerValueType;
		$sign = NumericSign::NoSign;
		$numberLength = 0;

		$next = $this->m_input->peek(0);
		if ('+' == $next) {
			++$numberLength;
			$sign = NumericSign::PlusSign;
		} elseif ('-' == $next) {
			++$numberLength;
			$sign = NumericSign::MinusSign;
		}

		$numberLength = $this->m_input->skipWhilePredicate($numberLength);
		$next = $this->m_input->peek($numberLength);
		if ('.' == $next && isASCIIDigit($this->m_input->peek($numberLength + 1))) {
			$type = NumericValueType::NumberValueType;
			$numberLength = $this->m_input->skipWhilePredicate($numberLength + 2);
			$next = $this->m_input->peek($numberLength);
		}

		if ('E' == $next || 'e' == $next) {
			$next = $this->m_input->peek($numberLength + 1);
			if (isASCIIDigit($next)) {
				$type = NumericValueType::NumberValueType;
				$numberLength = $this->m_input->skipWhilePredicate($numberLength + 1);
			} elseif (
				('+' == $next || '-' == $next) &&
				isASCIIDigit($this->m_input->peek($numberLength + 2))
			) {
				$type = NumericValueType::NumberValueType;
				$numberLength = $this->m_input->skipWhilePredicate($numberLength + 3);
			}
		}

		$value = $this->m_input->getDouble(0, $numberLength);
		$this->m_input->advance($numberLength);

		return new CSSParserToken(
			$value,
			$type,
			$sign,
			$this->m_input->rangeAt($startOffset, $this->m_input->offset() - $startOffset)
		);
	}

	public function consumeNumericToken(): CSSParserToken
	{
		$token = $this->consumeNumber();
		if ($this->nextCharsAreIdentifier()) {
			$token->convertToDimensionWithUnit($this->consumeName());
		} elseif (consumeIfNext('%')) {
			$token->convertToPercentage();
		}

		return $token;
	}

	public function consumeIdentLikeToken(): CSSParserToken
	{
		$name = $this->consumeName();
		if ($this->consumeIfNext('(')) {
			if (equalLettersIgnoringASCIICase($name, 'url')) {
				// The spec is slightly different so as to avoid dropping whitespace
				// tokens, but they wouldn't be used and this is easier.
				$this->m_input->advanceUntilNonWhitespace();
				$next = $this->m_input->peek(0);
				if ('"' != $next && '\'' != $next) {
					return $this->consumeURLToken();
				}
			}

			return $this->blockStart(
				CSSParserTokenType::LeftParenthesisToken,
				CSSParserTokenType::FunctionToken,
				$name
			);
		}

		return new CSSParserToken(IdentToken, $name);
	}

	public function consumeStringTokenUntil(UChar $endingCodePoint): CSSParserToken
	{
		// Strings without escapes get handled without allocations
		for ($size = 0;; ++$size) {
			$cc = $this->m_input->peek($size);
			if ($cc == $endingCodePoint) {
				$startOffset = $this->m_input->offset();
				$this->m_input->advance($size + 1);

				return new CSSParserToken(
					StringToken,
					$this->m_input->rangeAt($startOffset, $size)
				);
			}
			if (isNewline(cc)) {
				$this->m_input->advance($size);

				return CSSParserToken(BadStringToken);
			}
			if (kEndOfFileMarker == $cc || '\\' == $cc) {
				break;
			}
		}

		$output = '';
		while (true) {
			$cc = $this->consume();
			if ($cc == $endingCodePoint || kEndOfFileMarker == $cc) {
				return new CSSParserToken(StringToken, $this->registerString((string) $output));
			}
			if (isNewline($cc)) {
				$this->reconsume($cc);

				return new CSSParserToken(BadStringToken);
			}
			if ('\\' == $cc) {
				if (kEndOfFileMarker == $this->m_input->nextInputChar()) {
					continue;
				}
				if (isNewline($this->m_input->peek(0))) {
					$this->consumeSingleWhitespaceIfNext();
				} // This handles \r\n for us
				else {
					$output .= $this->consumeEscape();
				}
			} else {
				$output .= $cc;
			}
		}
	}

	public static function isNonPrintableCodePoint(UChar $cc): bool
	{
		return $cc <= "\x8" || "\xb" == $cc || ($cc >= "\xe" && $cc <= "\x1f") || "\x7f" == $cc;
	}

	public function consumeURLToken(): CSSParserToken
	{
		$this->m_input->advanceUntilNonWhitespace();

		// URL tokens without escapes get handled without allocations
		for ($size = 0;; ++$size) {
			$cc = $this->m_input->peek($size);
			if (')' == $cc) {
				$startOffset = $this->m_input->offset();
				$this->m_input->advance($size + 1);

				return new CSSParserToken(UrlToken, $this->m_input->rangeAt($startOffset, $size));
			}
			if (
				$cc <= ' ' ||
				'\\' == $cc ||
				'"' == $cc ||
				'\'' == $cc ||
				'(' == $cc ||
				'\x7f' == $cc
			) {
				break;
			}
		}

		$result = '';
		while (true) {
			$cc = $this->consume();
			if (')' == $cc || kEndOfFileMarker == $cc) {
				return new CSSParserToken(UrlToken, $this->registerString($result));
			}

			if (isASCIIWhitespace(cc)) {
				$this->m_input->advanceUntilNonWhitespace();
				if (
					$this->consumeIfNext(')') ||
					kEndOfFileMarker == $this->m_input->nextInputChar()
				) {
					return new CSSParserToken(UrlToken, $this->registerString($result));
				}
				break;
			}

			if ('"' == $cc || '\'' == $cc || '(' == $cc || isNonPrintableCodePoint($cc)) {
				break;
			}

			if ('\\' == $cc) {
				if (twoCharsAreValidEscape($cc, $this->m_input->peek(0))) {
					$result .= $this->consumeEscape();
					continue;
				}
				break;
			}

			$result .= $cc;
		}

		$this->consumeBadUrlRemnants();

		return new CSSParserToken(BadUrlToken);
	}

	public function consumeBadUrlRemnants(): void
	{
		while (true) {
			$cc = $this->consume();
			if (')' == $cc || kEndOfFileMarker == $cc) {
				return;
			}
			if (twoCharsAreValidEscape($cc, $this->m_input->peek(0))) {
				$this->consumeEscape();
			}
		}
	}

	public function consumeSingleWhitespaceIfNext(): void
	{
		// We check for \r\n and ASCII whitespace since we don't do preprocessing
		$next = $this->m_input->peek(0);
		if ('\r' == $next && '\n' == $this->m_input->peek(1)) {
			$this->m_input->advance(2);
		} elseif (isASCIIWhitespace($next)) {
			$this->m_input->advance();
		}
	}

	public function consumeUntilCommentEndFound(): void
	{
		$c = $this->consume();
		while (true) {
			if (kEndOfFileMarker == $c) {
				return;
			}
			if ('*' != $c) {
				$c = $this->consume();
				continue;
			}
			$c = $this->consume();
			if ('/' == $c) {
				return;
			}
		}
	}

	public function consumeIfNext(UChar $character): bool
	{
		assert(null !== $character);
		if ($this->m_input->peek(0) == $character) {
			$this->m_input->advance();

			return true;
		}

		return false;
	}

	public function consumeName(): string
	{
		// Names without escapes get handled without allocations
		for ($size = 0;; ++$size) {
			$cc = $this->m_input->peek($size);
			if (isNameCodePoint($cc)) {
				continue;
			}
			// peek will return NUL when we hit the end of the
			// input. In that case we want to still use the rangeAt() fast path
			// below.
			if (
				kEndOfFileMarker == $cc &&
				$this->m_input->offset() + $size < $this->m_input->length()
			) {
				break;
			}
			if ('\\' == $cc) {
				break;
			}
			$startOffset = $this->m_input->offset();
			$this->m_input->advance($size);

			return $this->m_input->rangeAt($startOffset, $size);
		}

		$result = '';
		while (true) {
			$cc = $this->consume();
			if (isNameCodePoint($cc)) {
				$result .= $cc;
				continue;
			}
			if (twoCharsAreValidEscape($cc, $this->m_input->peek(0))) {
				$result .= $this->consumeEscape();
				continue;
			}
			$this->reconsume($cc);

			// '�'
			return registerString($result);
		}
	}

	// http://dev.w3.org/csswg/css-syntax/#consume-an-escaped-code-point
	public function consumeEscape(): string
	{
		$cc = $this->consume();
		assert(!$this->isNewline($cc));
		if (isASCIIHexDigit($cc)) {
			$consumedHexDigits = 1;
			$hexChars = '';
			$hexChars .= $cc;
			while ($consumedHexDigits < 6 && isASCIIHexDigit($this->m_input->peek(0))) {
				$cc = $this->consume();
				$hexChars .= $cc;
				++$consumedHexDigits;
			}
			$this->consumeSingleWhitespaceIfNext();
			$codePoint = intval($hexChars, 16);
			if (
				!$codePoint ||
				\IntlChar::CHAR_CATEGORY_SURROGATE == \IntlChar::charType($codePoint) ||
				$codePoint > \IntlChar::CODEPOINT_MAX
			) {
				return REPLACEMENT_CHARACTER;
			}

			return \IntlChar::chr($codePoint);
		}

		return $cc;
	}

	public function nextTwoCharsAreValidEscape(): bool
	{
		return $this->twoCharsAreValidEscape($this->m_input->peek(0), $this->m_input->peek(1));
	}

	// http://www.w3.org/TR/css3-syntax/#starts-with-a-number
	public function nextCharsAreNumber(?UChar $first = null): bool
	{
		if (null === $first) {
			$first = $this->consume();
			$areNumber = $this->nextCharsAreNumber($first);
			$this->reconsume($first);

			return $areNumber;
		}

		$second = $this->m_input->peek(0);
		if (isASCIIDigit($first)) {
			return true;
		}
		if ('+' == $first || '-' == $first) {
			return
				isASCIIDigit($second) ||
				('.' == $second && isASCIIDigit($this->m_input->peek(1)))
			;
		}
		if ('.' == $first) {
			return isASCIIDigit($second);
		}

		return false;
	}

	// http://dev.w3.org/csswg/css-syntax/#would-start-an-identifier
	public function nextCharsAreIdentifier(?UChar $first = null): bool
	{
		if (null === $first) {
			$first = $this->consume();
			$areIdentifier = $this->nextCharsAreIdentifier($first);
			$this->reconsume($first);

			return $areIdentifier;
		}

		$second = $this->m_input->peek(0);
		if (isNameStartCodePoint($first) || $this->twoCharsAreValidEscape($first, $second)) {
			return true;
		}

		if ('-' == $first) {
			return isNameStartCodePoint($second) ||
				'-' == $second ||
				$this->nextTwoCharsAreValidEscape();
		}

		return false;
	}

	public function registerString(string $string): string
	{
		$this->m_stringPool[] = $string;

		return $string;
	}

	protected function consume(): UChar
	{
		$current = $this->m_input->nextInputChar();
		$this->m_input->advance();

		return $current;
	}

	protected function reconsume(UChar $c): void
	{
		$this->m_input->pushBack($c);
	}
}
