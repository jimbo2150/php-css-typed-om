<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Parser;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Parser\CSSSyntaxScanner;
use PHPUnit\Framework\TestCase;

final class CSSSyntaxScannerTest extends TestCase
{
	public function testSplitsOnlyAtTopLevelDelimiters(): void
	{
		$source = 'color: red; --value: fn(a;b); content: "x;y"; /* ; */ width: 1px';

		self::assertSame(
			['color: red', ' --value: fn(a;b)', ' content: "x;y"', ' /* ; */ width: 1px'],
			CSSSyntaxScanner::splitTopLevel($source, ';'),
		);
	}

	public function testIgnoresEscapedQuotesAndNestedBlocks(): void
	{
		$source = 'content: "a\";b"; --object: { value: [a;b] }; end: yes';

		self::assertSame(
			['content: "a\";b"', ' --object: { value: [a;b] }', ' end: yes'],
			CSSSyntaxScanner::splitTopLevel($source, ';'),
		);
	}

	public function testFindsOnlyTopLevelDelimiter(): void
	{
		self::assertSame(7, CSSSyntaxScanner::findTopLevel('fn(a:b): value', ':'));
		self::assertNull(CSSSyntaxScanner::findTopLevel('fn(a:b)', ':'));
	}

	public function testFindsMatchingCloseDelimiter(): void
	{
		$source = 'a{content:"}";b{color:red}} tail';

		self::assertSame(26, CSSSyntaxScanner::findMatchingDelimiter($source, 1));
		self::assertNull(CSSSyntaxScanner::findMatchingDelimiter('a{b', 1));
	}

	public function testSplitTopLevelWhitespaceAndBounds(): void
	{
		$source = 'prefix: screen and (min-width: 10px)  "a b" /* c */ print;';

		self::assertSame(
			['screen', 'and', '(min-width: 10px)', '"a b"', 'print'],
			CSSSyntaxScanner::splitTopLevelWhitespace($source, 8, strlen($source) - 1),
		);
	}

	public function testFindNextTopLevelDelimiterAndSliceBounds(): void
	{
		$source = '  a(1;2) { color: red; }';
		$next = CSSSyntaxScanner::findNextTopLevelDelimiter($source, [';', '{', '}'], 2, 12);

		self::assertSame(['offset' => 9, 'delimiter' => '{'], $next);
		self::assertNull(CSSSyntaxScanner::findNextTopLevelDelimiter($source, [';'], 2, 9));
	}

