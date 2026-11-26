<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Parser;

use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Traversable;

/**
 * Iterative, single-pass CSS Syntax Level 3 §4.2–§4.3 tokenizer.
 *
 * Public usage always runs CSS Syntax L3 §3.3 preprocessing via `CSSInputPreprocessor`
 * and yields immutable `CSSToken` value objects. Internal parser entry points may
 * use `forParserInternal()` + `advanceInternal()` to read scalar cursor fields with
 * zero per-token object allocation.
 *
 * @implements IteratorAggregate<int, CSSToken>
 * @see https://drafts.csswg.org/css-syntax-3/#tokenization
 */
final class CSSTokenizer implements IteratorAggregate
{
	public const int DEFAULT_MAX_TOKENS = 1_000_000;

	private const string HEX_DIGITS = '0123456789abcdefABCDEF';
	private const string ASCII_DIGITS = '0123456789';
	private const string ASCII_IDENT_BYTES = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789-_';
	private const string ASCII_WHITESPACE = " \t\n\r\f";
	private const string CONTROL_BYTES = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0E\x0F\x10\x11\x12\x13\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F\x7F";
	private const string URL_SPECIAL_BYTES = " \t\n\r\f\"'()\\" . self::CONTROL_BYTES;
	private const string REPLACEMENT_CHAR = "\u{FFFD}";

	private string $source;
	private int $offset;
	private int $end;
	private bool $preprocessed;
	private bool $checkControlBytes;
	private bool $parserMode;
	private bool $decodePayload = true;
	private ?int $maxErrors;
	private bool $errorsTruncated = false;

	/** @var list<array{message: string, offset: int}> */
	private array $parseErrors = [];

	private ?CSSToken $peekedToken = null;
	private bool $hasDirtyTokenState = false;

	// Scalar cursor state for zero-allocation @internal parsing
	/** @internal */
	public CSSTokenType $tokenType = CSSTokenType::Eof;
	/** @internal */
	public int $tokenStart = 0;
	/** @internal */
	public int $tokenEnd = 0;
	/** @internal */
	public ?string $tokenFlag = null;
	/** @internal */
	public ?float $tokenNumericValue = null;
	/** @internal */
	public ?string $tokenRepr = null;
	/** @internal */
	public string $tokenValue = '';
	/** @internal */
	public ?string $tokenUnit = null;
	/** @internal */
	public int $tokenUnitStart = 0;
	/** @internal */
	public int $tokenUnitEnd = 0;
	/** @internal */
	public bool $tokenHasEscapes = false;
	/** @internal */
	public bool $tokenHadLeadingComment = false;
	/** @internal */
	public bool $tokenClosedAtEof = false;
	/** @internal */
	public string $tokenClosedQuote = '';
	/** @internal */
	public bool $tokenTrailingBackslashAtEof = false;
	/** @internal */
	public ?int $tokenUnclosedCommentOffset = null;
	/**
	 * @internal
	 * @var list<int>
	 */
	public array $commentControlErrorOffsets = [];
	/** @internal */
	public ?int $tokenControlByteOffset = null;
	/** @internal */
	public ?string $tokenParseErrorMessage = null;
	/** @internal */
	public ?int $tokenParseErrorOffset = null;

	/**
	 * @throws CSSParserLimitException When `$source` exceeds `$options->maxBytes` (raw or preprocessed)
	 */
	public function __construct(string $source, ?CSSParserOptions $options = null)
	{
		$options ??= CSSParserOptions::default();
		$this->source = CSSInputPreprocessor::preprocess($source, $options);
		$this->offset = 0;
		$this->end = strlen($this->source);
		$this->preprocessed = true;
		$this->checkControlBytes = false;
		$this->parserMode = false;
		$this->maxErrors = $options->maxErrors;
	}

