<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Parser;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\CSSOM\MediaList;
use Jimbo2150\PhpCssTypedOm\Parser\CSSMediaQueryParser;
use PHPUnit\Framework\TestCase;

final class CSSMediaQueryParserTest extends TestCase
{
	public function testParsesAndCanonicalizesRangesBooleanLogicAndCustomMedia(): void
	{
		self::assertSame(
			[
				'(400px <= width <= 800px)',
				'(800px >= width > 200px)',
				'(width >= 400px)',
				'(width = 500px)',
				'screen and (min-width: 600px) and ((color) or (hover: hover))',
				'not (prefers-color-scheme: dark)',
				'(--DarkMode) and (--narrow)',
				'(aspect-ratio: 16/9)',
				'screen and (min-width: calc(10px + 20px))',
			],
			CSSMediaQueryParser::parseList(
				'(400px<=width<=800px), (800px>=width>200px), (WIDTH>=400PX), (width=500px), '
				. 'SCREEN AND (MIN-WIDTH:600PX) AND ((COLOR) OR (HOVER:HOVER)), '
				. 'NOT (PREFERS-COLOR-SCHEME:DARK), (--DarkMode) AND (--narrow), '
				. '(aspect-ratio: 16 / 9), screen and (min-width: calc(10px + 20px))',
			),
		);
	}

	public function testPreservesDashedIdentCaseInMediaListDeduplication(): void
	{
		$list = new MediaList('(--DarkMode), (--darkmode), SCREEN, screen');
		self::assertCount(3, $list);
		self::assertSame('(--DarkMode), (--darkmode), screen', $list->mediaText);
		self::assertTrue($list->contains('(--DarkMode)'));
		self::assertTrue($list->contains('(--darkmode)'));
		self::assertTrue($list->contains('PRINT') === false);
		self::assertTrue($list->contains('SCREEN'));

		$list->deleteMedium('(--DarkMode)');
		self::assertSame('(--darkmode), screen', $list->mediaText);
	}

	public function testRejectsInvalidMediaQueryGrammarInStrictMode(): void
	{
		$invalidQueries = [
			'(a) and (b) or (c)',
			'screen and (a) or (b)',
			'not not (color)',
			'only (width > 10px)',
			'layer',
			'not layer',
			'(400px <= width >= 800px)',
			'(400px = width = 800px)',
			'(400px < = width)',
			'(--custom: 1)',
			'(--custom > 10px)',
			'(width:)',
			'()',
			'screen print',
		];

		foreach ($invalidQueries as $bad) {
			try {
				CSSMediaQueryParser::parseList($bad);
				self::fail('Expected invalid media query to throw: ' . $bad);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
	}

	public function testBrowserErrorRecoveryFallsBackToNotAll(): void
	{
		self::assertSame(
			['screen', 'not all', 'not all'],
			CSSMediaQueryParser::parseList('screen, (a) and (b) or (c), (400px <= width >= 800px)', null, true),
		);

		$sheet = CSSStyleSheet::parse(
			'@media screen, (a) and (b) or (c) { a { color: red; } }',
			new CSSParserOptions(browserErrorRecovery: true),
		);
		self::assertSame([], $sheet->getParseErrors());
		self::assertCount(1, $sheet->cssRules);
		$mediaRule = $sheet->cssRules[0];
		self::assertInstanceOf(CSSMediaRule::class, $mediaRule);
		self::assertSame('screen, not all', $mediaRule->conditionText);

		// Post-parse mutations on MediaList remain strict
		$this->expectException(InvalidArgumentException::class);
		$mediaRule->media->appendMedium('(a) and (b) or (c)');
	}

	public function testEnforcesDepthAndNodeLimitsIteratively(): void
	{
		$deep = str_repeat('(', 500) . 'color' . str_repeat(')', 500);
		try {
			CSSMediaQueryParser::parseList($deep, new CSSParserOptions(maxDepth: 64));
			self::fail('Expected maxDepth overflow to throw CSSParserLimitException.');
		} catch (CSSParserLimitException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// Deep valid nesting within default maxDepth (256) succeeds without PHP stack overflow
		$validDeep = str_repeat('(', 200) . 'color' . str_repeat(')', 200);
		$parsed = CSSMediaQueryParser::parseSingle($validDeep);
		self::assertStringStartsWith(str_repeat('(', 200), $parsed);
	}

	public function testSupportsIdentRatioAndCalcValuesInRangesAndBrowserUnbalancedRecovery(): void
	{
		self::assertSame('(resolution = infinite)', CSSMediaQueryParser::parseSingle('(RESOLUTION = INFINITE)'));
		self::assertSame('(aspect-ratio >= 16/9)', CSSMediaQueryParser::parseSingle('(aspect-ratio >= 16/9)'));
		self::assertSame('(width >= calc(10px + 20px))', CSSMediaQueryParser::parseSingle('(width >= calc(10px + 20px))'));

		$sheet = CSSStyleSheet::parse(
			"@media screen, (bad) ) { a { color: red; } }\n@custom-media --broken (bad) );",
			new CSSParserOptions(browserErrorRecovery: true),
		);
		self::assertSame([], $sheet->getParseErrors());
		self::assertCount(2, $sheet->cssRules);
		$media = $sheet->cssRules[0];
		self::assertInstanceOf(CSSMediaRule::class, $media);
		self::assertSame('screen, not all', $media->conditionText);
		self::assertSame('@media screen, not all { a { color: red; } }', $media->cssText);
		self::assertSame('@custom-media --broken not all;', $sheet->cssRules[1]->cssText);
	}
}
