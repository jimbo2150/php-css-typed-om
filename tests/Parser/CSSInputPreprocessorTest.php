<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Parser;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\Parser\CSSInputPreprocessor;
use PHPUnit\Framework\TestCase;

final class CSSInputPreprocessorTest extends TestCase
{
	public function testCleanAsciiUsesFastPathAndPreservesString(): void
	{
		$clean = "div { color: red;\n\tmargin: 0; }";
		self::assertFalse(CSSInputPreprocessor::needsPreprocessing($clean));
		self::assertSame($clean, CSSInputPreprocessor::preprocess($clean));
		self::assertSame('', CSSInputPreprocessor::preprocess(''));
		self::assertFalse(CSSInputPreprocessor::needsPreprocessing(''));
	}

	public function testStripsSingleLeadingUtf8BomOnly(): void
	{
		$singleBom = "\xEF\xBB\xBFbody { color: red; }";
		self::assertTrue(CSSInputPreprocessor::needsPreprocessing($singleBom));
		self::assertSame('body { color: red; }', CSSInputPreprocessor::preprocess($singleBom));

		$doubleBom = "\xEF\xBB\xBF\xEF\xBB\xBFbody {}";
		self::assertSame("\xEF\xBB\xBFbody {}", CSSInputPreprocessor::preprocess($doubleBom));
	}

	public function testNormalizesLineEndingsAndNulBytes(): void
	{
		$input = "a\r\nb\rc\fd\x00e\x00";
		self::assertTrue(CSSInputPreprocessor::needsPreprocessing($input));
		self::assertSame("a\nb\nc\nd\u{FFFD}e\u{FFFD}", CSSInputPreprocessor::preprocess($input));
	}

	public function testPreservesValidUtf8AndReplacesSurrogatesAndIllFormedMaximalSubparts(): void
	{
		$validUtf8 = ".café::before { content: '日本語😀'; }";
		self::assertFalse(CSSInputPreprocessor::needsPreprocessing($validUtf8));
		self::assertSame($validUtf8, CSSInputPreprocessor::preprocess($validUtf8));

		// UTF-8 encoded surrogates (U+D800, U+DC00, U+DFFF) -> 3-byte invalid lead/second -> 3 U+FFFDs per surrogate
		self::assertSame("\u{FFFD}\u{FFFD}\u{FFFD}", CSSInputPreprocessor::preprocess("\xED\xA0\x80"));
		self::assertSame("\u{FFFD}\u{FFFD}\u{FFFD}", CSSInputPreprocessor::preprocess("\xED\xBF\xBF"));

		// Overlong encodings & invalid lead bytes follow WHATWG maximal subpart length rules
		self::assertSame("\u{FFFD}\u{FFFD}a", CSSInputPreprocessor::preprocess("\xC0\xAFa"));
		self::assertSame("\u{FFFD}\u{FFFD}a", CSSInputPreprocessor::preprocess("\xE0\x80a"));
		self::assertSame("\u{FFFD}a", CSSInputPreprocessor::preprocess("\xF0\x90\x80a"));
		self::assertSame("\u{FFFD}x", CSSInputPreprocessor::preprocess("\xFFx"));
		self::assertSame("a\u{FFFD}", CSSInputPreprocessor::preprocess("a\xC2"));
	}

	public function testEnforcesMaxBytesBeforeAndDuringThreeTimesAmplification(): void
	{
		$tight = new CSSParserOptions(maxBytes: 10);
		self::assertSame("abc\u{FFFD}def", CSSInputPreprocessor::preprocess("abc\x00def", $tight)); // 9 bytes <= 10

		try {
			CSSInputPreprocessor::preprocess(str_repeat('a', 11), $tight);
			self::fail('Expected raw maxBytes overflow to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// 4 NUL bytes expand to 12 bytes (> 10)
		try {
			CSSInputPreprocessor::preprocess("\x00\x00\x00\x00", $tight);
			self::fail('Expected 3x NUL expansion overflow to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
	}

	public function testValidUtf8FastPathsAndNeedsPreprocessingParity(): void
	{
		// Valid UTF-8 with BOM (no CRLF/FF/NUL)
		$bomUtf8 = "\xEF\xBB\xBF.カード { content: \"café 😀\"; }";
		self::assertTrue(CSSInputPreprocessor::needsPreprocessing($bomUtf8));
		self::assertSame(".カード { content: \"café 😀\"; }", CSSInputPreprocessor::preprocess($bomUtf8));

		// Valid UTF-8 with CRLF/CR/FF (no NUL)
		$crlfUtf8 = "\xEF\xBB\xBF.café {\r\n\tcontent: \"日本語\";\r\f}";
		self::assertTrue(CSSInputPreprocessor::needsPreprocessing($crlfUtf8));
		self::assertSame(".café {\n\tcontent: \"日本語\";\n\n}", CSSInputPreprocessor::preprocess($crlfUtf8));

		// Valid UTF-8 containing NUL + BOM + 3x maxBytes check
		$nulUtf8 = "\xEF\xBB\xBF.café { a: \"x\x00y\"; }";
		self::assertTrue(CSSInputPreprocessor::needsPreprocessing($nulUtf8));
		self::assertSame(".café { a: \"x\u{FFFD}y\"; }", CSSInputPreprocessor::preprocess($nulUtf8));

		// Surrogate and overlong fail C-level check and report needsPreprocessing() === true
		self::assertTrue(CSSInputPreprocessor::needsPreprocessing("\xED\xA0\x80"));
		self::assertTrue(CSSInputPreprocessor::needsPreprocessing("\xC0\xAF"));

		$tight = new CSSParserOptions(maxBytes: 12);
		self::assertSame("café\u{FFFD}x", CSSInputPreprocessor::preprocess("café\x00x", $tight)); // 5 + 3 + 1 = 9 <= 12
		try {
			CSSInputPreprocessor::preprocess("café\x00\x00x", $tight); // 5 + 6 + 1 = 12 ok; add one more char -> 13 > 12
			CSSInputPreprocessor::preprocess("café\x00\x00xy", $tight);
			self::fail('Expected valid UTF-8 with NUL 3x expansion to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}
	}
}
