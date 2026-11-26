<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Parser;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSToken;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenizer;
use Jimbo2150\PhpCssTypedOm\Parser\CSSTokenType;
use PHPUnit\Framework\TestCase;

final class CSSTokenizerTest extends TestCase
{
	public function testTokenizesAllTwentyFiveW3CTokenTypes(): void
	{
		$css = "<!-- @media (#id, [a='x'], calc(10px + 50% - 2.5)) { /*c*/ url(a.png) url(a b) \"bad\n } : ; --> \\";
		$tokens = CSSTokenizer::tokenize($css, includeEof: true);
		$types = array_map(static fn (CSSToken $t): CSSTokenType => $t->type, $tokens);

		self::assertContains(CSSTokenType::Cdo, $types);
		self::assertContains(CSSTokenType::AtKeyword, $types);
		self::assertContains(CSSTokenType::LeftParenthesis, $types);
		self::assertContains(CSSTokenType::Hash, $types);
		self::assertContains(CSSTokenType::Comma, $types);
		self::assertContains(CSSTokenType::LeftSquareBracket, $types);
		self::assertContains(CSSTokenType::Ident, $types);
		self::assertContains(CSSTokenType::Delim, $types);
		self::assertContains(CSSTokenType::String, $types);
		self::assertContains(CSSTokenType::RightSquareBracket, $types);
		self::assertContains(CSSTokenType::Function, $types);
		self::assertContains(CSSTokenType::Dimension, $types);
		self::assertContains(CSSTokenType::Percentage, $types);
		self::assertContains(CSSTokenType::Number, $types);
		self::assertContains(CSSTokenType::RightParenthesis, $types);
		self::assertContains(CSSTokenType::LeftCurlyBracket, $types);
		self::assertContains(CSSTokenType::Whitespace, $types);
		self::assertContains(CSSTokenType::Url, $types);
		self::assertContains(CSSTokenType::BadUrl, $types);
		self::assertContains(CSSTokenType::BadString, $types);
		self::assertContains(CSSTokenType::RightCurlyBracket, $types);
		self::assertContains(CSSTokenType::Colon, $types);
		self::assertContains(CSSTokenType::Semicolon, $types);
		self::assertContains(CSSTokenType::Cdc, $types);
		self::assertContains(CSSTokenType::Eof, $types);
		self::assertCount(25, array_unique(array_map(static fn (CSSTokenType $t): string => $t->value, $types)));
	}

	public function testHashIdVsUnrestrictedFlags(): void
	{
		$cases = [
			'#abc' => [CSSTokenType::Hash, 'abc', CSSToken::HASH_ID],
			'#-a' => [CSSTokenType::Hash, '-a', CSSToken::HASH_ID],
			'#--' => [CSSTokenType::Hash, '--', CSSToken::HASH_ID],
			'#\31 ' => [CSSTokenType::Hash, '1', CSSToken::HASH_ID],
			'#123' => [CSSTokenType::Hash, '123', CSSToken::HASH_UNRESTRICTED],
			'#-1' => [CSSTokenType::Hash, '-1', CSSToken::HASH_UNRESTRICTED],
			'#-' => [CSSTokenType::Hash, '-', CSSToken::HASH_UNRESTRICTED],
			'#' => [CSSTokenType::Delim, '#', null],
		];

		foreach ($cases as $input => [$expectedType, $expectedValue, $expectedFlag]) {
			$tokens = CSSTokenizer::tokenize($input);
			self::assertCount(1, $tokens, $input);
			self::assertSame($expectedType, $tokens[0]->type, $input);
			self::assertSame($expectedValue, $tokens[0]->value, $input);
			self::assertSame($expectedFlag, $tokens[0]->flag, $input);
			self::assertSame($expectedFlag === CSSToken::HASH_ID, $tokens[0]->isHashId(), $input);
		}
	}