	public function testUnquotedUrlDoesNotTreatSlashStarAsComment(): void
	{
		// SEC-H1: Unquoted url(...) closes at the first unescaped ')' even if '/*' appeared inside.
		$payloadRuleBreakout = 'url(https://example.com/*) } body { background: red } */)';
		$payloadDeclInjection = 'url(https://example.com/*); color: red; --x: */)';
		$escapedUrlBreakout = '\\75 rl(https://example.com/*) } body { color: red } */)';
		$escapedHexWithNoSpace = '\\75\\72\\6c(https://example.com/*);color:red;/*)';

		self::assertFalse(CSSSyntaxScanner::isBalanced($payloadRuleBreakout));
		self::assertFalse(CSSSyntaxScanner::isBalanced($escapedUrlBreakout));

		foreach ([$payloadRuleBreakout, $payloadDeclInjection, $escapedUrlBreakout, $escapedHexWithNoSpace] as $payload) {
			try {
				CSSSyntaxScanner::assertValidFragment($payload, 'CSS value');
				self::fail('Expected unquoted url() comment breakout payload to fail validation: ' . $payload);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// Valid unquoted URLs with '/*', data URIs, and escaped parens remain allowed and preserve '/*'.
		$validUrl = 'url(https://example.com/*not-a-comment*/a.png)';
		$escapedParenUrl = 'url(a\\)b.png)';
		$dataUri = "url(data:image/svg+xml;utf8,%3Csvg%3E%3C/svg%3E)";
		$quotedUrlWithCommentAfter = 'url("a.png" /* note */)';

		self::assertTrue(CSSSyntaxScanner::isBalanced($validUrl));
		self::assertTrue(CSSSyntaxScanner::isBalanced($escapedParenUrl));
		self::assertTrue(CSSSyntaxScanner::isBalanced($dataUri));
		self::assertTrue(CSSSyntaxScanner::isBalanced($quotedUrlWithCommentAfter));
		self::assertSame($validUrl, CSSSyntaxScanner::removeComments($validUrl));
		self::assertSame('url("a.png" )', CSSSyntaxScanner::removeComments($quotedUrlWithCommentAfter));

		// Unquoted URLs reject unescaped quotes and interior whitespace.
		self::assertFalse(CSSSyntaxScanner::isBalanced("url(a'b.png)"));
		self::assertFalse(CSSSyntaxScanner::isBalanced('url(a b.png)'));
		self::assertTrue(CSSSyntaxScanner::isBalanced('url(  a.png  )'));
	}

	public function testQuotedStringLineContinuationsAndControlBytes(): void
	{
		self::assertFalse(CSSSyntaxScanner::isBalanced("\"a\\\\\nb\""));
		self::assertTrue(CSSSyntaxScanner::isBalanced("\"a\\\nb\""));
		self::assertTrue(CSSSyntaxScanner::isBalanced("\"a\\\r\nb\""));
		self::assertTrue(CSSSyntaxScanner::isBalanced("\"foo\\61\nbar\""));
		self::assertTrue(CSSSyntaxScanner::isBalanced("\"foo\\61\r\nbar\""));
		self::assertFalse(CSSSyntaxScanner::isBalanced("\"a\nb\""));
		self::assertFalse(CSSSyntaxScanner::isBalanced("a\\\nb"));

		// Unclosed unquoted URLs and EOF-after-escape URLs must fail balance & context checks
		self::assertFalse(CSSSyntaxScanner::isBalanced('url('));
		self::assertFalse(CSSSyntaxScanner::isBalanced('url(   '));
		self::assertFalse(CSSSyntaxScanner::isBalanced('url(a\61'));
		self::assertTrue(CSSSyntaxScanner::hasUnclosedCommentOrString('url('));
		self::assertNotNull(CSSSyntaxScanner::findMatchingUrlEnd('url("a.png" /* note */)', 3));
		self::assertNull(CSSSyntaxScanner::findMatchingUrlEnd("url(\"foo\nbar)", 3));

		// Escaped whitespace / long hex-escaped ident before '(' is a <function-token>, not unquoted url()
		self::assertSame(10, CSSSyntaxScanner::findTopLevel('\ url(a;b);c', ';'));
		self::assertNull(CSSSyntaxScanner::findTopLevel('\ url(a;b)', ';'));
		$longHexUrl = "\\000075\r\n\\000072\r\n\\00006c(";
		self::assertSame(30, CSSSyntaxScanner::findTopLevel($longHexUrl . 'a;b);c', ';'));
		self::assertNull(CSSSyntaxScanner::findTopLevel(str_repeat('\61', 15) . 'url(a;b)', ';'));

		self::assertTrue(CSSSyntaxScanner::hasUnclosedCommentOrString('"unterminated'));
		self::assertTrue(CSSSyntaxScanner::hasUnclosedCommentOrString("/* unclosed"));
		self::assertTrue(CSSSyntaxScanner::hasUnclosedCommentOrString('url(unclosed'));
		self::assertFalse(CSSSyntaxScanner::hasUnclosedCommentOrString('"closed" /* ok */ url(ok)'));

		self::assertTrue(CSSSyntaxScanner::hasControlCharacters("ok\x00bad"));
		self::assertFalse(CSSSyntaxScanner::hasControlCharacters("ok\t\n\r\f"));
	}

	public function testDecodeCssIdentEscapesAndFindLastImportant(): void
	{
		self::assertSame('important', CSSSyntaxScanner::decodeCssIdentEscapes('\\69 mportant'));
		self::assertSame('important', CSSSyntaxScanner::decodeCssIdentEscapes('\\000069\\6d port\\61 nt'));
		self::assertSame('a:b', CSSSyntaxScanner::decodeCssIdentEscapes('a\\:b'));
		self::assertSame("\u{FFFD}", CSSSyntaxScanner::decodeCssIdentEscapes('\\0 '));
		self::assertSame("\u{FFFD}", CSSSyntaxScanner::decodeCssIdentEscapes('\\110000 '));

		self::assertSame(2, CSSSyntaxScanner::findLastTopLevelImportant('! !important'));
		self::assertSame(4, CSSSyntaxScanner::findLastTopLevelImportant('red !\\69 mportant /* trailing */'));
		self::assertNull(CSSSyntaxScanner::findLastTopLevelImportant('"!important"'));
		self::assertNull(CSSSyntaxScanner::findLastTopLevelImportant('red !important extra'));
	}

	public function testRemoveCommentsPreservesTokenBoundaries(): void
	{
		self::assertSame('div.foo', CSSSyntaxScanner::removeComments('div/*c*/.foo'));
		self::assertSame('.col-12.active', CSSSyntaxScanner::removeComments('.col-12/*c*/.active'));
		self::assertSame('div span', CSSSyntaxScanner::removeComments('div/*c*/span'));
		self::assertSame('12 .5', CSSSyntaxScanner::removeComments('12/*c*/.5'));
		self::assertSame('10px 20px', CSSSyntaxScanner::removeComments('10px/*a*//*b*/20px'));
		self::assertSame('calc(10px+ 5px)', CSSSyntaxScanner::removeComments('calc(10px/*a*/+/*b*/5px)'));
		self::assertSame('rgb(255 0 0)', CSSSyntaxScanner::removeComments('rgb(255/*c*/0 0)'));
		self::assertSame('a{color:red;}', CSSSyntaxScanner::removeComments('a/*c*/{color:red;}'));
		self::assertSame('1e +2', CSSSyntaxScanner::removeComments('1e/*c*/+2'));
		self::assertSame('<! --', CSSSyntaxScanner::removeComments('<!/*c*/--'));
		// Escaped '/' (\/*...*/) is an escaped slash token, NOT a comment opener
		self::assertSame('1e\\/*c*/+2', CSSSyntaxScanner::removeComments('1e\\/*c*/+2'));
		self::assertNull(CSSSyntaxScanner::findMatchingUrlEnd('url("a.png" /*', 3));
		self::assertSame("a \\\nb", CSSSyntaxScanner::removeComments("a/*c*/\\\nb"));
		self::assertSame("a\\\nb", CSSSyntaxScanner::removeComments("a\\\n/*c*/b"));
	}
}
