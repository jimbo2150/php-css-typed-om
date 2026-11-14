<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Parser;

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
}