	public function testCdoCdcVsIdentAndNumbers(): void
	{
		// Per CSS Syntax L3 §4.3.3 & §4.3.9, 10--> starts an ident '--' after 10 -> dimension(10, '--') + delim('>')
		$dimTokens = CSSTokenizer::tokenize('10-->');
		self::assertSame(
			[CSSTokenType::Dimension, CSSTokenType::Delim],
			array_map(static fn (CSSToken $t): CSSTokenType => $t->type, $dimTokens),
		);
		self::assertSame(10.0, $dimTokens[0]->numericValue);
		self::assertSame('--', $dimTokens[0]->unit);
		self::assertSame('>', $dimTokens[1]->value);

		$cdcTokens = CSSTokenizer::tokenize('<!-- --> --custom foo--> <! --');
		self::assertSame(CSSTokenType::Cdo, $cdcTokens[0]->type);
		self::assertSame(CSSTokenType::Cdc, $cdcTokens[2]->type);
		self::assertSame(CSSTokenType::Ident, $cdcTokens[4]->type);
		self::assertSame('--custom', $cdcTokens[4]->value);
		self::assertSame(CSSTokenType::Ident, $cdcTokens[6]->type);
		self::assertSame('foo--', $cdcTokens[6]->value);
		self::assertSame(CSSTokenType::Delim, $cdcTokens[7]->type);
		self::assertSame('>', $cdcTokens[7]->value);
		self::assertSame(CSSTokenType::Delim, $cdcTokens[9]->type);
		self::assertSame('<', $cdcTokens[9]->value);
		self::assertSame(CSSTokenType::Delim, $cdcTokens[10]->type);
		self::assertSame('!', $cdcTokens[10]->value);
	}

	public function testNumericPercentageDimensionFlagsAndInfinity(): void
	{
		$tokens = CSSTokenizer::tokenize('10 10.0 1e2 50% 12px 1.5em 1e999 10-1');
		$nonWs = array_values(array_filter($tokens, static fn (CSSToken $t): bool => $t->type !== CSSTokenType::Whitespace));

		self::assertTrue($nonWs[0]->isInteger());
		self::assertSame(CSSToken::NUMERIC_INTEGER, $nonWs[0]->flag);
		self::assertFalse($nonWs[1]->isInteger());
		self::assertSame(CSSToken::NUMERIC_NUMBER, $nonWs[1]->flag);
		self::assertSame(CSSToken::NUMERIC_NUMBER, $nonWs[2]->flag);

		// CSS Syntax L3 §4.2: <percentage-token> carries NO type flag
		self::assertSame(CSSTokenType::Percentage, $nonWs[3]->type);
		self::assertNull($nonWs[3]->flag);
		self::assertFalse($nonWs[3]->isInteger());
		self::assertSame(50.0, $nonWs[3]->numericValue);

		self::assertSame(CSSTokenType::Dimension, $nonWs[4]->type);
		self::assertSame('px', $nonWs[4]->unit);
		self::assertTrue($nonWs[4]->isInteger());
		self::assertSame(CSSToken::NUMERIC_NUMBER, $nonWs[5]->flag);

		self::assertSame(INF, $nonWs[6]->numericValue);
		self::assertSame(10.0, $nonWs[7]->numericValue);
		self::assertSame(-1.0, $nonWs[8]->numericValue);
	}