	/**
	 * @internal Creates a zero-allocation cursor tokenizer over `$source[$startOffset..$endOffset)`.
	 */
	public static function forParserInternal(
		string $source,
		CSSParserOptions $options,
		bool $alreadyPreprocessed,
		bool $checkControlBytes,
		int $startOffset = 0,
		?int $endOffset = null,
		bool $decodeMetadata = false,
	): self {
		static $template = null;
		$template ??= (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
		$instance = clone $template;
		$len = strlen($source);
		$instance->source = $source;
		$instance->offset = max(0, $startOffset);
		$instance->end = $endOffset === null ? $len : min($len, max(0, $endOffset));
		$instance->preprocessed = $alreadyPreprocessed;
		$instance->checkControlBytes = $checkControlBytes;
		$instance->parserMode = true;
		$instance->decodePayload = $decodeMetadata;
		$instance->maxErrors = $options->maxErrors;

		return $instance;
	}

	/**
	 * Tokenizes `$source` eagerly into a list of `CSSToken` objects, bounded by `$maxTokens`.
	 *
	 * @return list<CSSToken>
	 * @throws InvalidArgumentException When `$maxTokens` is less than 1
	 * @throws CSSParserLimitException When `$source` exceeds `maxBytes` or token count exceeds `$maxTokens`
	 */
	public static function tokenize(
		string $source,
		?CSSParserOptions $options = null,
		bool $includeEof = false,
		?int $maxTokens = self::DEFAULT_MAX_TOKENS,
	): array {
		if ($maxTokens !== null && $maxTokens < 1) {
			throw new InvalidArgumentException('Maximum token count must be at least 1.');
		}

		$tokenizer = new self($source, $options);
		$tokens = [];
		$count = 0;

		while (true) {
			$token = $tokenizer->nextToken();
			if ($token->type === CSSTokenType::Eof) {
				if ($includeEof) {
					$count++;
					if ($maxTokens !== null && $count > $maxTokens) {
						throw new CSSParserLimitException(sprintf('CSS token stream exceeds maximum limit of %d tokens.', $maxTokens));
					}
					$tokens[] = $token;
				}
				break;
			}
			$count++;
			if ($maxTokens !== null && $count > $maxTokens) {
				throw new CSSParserLimitException(sprintf('CSS token stream exceeds maximum limit of %d tokens.', $maxTokens));
			}
			$tokens[] = $token;
		}

		return $tokens;
	}

	public function getPreprocessedSource(): string
	{
		return $this->source;
	}

	/**
	 * @return list<array{message: string, offset: int}>
	 */
	public function getParseErrors(): array
	{
		return $this->parseErrors;
	}

	public function nextToken(): CSSToken
	{
		if ($this->peekedToken !== null) {
			$token = $this->peekedToken;
			$this->peekedToken = null;
			return $token;
		}

		$this->advanceInternal();
		return $this->materializeCurrentToken();
	}

	public function peekToken(): CSSToken
	{
		if ($this->peekedToken === null) {
			$this->advanceInternal();
			$this->peekedToken = $this->materializeCurrentToken();
		}

		return $this->peekedToken;
	}

	/**
	 * @return Generator<int, CSSToken>
	 */
	public function getIterator(): Traversable
	{
		while (true) {
			$token = $this->nextToken();
			if ($token->type === CSSTokenType::Eof) {
				break;
			}
			yield $token;
		}
	}

	/**
	 * @internal Returns current cursor byte offset (after current token).
	 */
	public function getOffsetInternal(): int
	{
		return $this->offset;
	}

	/**
	 * @internal Repositions the cursor offset directly (e.g., after a C-level ASCII fast path).
	 */
	public function setOffsetInternal(int $offset): void
	{
		$this->offset = max(0, min($offset, $this->end));
		$this->peekedToken = null;
	}

	/**
	 * @internal Advances to the next token, updating scalar cursor fields with zero CSSToken allocation.
	 */
	public function advanceInternal(): CSSTokenType
	{
		if ($this->peekedToken !== null) {
			$this->peekedToken = null;
		}
		if ($this->hasDirtyTokenState) {
			$this->tokenFlag = null;
			$this->tokenNumericValue = null;
			$this->tokenRepr = null;
			$this->tokenValue = '';
			$this->tokenUnit = null;
			$this->tokenUnitStart = 0;
			$this->tokenUnitEnd = 0;
			$this->tokenHasEscapes = false;
			$this->tokenHadLeadingComment = false;
			$this->tokenClosedAtEof = false;
			$this->tokenClosedQuote = '';
			$this->tokenTrailingBackslashAtEof = false;
			$this->tokenUnclosedCommentOffset = null;
			$this->commentControlErrorOffsets = [];
			$this->tokenControlByteOffset = null;
			$this->tokenParseErrorMessage = null;
			$this->tokenParseErrorOffset = null;
			$this->hasDirtyTokenState = false;
		}

		$src = $this->source;
		$end = $this->end;
		$pos = $this->offset;

		// §4.3.2 Consume comments
		while ($pos + 1 < $end && $src[$pos] === '/' && $src[$pos + 1] === '*') {
			$this->hasDirtyTokenState = true;
			$this->tokenHadLeadingComment = true;
			$commentEnd = strpos($src, '*/', $pos + 2);
			if ($commentEnd === false || $commentEnd + 1 >= $end) {
				if ($this->checkControlBytes && CSSSyntaxScanner::hasControlCharactersInSlice($src, $pos, $end - $pos)) {
					$this->commentControlErrorOffsets[] = $pos;
				}
				$this->tokenUnclosedCommentOffset = $pos;
				$this->recordInternalError('Unclosed CSS comment.', $pos);
				$pos = $end;
				break;
			}
			if ($this->checkControlBytes && CSSSyntaxScanner::hasControlCharactersInSlice($src, $pos + 2, $commentEnd - ($pos + 2))) {
				$this->commentControlErrorOffsets[] = $pos;
			}
			$pos = $commentEnd + 2;
		}

		if ($pos >= $end) {
			$this->offset = $end;
			$this->tokenStart = $end;
			$this->tokenEnd = $end;
			return $this->tokenType = CSSTokenType::Eof;
		}

		$this->tokenStart = $pos;
		$ch = $src[$pos];
		$ord = ord($ch);

		// Whitespace
		if ($ch === ' ' || $ch === "\t" || $ch === "\n" || (!$this->preprocessed && ($ch === "\r" || $ch === "\f"))) {
			$wsMask = $this->preprocessed ? " \t\n" : self::ASCII_WHITESPACE;
			$pos += strspn($src, $wsMask, $pos, $end - $pos);
			$this->offset = $pos;
			$this->tokenEnd = $pos;
			return $this->tokenType = CSSTokenType::Whitespace;
		}

		// Quoted strings
		if ($ch === '"' || $ch === "'") {
			$this->consumeStringToken($src, $pos, $end);
			$this->offset = $pos;
			$this->tokenEnd = $pos;
			return $this->tokenType;
		}

		// Single-byte punctuation fast path
		switch ($ch) {
			case '(':
				$this->offset = $pos + 1;
				$this->tokenEnd = $pos + 1;
				return $this->tokenType = CSSTokenType::LeftParenthesis;
			case ')':
				$this->offset = $pos + 1;
				$this->tokenEnd = $pos + 1;
				return $this->tokenType = CSSTokenType::RightParenthesis;
			case ',':
				$this->offset = $pos + 1;
				$this->tokenEnd = $pos + 1;
				return $this->tokenType = CSSTokenType::Comma;
			case ':':
				$this->offset = $pos + 1;
				$this->tokenEnd = $pos + 1;
				return $this->tokenType = CSSTokenType::Colon;
			case ';':
				$this->offset = $pos + 1;
				$this->tokenEnd = $pos + 1;
				return $this->tokenType = CSSTokenType::Semicolon;
			case '[':
				$this->offset = $pos + 1;
				$this->tokenEnd = $pos + 1;
				return $this->tokenType = CSSTokenType::LeftSquareBracket;
			case ']':
				$this->offset = $pos + 1;
				$this->tokenEnd = $pos + 1;
				return $this->tokenType = CSSTokenType::RightSquareBracket;
			case '{':
				$this->offset = $pos + 1;
				$this->tokenEnd = $pos + 1;
				return $this->tokenType = CSSTokenType::LeftCurlyBracket;
			case '}':
				$this->offset = $pos + 1;
				$this->tokenEnd = $pos + 1;
				return $this->tokenType = CSSTokenType::RightCurlyBracket;
		}

		// '#'
		if ($ch === '#') {
			$nextPos = $pos + 1;
			$dummyLen = 0;
			if ($nextPos < $end && ($this->isIdentCodePointAt($src, $nextPos, $end, $dummyLen) || $this->isValidEscapeAt($src, $nextPos, $end))) {
				$pos = $nextPos;
				if ($this->decodePayload) {
					$this->hasDirtyTokenState = true;
					$this->tokenFlag = $this->startsIdentSequenceAt($src, $pos, $end)
						? CSSToken::HASH_ID
						: CSSToken::HASH_UNRESTRICTED;
				}
				$val = $this->consumeIdentSequence($src, $pos, $end, $this->decodePayload);
				if ($val !== '') {
					$this->tokenValue = $val;
					$this->hasDirtyTokenState = true;
				}
				$this->offset = $pos;
				$this->tokenEnd = $pos;
				return $this->tokenType = CSSTokenType::Hash;
			}
			$this->offset = $pos + 1;
			$this->tokenEnd = $pos + 1;
			$this->tokenValue = '#';
			$this->hasDirtyTokenState = true;
			return $this->tokenType = CSSTokenType::Delim;
		}

		// '+'
		if ($ch === '+') {
			if (self::startsNumberAt($src, $pos, $end)) {
				$this->consumeNumericToken($src, $pos, $end);
				$this->offset = $pos;
				$this->tokenEnd = $pos;
				return $this->tokenType;
			}
			$this->offset = $pos + 1;
			$this->tokenEnd = $pos + 1;
			$this->tokenValue = '+';
			$this->hasDirtyTokenState = true;
			return $this->tokenType = CSSTokenType::Delim;
		}

		// '-'
		if ($ch === '-') {
			if (self::startsNumberAt($src, $pos, $end)) {
				$this->consumeNumericToken($src, $pos, $end);
				$this->offset = $pos;
				$this->tokenEnd = $pos;
				return $this->tokenType;
			}
			if ($pos + 2 < $end && $src[$pos + 1] === '-' && $src[$pos + 2] === '>') {
				$this->offset = $pos + 3;
				$this->tokenEnd = $pos + 3;
				return $this->tokenType = CSSTokenType::Cdc;
			}
			if ($this->startsIdentSequenceAt($src, $pos, $end)) {
				$this->consumeIdentLikeToken($src, $pos, $end);
				$this->offset = $pos;
				return $this->tokenType;
			}
			$this->offset = $pos + 1;
			$this->tokenEnd = $pos + 1;
			$this->tokenValue = '-';
			$this->hasDirtyTokenState = true;
			return $this->tokenType = CSSTokenType::Delim;
		}

		// '.'
		if ($ch === '.') {
			if (self::startsNumberAt($src, $pos, $end)) {
				$this->consumeNumericToken($src, $pos, $end);
				$this->offset = $pos;
				$this->tokenEnd = $pos;
				return $this->tokenType;
			}
			$this->offset = $pos + 1;
			$this->tokenEnd = $pos + 1;
			$this->tokenValue = '.';
			$this->hasDirtyTokenState = true;
			return $this->tokenType = CSSTokenType::Delim;
		}

		// '<'
		if ($ch === '<') {
			if ($pos + 3 < $end && $src[$pos + 1] === '!' && $src[$pos + 2] === '-' && $src[$pos + 3] === '-') {
				$this->offset = $pos + 4;
				$this->tokenEnd = $pos + 4;
				return $this->tokenType = CSSTokenType::Cdo;
			}
			$this->offset = $pos + 1;
			$this->tokenEnd = $pos + 1;
			$this->tokenValue = '<';
			$this->hasDirtyTokenState = true;
			return $this->tokenType = CSSTokenType::Delim;
		}

		// '@'
		if ($ch === '@') {
			if ($this->startsIdentSequenceAt($src, $pos + 1, $end)) {
				$pos++;
				$val = $this->consumeIdentSequence($src, $pos, $end, $this->decodePayload);
				if ($val !== '') {
					$this->tokenValue = $val;
					$this->hasDirtyTokenState = true;
				}
				$this->offset = $pos;
				$this->tokenEnd = $pos;
				return $this->tokenType = CSSTokenType::AtKeyword;
			}
			$this->offset = $pos + 1;
			$this->tokenEnd = $pos + 1;
			$this->tokenValue = '@';
			$this->hasDirtyTokenState = true;
			return $this->tokenType = CSSTokenType::Delim;
		}

		// '\'
		if ($ch === '\\') {
			if ($this->isValidEscapeAt($src, $pos, $end)) {
				$this->consumeIdentLikeToken($src, $pos, $end);
				$this->offset = $pos;
				return $this->tokenType;
			}
			$this->recordInternalError('Invalid CSS escape before newline.', $pos);
			$this->offset = $pos + 1;
			$this->tokenEnd = $pos + 1;
			$this->tokenValue = '\\';
			$this->hasDirtyTokenState = true;
			return $this->tokenType = CSSTokenType::Delim;
		}

		// Digit
		if ($ord >= 0x30 && $ord <= 0x39) {
			$this->consumeNumericToken($src, $pos, $end);
			$this->offset = $pos;
			$this->tokenEnd = $pos;
			return $this->tokenType;
		}

		// Ident-start (ASCII)
		if (($ord >= 0x61 && $ord <= 0x7A) || ($ord >= 0x41 && $ord <= 0x5A) || $ord === 0x5F) {
			$this->consumeIdentLikeToken($src, $pos, $end);
			$this->offset = $pos;
			return $this->tokenType;
		}

		// Non-ASCII code point (>= 0x80)
		if ($ord >= 0x80) {
			$byteLen = 1;
			$cp = self::readUtf8CodePointAt($src, $pos, $end, $byteLen);
			if ($cp >= 0x80 && self::isNonAsciiIdentCodePoint($cp)) {
				$this->consumeIdentLikeToken($src, $pos, $end);
				$this->offset = $pos;
				return $this->tokenType;
			}
			$this->offset = $pos + $byteLen;
			$this->tokenEnd = $this->offset;
			$this->tokenValue = substr($src, $pos, $byteLen);
			$this->hasDirtyTokenState = true;
			return $this->tokenType = CSSTokenType::Delim;
		}

		// Any other ASCII byte -> <delim-token>
		if ($this->checkControlBytes && ($ord <= 0x08 || $ord === 0x0B || ($ord >= 0x0E && $ord <= 0x1F) || $ord === 0x7F)) {
			$this->tokenControlByteOffset = $pos;
		}
		$this->offset = $pos + 1;
		$this->tokenEnd = $pos + 1;
		$this->tokenValue = $ch;
		$this->hasDirtyTokenState = true;
		return $this->tokenType = CSSTokenType::Delim;
	}

	/**
	 * @internal Lazily decodes the current Ident/AtKeyword/Function/Hash identifier value in parser mode.
	 */
	public function decodeCurrentIdentInternal(): string
	{
		if ($this->tokenType === CSSTokenType::AtKeyword || $this->tokenType === CSSTokenType::Hash) {
			$rawStart = $this->tokenStart + 1;
			$rawEnd = $this->tokenEnd;
		} elseif ($this->tokenType === CSSTokenType::Function) {
			$rawStart = $this->tokenStart;
			$rawEnd = $this->tokenEnd - 1;
		} else {
			$rawStart = $this->tokenStart;
			$rawEnd = $this->tokenEnd;
		}

		if ($rawEnd <= $rawStart) {
			return '';
		}
		$raw = substr($this->source, $rawStart, $rawEnd - $rawStart);
		if (!$this->tokenHasEscapes) {
			return $raw;
		}

		return $this->decodeEscapedIdentSlice($raw);
	}

	/**
	 * @internal Returns lowercased at-keyword name for the current `AtKeyword` token.
	 */
	public function getCurrentAtKeywordLowerInternal(): string
	{
		return strtolower($this->decodeCurrentIdentInternal());
	}

	/**
	 * @internal Returns whether the current `Ident` token matches ASCII `$expectedLower` (case-insensitively).
	 */
	public function currentIdentEqualsLowerInternal(string $expectedLower): bool
	{
		if (!$this->tokenHasEscapes) {
			$len = $this->tokenEnd - $this->tokenStart;
			return $len === strlen($expectedLower)
				&& substr_compare($this->source, $expectedLower, $this->tokenStart, $len, true) === 0;
		}

		return strtolower($this->decodeCurrentIdentInternal()) === $expectedLower;
	}

	/**
	 * @internal Returns true if the current token is an `Ident` or `Function` ending with a bare hex escape (no trailing ws).
	 */
	public function currentTokenEndsWithBareHexEscapeInternal(): bool
	{
		if (!$this->tokenHasEscapes) {
			return false;
		}
		$rawEnd = $this->tokenType === CSSTokenType::Function ? $this->tokenEnd - 1 : $this->tokenEnd;
		if ($rawEnd <= $this->tokenStart) {
			return false;
		}

		return CSSSyntaxScanner::endsWithBareHexEscape(substr($this->source, $this->tokenStart, $rawEnd - $this->tokenStart));
	}

	private function materializeCurrentToken(): CSSToken
	{
		return new CSSToken(
			$this->tokenType,
			$this->tokenValue,
			$this->tokenStart,
			$this->tokenEnd,
			$this->tokenFlag,
			$this->tokenNumericValue,
			$this->tokenUnit,
			$this->tokenRepr,
		);
	}

	private function consumeStringToken(string $src, int &$pos, int $end): void
	{
		$quote = $src[$pos];
		$this->hasDirtyTokenState = true;
		$this->tokenClosedQuote = $quote;
		$pos++;
		$decode = $this->decodePayload;
		$chunks = $decode ? [] : null;
		$chunkStart = $pos;
		$mask = $quote . "\\\n\r\f" . ($this->checkControlBytes ? self::CONTROL_BYTES : '');

		while ($pos < $end) {
			$span = strcspn($src, $mask, $pos, $end - $pos);
			$pos += $span;
			if ($pos >= $end) {
				if ($chunks !== null && $pos > $chunkStart) {
					$chunks[] = substr($src, $chunkStart, $pos - $chunkStart);
				}
				$this->tokenClosedAtEof = true;
				$this->recordInternalError('Unclosed CSS string token.', $this->tokenStart);
				$this->tokenValue = $chunks !== null ? implode('', $chunks) : '';
				$this->tokenType = CSSTokenType::String;
				return;
			}

			$ch = $src[$pos];
			if ($ch === $quote) {
				if ($chunks !== null && $pos > $chunkStart) {
					$chunks[] = substr($src, $chunkStart, $pos - $chunkStart);
				}
				$pos++;
				$this->tokenValue = $chunks !== null ? implode('', $chunks) : '';
				$this->tokenType = CSSTokenType::String;
				return;
			}

			if ($ch === "\n" || $ch === "\r" || $ch === "\f") {
				// §4.3.5: Unescaped newline inside string produces <bad-string-token> and reconsumes newline.
				$this->recordInternalError('Unescaped newline in CSS string token.', $pos);
				$this->tokenValue = '';
				$this->tokenType = CSSTokenType::BadString;
				return;
			}

			if ($ch === '\\') {
				$this->tokenHasEscapes = true;
				if ($chunks !== null && $pos > $chunkStart) {
					$chunks[] = substr($src, $chunkStart, $pos - $chunkStart);
				}

				if ($pos + 1 >= $end) {
					// §4.3.5: Backslash before EOF inside a string is ignored (no U+FFFD), then EOF closes string.
					$this->tokenTrailingBackslashAtEof = true;
					$this->tokenClosedAtEof = true;
					$this->recordInternalError('Unclosed CSS string token.', $this->tokenStart);
					$pos = $end;
					$this->tokenValue = $chunks !== null ? implode('', $chunks) : '';
					$this->tokenType = CSSTokenType::String;
					return;
				}

				$next = $src[$pos + 1];
				if ($next === "\n" || $next === "\r" || $next === "\f") {
					// Line continuation
					if ($next === "\r" && $pos + 2 < $end && $src[$pos + 2] === "\n") {
						$pos += 3;
					} else {
						$pos += 2;
					}
					$chunkStart = $pos;
					continue;
				}

				$decodedEsc = $this->consumeEscapedCodePoint($src, $pos, $end, $decode);
				if ($chunks !== null) {
					$chunks[] = $decodedEsc;
				}
				$chunkStart = $pos;
				continue;
			}

			// Control byte inside string when $checkControlBytes is true
			$this->tokenControlByteOffset ??= $pos;
			$pos++;
		}

		if ($chunks !== null && $pos > $chunkStart) {
			$chunks[] = substr($src, $chunkStart, $pos - $chunkStart);
		}
		$this->tokenClosedAtEof = true;
		$this->recordInternalError('Unclosed CSS string token.', $this->tokenStart);
		$this->tokenValue = $chunks !== null ? implode('', $chunks) : '';
		$this->tokenType = CSSTokenType::String;
	}

	private function consumeIdentLikeToken(string $src, int &$pos, int $end): void
	{
		$identStart = $pos;
		$decoded = $this->consumeIdentSequence($src, $pos, $end, $this->decodePayload);

		if ($pos < $end && $src[$pos] === '(') {
			$pos++;
			$funcEnd = $pos;

			if ($this->tokenHasEscapes) {
				$decodedForUrl = !$this->decodePayload
					? $this->decodeEscapedIdentSlice(substr($src, $identStart, $funcEnd - 1 - $identStart))
					: $decoded;
				$isUrl = strtolower($decodedForUrl) === 'url';
			} else {
				$isUrl = ($funcEnd - 1 - $identStart) === 3 && substr_compare($src, 'url', $identStart, 3, true) === 0;
			}

			if ($isUrl) {
				// §4.3.4: While the next two input code points are whitespace, consume the next input code point.
				while ($pos + 1 < $end && $this->isWhitespaceByte($src[$pos]) && $this->isWhitespaceByte($src[$pos + 1])) {
					if (!$this->preprocessed && $src[$pos] === "\r" && $src[$pos + 1] === "\n") {
						// In non-preprocessed mode, CRLF is a single whitespace code point; check the byte after CRLF.
						if ($pos + 2 < $end && $this->isWhitespaceByte($src[$pos + 2])) {
							$pos += 2;
							continue;
						}
						break;
					}
					$pos++;
				}

				if ($this->isQuotedUrlNext($src, $pos, $end)) {
					$this->tokenEnd = $funcEnd;
					if ($this->decodePayload) {
						$this->tokenValue = $decoded;
						$this->hasDirtyTokenState = true;
					}
					$this->tokenType = CSSTokenType::Function;
					return;
				}

				$this->consumeUrlToken($src, $pos, $end);
				$this->tokenEnd = $pos;
				return;
			}

			$this->tokenEnd = $funcEnd;
			if ($this->decodePayload) {
				$this->tokenValue = $decoded;
				$this->hasDirtyTokenState = true;
			}
			$this->tokenType = CSSTokenType::Function;
			return;
		}

		$this->tokenEnd = $pos;
		if ($this->decodePayload) {
			$this->tokenValue = $decoded;
			$this->hasDirtyTokenState = true;
		}
		$this->tokenType = CSSTokenType::Ident;
	}

	private function isQuotedUrlNext(string $src, int $pos, int $end): bool
	{
		if ($pos >= $end) {
			return false;
		}
		$c0 = $src[$pos];
		if ($c0 === '"' || $c0 === "'") {
			return true;
		}
		if ($this->isWhitespaceByte($c0)) {
			$next = (!$this->preprocessed && $c0 === "\r" && $pos + 1 < $end && $src[$pos + 1] === "\n")
				? $pos + 2
				: $pos + 1;
			if ($next < $end) {
				$c1 = $src[$next];
				return $c1 === '"' || $c1 === "'";
			}
		}

		return false;
	}

	private function consumeUrlToken(string $src, int &$pos, int $end): void
	{
		$this->hasDirtyTokenState = true;
		$wsMask = $this->preprocessed ? " \t\n" : self::ASCII_WHITESPACE;
		$pos += strspn($src, $wsMask, $pos, $end - $pos);

		$decode = $this->decodePayload;
		$chunks = $decode ? [] : null;
		$chunkStart = $pos;

		while ($pos < $end) {
			$pos += strcspn($src, self::URL_SPECIAL_BYTES, $pos, $end - $pos);
			if ($pos >= $end) {
				break;
			}

			$ch = $src[$pos];
			if ($ch === ')') {
				if ($chunks !== null && $pos > $chunkStart) {
					$chunks[] = substr($src, $chunkStart, $pos - $chunkStart);
				}
				$pos++;
				$this->tokenValue = $chunks !== null ? implode('', $chunks) : '';
				$this->tokenType = CSSTokenType::Url;
				return;
			}

			if ($ch === ' ' || $ch === "\t" || $ch === "\n" || $ch === "\r" || $ch === "\f") {
				if ($chunks !== null && $pos > $chunkStart) {
					$chunks[] = substr($src, $chunkStart, $pos - $chunkStart);
				}
				$pos += strspn($src, $wsMask, $pos, $end - $pos);
				if ($pos >= $end) {
					$this->tokenClosedAtEof = true;
					$this->recordInternalError('Unclosed CSS url() token.', $this->tokenStart);
					$this->tokenValue = $chunks !== null ? implode('', $chunks) : '';
					$this->tokenType = CSSTokenType::Url;
					return;
				}
				if ($src[$pos] === ')') {
					$pos++;
					$this->tokenValue = $chunks !== null ? implode('', $chunks) : '';
					$this->tokenType = CSSTokenType::Url;
					return;
				}
				$this->recordInternalError('Unexpected whitespace inside unquoted CSS url().', $pos);
				$this->consumeBadUrlRemnants($src, $pos, $end);
				$this->tokenValue = '';
				$this->tokenType = CSSTokenType::BadUrl;
				return;
			}

			if ($ch === '\\') {
				$this->tokenHasEscapes = true;
				if ($chunks !== null && $pos > $chunkStart) {
					$chunks[] = substr($src, $chunkStart, $pos - $chunkStart);
				}
				if ($this->isValidEscapeAt($src, $pos, $end)) {
					$decodedEsc = $this->consumeEscapedCodePoint($src, $pos, $end, $decode);
					if ($chunks !== null) {
						$chunks[] = $decodedEsc;
					}
					$chunkStart = $pos;
					continue;
				}
				$this->recordInternalError('Invalid escape inside CSS url().', $pos);
				$this->consumeBadUrlRemnants($src, $pos, $end);
				$this->tokenValue = '';
				$this->tokenType = CSSTokenType::BadUrl;
				return;
			}

			// Disallowed ", ', (, or non-printable control byte
			$ord = ord($ch);
			if ($this->checkControlBytes && ($ord <= 0x08 || $ord === 0x0B || ($ord >= 0x0E && $ord <= 0x1F) || $ord === 0x7F)) {
				$this->tokenControlByteOffset ??= $pos;
			}
			$this->recordInternalError('Invalid character inside unquoted CSS url().', $pos);
			$pos++;
			$this->consumeBadUrlRemnants($src, $pos, $end);
			$this->tokenValue = '';
			$this->tokenType = CSSTokenType::BadUrl;
			return;
		}

		if ($chunks !== null && $pos > $chunkStart) {
			$chunks[] = substr($src, $chunkStart, $pos - $chunkStart);
		}
		$this->tokenClosedAtEof = true;
		$this->recordInternalError('Unclosed CSS url() token.', $this->tokenStart);
		$this->tokenValue = $chunks !== null ? implode('', $chunks) : '';
		$this->tokenType = CSSTokenType::Url;
	}

	private function consumeBadUrlRemnants(string $src, int &$pos, int $end): void
	{
		while ($pos < $end) {
			$pos += strcspn($src, "\\)", $pos, $end - $pos);
			if ($pos >= $end) {
				return;
			}
			if ($src[$pos] === ')') {
				$pos++;
				return;
			}
			// '\\'
			if ($this->isValidEscapeAt($src, $pos, $end)) {
				$this->consumeEscapedCodePoint($src, $pos, $end, false);
			} else {
				$pos++;
			}
		}
	}

	private function consumeNumericToken(string $src, int &$pos, int $end): void
	{
		$numStart = $pos;
		$typeFlag = CSSToken::NUMERIC_INTEGER;

		if ($pos < $end && ($src[$pos] === '+' || $src[$pos] === '-')) {
			$pos++;
		}
		$pos += strspn($src, self::ASCII_DIGITS, $pos, $end - $pos);

		if ($pos + 1 < $end && $src[$pos] === '.' && $src[$pos + 1] >= '0' && $src[$pos + 1] <= '9') {
			$typeFlag = CSSToken::NUMERIC_NUMBER;
			$pos += 2;
			$pos += strspn($src, self::ASCII_DIGITS, $pos, $end - $pos);
		}

		if ($pos + 1 < $end && ($src[$pos] === 'e' || $src[$pos] === 'E')) {
			$expPos = $pos + 1;
			if ($src[$expPos] === '+' || $src[$expPos] === '-') {
				$expPos++;
			}
			if ($expPos < $end && $src[$expPos] >= '0' && $src[$expPos] <= '9') {
				$typeFlag = CSSToken::NUMERIC_NUMBER;
				$pos = $expPos + strspn($src, self::ASCII_DIGITS, $expPos, $end - $expPos);
			}
		}

		if ($this->decodePayload) {
			$repr = substr($src, $numStart, $pos - $numStart);
			$this->tokenRepr = $repr;
			$this->tokenNumericValue = (float) $repr;
			$this->hasDirtyTokenState = true;
		}

		if ($this->startsIdentSequenceAt($src, $pos, $end)) {
			if ($this->decodePayload) {
				$this->tokenUnitStart = $pos;
				$unit = $this->consumeIdentSequence($src, $pos, $end, true);
				$this->tokenUnitEnd = $pos;
				$this->tokenFlag = $typeFlag;
				$this->tokenUnit = $unit;
				$this->hasDirtyTokenState = true;
			} else {
				$this->consumeIdentSequence($src, $pos, $end, false);
			}
			$this->tokenType = CSSTokenType::Dimension;
			return;
		}

		if ($pos < $end && $src[$pos] === '%') {
			$pos++;
			// CSS Syntax L3 §4.2 / §4.3.3 step 3: <percentage-token> carries no type flag.
			$this->tokenType = CSSTokenType::Percentage;
			return;
		}

		if ($this->decodePayload) {
			$this->tokenFlag = $typeFlag;
			$this->hasDirtyTokenState = true;
		}
		$this->tokenType = CSSTokenType::Number;
	}

	private function consumeIdentSequence(string $src, int &$pos, int $end, bool $decode): string
	{
		$start = $pos;
		$chunks = $decode ? [] : null;
		$chunkStart = $pos;

		while ($pos < $end) {
			$asciiLen = strspn($src, self::ASCII_IDENT_BYTES, $pos, $end - $pos);
			if ($asciiLen > 0) {
				$pos += $asciiLen;
				if ($pos >= $end) {
					break;
				}
			}

			$ch = $src[$pos];
			$ord = ord($ch);
			if ($ord >= 0x80) {
				$byteLen = 1;
				$cp = self::readUtf8CodePointAt($src, $pos, $end, $byteLen);
				if ($cp >= 0x80 && self::isNonAsciiIdentCodePoint($cp)) {
					$pos += $byteLen;
					continue;
				}
				break;
			}

			if ($ch === '\\' && $this->isValidEscapeAt($src, $pos, $end)) {
				$this->tokenHasEscapes = true;
				$this->hasDirtyTokenState = true;
				if ($chunks !== null && $pos > $chunkStart) {
					$chunks[] = substr($src, $chunkStart, $pos - $chunkStart);
				}
				$decodedEsc = $this->consumeEscapedCodePoint($src, $pos, $end, $decode);
				if ($chunks !== null) {
					$chunks[] = $decodedEsc;
				}
				$chunkStart = $pos;
				continue;
			}

			break;
		}

		if ($chunks === null) {
			return '';
		}
		if ($chunks === []) {
			return substr($src, $start, $pos - $start);
		}
		if ($pos > $chunkStart) {
			$chunks[] = substr($src, $chunkStart, $pos - $chunkStart);
		}

		return implode('', $chunks);
	}

	private function consumeEscapedCodePoint(string $src, int &$pos, int $end, bool $decode): string
	{
		$slashPos = $pos;
		$pos++; // skip '\'
		if ($pos >= $end) {
			$this->recordInternalError('Unexpected EOF after CSS escape backslash.', $slashPos);
			return $decode ? self::REPLACEMENT_CHAR : '';
		}

		$hexLen = strspn($src, self::HEX_DIGITS, $pos, min(6, $end - $pos));
		if ($hexLen > 0) {
			$hex = $decode ? substr($src, $pos, $hexLen) : '';
			$pos += $hexLen;

			if ($pos < $end) {
				$ws = $src[$pos];
				if (!$this->preprocessed && $ws === "\r" && $pos + 1 < $end && $src[$pos + 1] === "\n") {
					$pos += 2;
				} elseif ($ws === ' ' || $ws === "\t" || $ws === "\n" || (!$this->preprocessed && ($ws === "\r" || $ws === "\f"))) {
					$pos++;
				}
			}

			if (!$decode) {
				return '';
			}

			$codepoint = (int) hexdec($hex);
			if ($codepoint === 0 || ($codepoint >= 0xD800 && $codepoint <= 0xDFFF) || $codepoint > 0x10FFFF) {
				return self::REPLACEMENT_CHAR;
			}

			return self::encodeUtf8Codepoint($codepoint);
		}

		$ord = ord($src[$pos]);
		if ($ord < 0x80) {
			$ch = $src[$pos];
			$pos++;
			return $decode ? $ch : '';
		}

		$byteLen = 1;
		self::readUtf8CodePointAt($src, $pos, $end, $byteLen);
		$escaped = $decode ? substr($src, $pos, $byteLen) : '';
		$pos += $byteLen;

		return $escaped;
	}

	private function decodeEscapedIdentSlice(string $raw): string
	{
		$len = strlen($raw);
		$pos = 0;
		$chunks = [];

		while ($pos < $len) {
			$span = strcspn($raw, '\\', $pos, $len - $pos);
			if ($span > 0) {
				$chunks[] = substr($raw, $pos, $span);
				$pos += $span;
				if ($pos >= $len) {
					break;
				}
			}

			$pos++; // skip '\'
			if ($pos >= $len) {
				$chunks[] = self::REPLACEMENT_CHAR;
				break;
			}

			$hexLen = strspn($raw, self::HEX_DIGITS, $pos, min(6, $len - $pos));
			if ($hexLen > 0) {
				$codepoint = (int) hexdec(substr($raw, $pos, $hexLen));
				$pos += $hexLen;
				if ($pos < $len) {
					$ws = $raw[$pos];
					if ($ws === "\r" && $pos + 1 < $len && $raw[$pos + 1] === "\n") {
						$pos += 2;
					} elseif ($ws === ' ' || $ws === "\t" || $ws === "\n" || $ws === "\r" || $ws === "\f") {
						$pos++;
					}
				}
				if ($codepoint === 0 || ($codepoint >= 0xD800 && $codepoint <= 0xDFFF) || $codepoint > 0x10FFFF) {
					$chunks[] = self::REPLACEMENT_CHAR;
				} else {
					$chunks[] = self::encodeUtf8Codepoint($codepoint);
				}
				continue;
			}

			$ord = ord($raw[$pos]);
			if ($ord < 0x80) {
				$chunks[] = $raw[$pos];
				$pos++;
			} else {
				$byteLen = 1;
				self::readUtf8CodePointAt($raw, $pos, $len, $byteLen);
				$chunks[] = substr($raw, $pos, $byteLen);
				$pos += $byteLen;
			}
		}

		return implode('', $chunks);
	}

	private function isValidEscapeAt(string $src, int $pos, int $end): bool
	{
		if ($pos >= $end || $src[$pos] !== '\\') {
			return false;
		}
		if ($pos + 1 >= $end) {
			// §4.3.8: EOF is not a newline; trailing '\' starts a valid escape yielding U+FFFD.
			return true;
		}
		$next = $src[$pos + 1];

		return $next !== "\n" && ($this->preprocessed || ($next !== "\r" && $next !== "\f"));
	}

	private function startsIdentSequenceAt(string $src, int $pos, int $end): bool
	{
		if ($pos >= $end) {
			return false;
		}

		$ch = $src[$pos];
		if ($ch === '-') {
			$next = $pos + 1;
			if ($next >= $end) {
				return false;
			}
			$c2 = $src[$next];
			if ($c2 === '-') {
				return true;
			}
			$dummyLen = 0;
			return $this->isIdentStartAt($src, $next, $end, $dummyLen) || $this->isValidEscapeAt($src, $next, $end);
		}

		$dummyLen = 0;
		return $this->isIdentStartAt($src, $pos, $end, $dummyLen) || $this->isValidEscapeAt($src, $pos, $end);
	}

	private static function startsNumberAt(string $src, int $pos, int $end): bool
	{
		if ($pos >= $end) {
			return false;
		}
		$c0 = $src[$pos];
		if ($c0 === '+' || $c0 === '-') {
			if ($pos + 1 >= $end) {
				return false;
			}
			$c1 = $src[$pos + 1];
			if ($c1 >= '0' && $c1 <= '9') {
				return true;
			}
			return $c1 === '.' && $pos + 2 < $end && $src[$pos + 2] >= '0' && $src[$pos + 2] <= '9';
		}
		if ($c0 === '.') {
			return $pos + 1 < $end && $src[$pos + 1] >= '0' && $src[$pos + 1] <= '9';
		}

		return $c0 >= '0' && $c0 <= '9';
	}

	private function isIdentStartAt(string $src, int $pos, int $end, int &$byteLen): bool
	{
		if ($pos >= $end) {
			$byteLen = 0;
			return false;
		}
		$ord = ord($src[$pos]);
		if ($ord < 0x80) {
			$byteLen = 1;
			return ($ord >= 0x61 && $ord <= 0x7A) || ($ord >= 0x41 && $ord <= 0x5A) || $ord === 0x5F;
		}
		$cp = self::readUtf8CodePointAt($src, $pos, $end, $byteLen);

		return $cp >= 0x80 && self::isNonAsciiIdentCodePoint($cp);
	}

	private function isIdentCodePointAt(string $src, int $pos, int $end, int &$byteLen): bool
	{
		if ($pos >= $end) {
			$byteLen = 0;
			return false;
		}
		$ord = ord($src[$pos]);
		if ($ord < 0x80) {
			$byteLen = 1;
			return ($ord >= 0x61 && $ord <= 0x7A)
				|| ($ord >= 0x41 && $ord <= 0x5A)
				|| ($ord >= 0x30 && $ord <= 0x39)
				|| $ord === 0x5F
				|| $ord === 0x2D;
		}
		$cp = self::readUtf8CodePointAt($src, $pos, $end, $byteLen);

		return $cp >= 0x80 && self::isNonAsciiIdentCodePoint($cp);
	}

	private function isWhitespaceByte(string $ch): bool
	{
		if ($ch === ' ' || $ch === "\t" || $ch === "\n") {
			return true;
		}

		return !$this->preprocessed && ($ch === "\r" || $ch === "\f");
	}

	private function recordInternalError(string $message, int $offset): void
	{
		if ($this->parserMode) {
			if ($this->tokenParseErrorMessage === null) {
				$this->hasDirtyTokenState = true;
				$this->tokenParseErrorMessage = $message;
				$this->tokenParseErrorOffset = $offset;
			}
			return;
		}

		if ($this->maxErrors !== null && count($this->parseErrors) >= $this->maxErrors) {
			if (!$this->errorsTruncated) {
				$this->errorsTruncated = true;
				$this->parseErrors[] = [
					'message' => sprintf('Maximum parse error limit of %d exceeded; further diagnostics suppressed.', $this->maxErrors),
					'offset' => $offset,
				];
			}
			return;
		}

		$this->parseErrors[] = ['message' => $message, 'offset' => $offset];
	}

	/**
	 * CSS Syntax Module Level 3 §4.2 non-ASCII ident code point table.
	 *
	 * @internal Also usable by `CSSSyntaxScanner` for spec-aligned identifier validation.
	 */
	public static function isNonAsciiIdentCodePoint(int $cp): bool
	{
		return $cp === 0x00B7
			|| ($cp >= 0x00C0 && $cp <= 0x00D6)
			|| ($cp >= 0x00D8 && $cp <= 0x00F6)
			|| ($cp >= 0x00F8 && $cp <= 0x037D)
			|| ($cp >= 0x037F && $cp <= 0x1FFF)
			|| $cp === 0x200C
			|| $cp === 0x200D
			|| $cp === 0x203F
			|| $cp === 0x2040
			|| ($cp >= 0x2070 && $cp <= 0x218F)
			|| ($cp >= 0x2C00 && $cp <= 0x2FEF)
			|| ($cp >= 0x3001 && $cp <= 0xD7FF)
			|| ($cp >= 0xF900 && $cp <= 0xFDCF)
			|| ($cp >= 0xFDF0 && $cp <= 0xFFFD)
			|| $cp >= 0x10000;
	}

	/**
	 * Decodes one UTF-8 code point at `$pos`, returning `-1` with `$byteLen = 1` on ill-formed bytes.
	 *
	 * @internal
	 */
	public static function readUtf8CodePointAt(string $src, int $pos, int $end, int &$byteLen): int
	{
		$b0 = ord($src[$pos]);
		if ($b0 < 0x80) {
			$byteLen = 1;
			return $b0;
		}
		if ($b0 >= 0xC2 && $b0 <= 0xDF && $pos + 1 < $end) {
			$b1 = ord($src[$pos + 1]);
			if ($b1 >= 0x80 && $b1 <= 0xBF) {
				$byteLen = 2;
				return (($b0 & 0x1F) << 6) | ($b1 & 0x3F);
			}
		} elseif ($b0 >= 0xE0 && $b0 <= 0xEF && $pos + 2 < $end) {
			$b1 = ord($src[$pos + 1]);
			$b2 = ord($src[$pos + 2]);
			if ($b2 >= 0x80 && $b2 <= 0xBF) {
				$validB1 = $b0 === 0xE0
					? ($b1 >= 0xA0 && $b1 <= 0xBF)
					: ($b0 === 0xED ? ($b1 >= 0x80 && $b1 <= 0x9F) : ($b1 >= 0x80 && $b1 <= 0xBF));
				if ($validB1) {
					$byteLen = 3;
					return (($b0 & 0x0F) << 12) | (($b1 & 0x3F) << 6) | ($b2 & 0x3F);
				}
			}
		} elseif ($b0 >= 0xF0 && $b0 <= 0xF4 && $pos + 3 < $end) {
			$b1 = ord($src[$pos + 1]);
			$b2 = ord($src[$pos + 2]);
			$b3 = ord($src[$pos + 3]);
			if ($b2 >= 0x80 && $b2 <= 0xBF && $b3 >= 0x80 && $b3 <= 0xBF) {
				$validB1 = $b0 === 0xF0
					? ($b1 >= 0x90 && $b1 <= 0xBF)
					: ($b0 === 0xF4 ? ($b1 >= 0x80 && $b1 <= 0x8F) : ($b1 >= 0x80 && $b1 <= 0xBF));
				if ($validB1) {
					$byteLen = 4;
					return (($b0 & 0x07) << 18) | (($b1 & 0x3F) << 12) | (($b2 & 0x3F) << 6) | ($b3 & 0x3F);
				}
			}
		}

		$byteLen = 1;
		return -1;
	}

	private static function encodeUtf8Codepoint(int $cp): string
	{
		if ($cp <= 0x7F) {
			return chr($cp);
		}
		if ($cp <= 0x7FF) {
			return chr(0xC0 | ($cp >> 6)) . chr(0x80 | ($cp & 0x3F));
		}
		if ($cp <= 0xFFFF) {
			return chr(0xE0 | ($cp >> 12)) . chr(0x80 | (($cp >> 6) & 0x3F)) . chr(0x80 | ($cp & 0x3F));
		}

		return chr(0xF0 | ($cp >> 18))
			. chr(0x80 | (($cp >> 12) & 0x3F))
			. chr(0x80 | (($cp >> 6) & 0x3F))
			. chr(0x80 | ($cp & 0x3F));
	}
}