	public function testStringsUrlsAndBadTokenRemnants(): void
	{
		// Bad string reconsumes newline
		$badStr = CSSTokenizer::tokenize("\"a\nb\"");
		self::assertSame(
			[CSSTokenType::BadString, CSSTokenType::Whitespace, CSSTokenType::Ident, CSSTokenType::String],
			array_map(static fn (CSSToken $t): CSSTokenType => $t->type, $badStr),
		);

		// Backslash before EOF in string is ignored (no U+FFFD)
		$tokEofStr = new CSSTokenizer('"abc\\');
		$eofStr = $tokEofStr->nextToken();
		self::assertSame(CSSTokenType::String, $eofStr->type);
		self::assertSame('abc', $eofStr->value);
		self::assertNotEmpty($tokEofStr->getParseErrors());

		// url(   "foo") -> Function token whose raw slice stops right after '('
		$quotedUrlSrc = 'url(   "foo")';
		$quotedUrl = CSSTokenizer::tokenize($quotedUrlSrc);
		self::assertSame(CSSTokenType::Function, $quotedUrl[0]->type);
		self::assertSame('url', $quotedUrl[0]->value);
		self::assertSame('url(', $quotedUrl[0]->getRawSlice($quotedUrlSrc));
		self::assertSame(CSSTokenType::Whitespace, $quotedUrl[1]->type);
		self::assertSame(CSSTokenType::String, $quotedUrl[2]->type);
		self::assertSame('foo', $quotedUrl[2]->value);

		// Bad URL remnants with escaped paren and multi-byte UTF-8 escape
		$badUrl = CSSTokenizer::tokenize("url(a b \\) \\29 \\😀 ) after");
		self::assertSame(CSSTokenType::BadUrl, $badUrl[0]->type);
		self::assertSame(CSSTokenType::Whitespace, $badUrl[1]->type);
		self::assertSame(CSSTokenType::Ident, $badUrl[2]->type);
		self::assertSame('after', $badUrl[2]->value);

		// Valid URL with escaped space and paren
		$goodUrl = CSSTokenizer::tokenize('url(  a\\)b\\20 c  )');
		self::assertSame(CSSTokenType::Url, $goodUrl[0]->type);
		self::assertSame('a)b c', $goodUrl[0]->value);
	}

	public function testModernNonAsciiIdentTableAndEscapes(): void
	{
		$tokens = CSSTokenizer::tokenize("café \u{00A0} \u{00B7}mid 😀 foo\\");
		$nonWs = array_values(array_filter($tokens, static fn (CSSToken $t): bool => $t->type !== CSSTokenType::Whitespace));

		self::assertSame(CSSTokenType::Ident, $nonWs[0]->type);
		self::assertSame('café', $nonWs[0]->value);
		self::assertSame(CSSTokenType::Delim, $nonWs[1]->type); // U+00A0 NBSP is NOT a non-ASCII ident code point
		self::assertSame(CSSTokenType::Ident, $nonWs[2]->type); // U+00B7 IS a non-ASCII ident code point
		self::assertSame(CSSTokenType::Ident, $nonWs[3]->type); // Astral code point IS ident
		self::assertSame(CSSTokenType::Ident, $nonWs[4]->type);
		self::assertSame("foo\u{FFFD}", $nonWs[4]->value);
	}

	public function testStreamingPeekIteratorAndLimits(): void
	{
		$tokenizer = new CSSTokenizer("\xEF\xBB\xBFa: 1px;\r\n");
		self::assertSame("a: 1px;\n", $tokenizer->getPreprocessedSource());

		$peek = $tokenizer->peekToken();
		self::assertSame(CSSTokenType::Ident, $peek->type);
		self::assertSame($peek, $tokenizer->nextToken());
		self::assertSame(CSSTokenType::Colon, $tokenizer->nextToken()->type);

		$rest = [];
		foreach ($tokenizer as $tok) {
			$rest[] = $tok->type;
		}
		self::assertSame([CSSTokenType::Whitespace, CSSTokenType::Dimension, CSSTokenType::Semicolon, CSSTokenType::Whitespace], $rest);
		self::assertTrue($tokenizer->nextToken()->isEof());
		self::assertTrue($tokenizer->nextToken()->isEof());

		try {
			CSSTokenizer::tokenize('a b c', null, false, 2);
			self::fail('Expected maxTokens overflow to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$this->expectException(InvalidArgumentException::class);
		CSSTokenizer::tokenize('a', null, false, 0);
	}
}
