<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleDeclaration;
use PHPUnit\Framework\TestCase;

final class CSSStyleDeclarationTest extends TestCase
{
	public function testParsesAndMutatesOrderedDeclarations(): void
	{
		$style = CSSStyleDeclaration::parse('color: red; margin: 0 !important; --x: fn(a;b);');

		self::assertSame(6, $style->getLength());
		self::assertSame('red', $style->getPropertyValue('COLOR'));
		self::assertSame('important', $style->getPropertyPriority('margin'));
		self::assertSame('fn(a;b)', $style->getPropertyValue('--x'));

		$style->setProperty('color', 'blue');
		$style->removeProperty('margin');

		self::assertSame('color: blue; --x: fn(a;b);', $style->getCssText());
	}

	public function testRejectsInvalidMutationWithoutChangingDeclarations(): void
	{
		$style = CSSStyleDeclaration::parse('color: red');

		try {
			$style->setProperty('bad property', 'blue');
			self::fail('Expected invalid property name to throw.');
		} catch (InvalidArgumentException) {
			self::assertSame('color: red;', $style->getCssText());
		}
	}

	public function testCssTextReplacementIsAtomic(): void
	{
		$style = CSSStyleDeclaration::parse('color: red');

		try {
			$style->setCssText('color: blue; broken');
			self::fail('Expected malformed declaration text to throw.');
		} catch (InvalidArgumentException) {
			self::assertSame('color: red;', $style->cssText);
		}
	}

	public function testIgnoresCommentsOutsideStrings(): void
	{
		$style = CSSStyleDeclaration::parse('color /* comment */ : red; content: "/* retained */";');

		self::assertSame('red', $style->getPropertyValue('color'));
		self::assertSame('"/* retained */"', $style->getPropertyValue('content'));
	}

	public function testRejectsUnbalancedValuesAndEmbeddedPriorityAtomically(): void
	{
		$style = CSSStyleDeclaration::parse('color: red');
		foreach (['rgb(1, 2, 3', 'blue !important'] as $invalidValue) {
			try {
				$style->setProperty('color', $invalidValue);
				self::fail('Expected invalid CSS value to throw.');
			} catch (InvalidArgumentException) {
				self::assertSame('red', $style->getPropertyValue('color'));
			}
		}
	}

	public function testImportantDuplicateDeclarationWins(): void
	{
		$style = CSSStyleDeclaration::parse('color: red !important; color: blue; color: green !important;');

		self::assertSame('green', $style->getPropertyValue('color'));
		self::assertSame('important', $style->getPropertyPriority('color'));
	}

	public function testStylePropertiesSupportCamelCaseAccessors(): void
	{
		$style = new CSSStyleDeclaration();
		/** @phpstan-ignore property.notFound */
		$style->backgroundColor = 'red';
		/** @phpstan-ignore property.notFound */
		$style->cssFloat = 'left';

		self::assertSame('red', $style->getPropertyValue('background-color'));
		self::assertSame('left', $style->getPropertyValue('float'));
		self::assertSame('red', $style->backgroundColor);
	}

	public function testIssetCachedStyleMapAndImportMediaSync(): void
	{
		$sheet = \Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet::parse('@import url("tokens.css") screen; .card { color: red; --empty: ; }');
		$importRule = $sheet->cssRules[0];
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\CSSOM\CSSImportRule::class, $importRule);
		self::assertTrue(isset($sheet->cssRules));
		self::assertTrue(isset($importRule->cssText));
		self::assertTrue(isset($importRule->media));

		$importRule->media->appendMedium('print');
		self::assertSame('@import url("tokens.css") screen, print;', $importRule->cssText);

		$card = $sheet->cssRules[1];
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule::class, $card);
		self::assertSame($card->styleMap, $card->styleMap);
		$card->styleMap->set('margin', '10px 20px');
		$card->styleMap->set('background-image', 'url(a.png), url(b.png)');
		$card->style->setProperty('transition-duration', '0.2s', 'important');
		$card->styleMap->append('transition-duration', '0.5s');
		self::assertSame('0.2s, 0.5s', $card->style->getPropertyValue('transition-duration'));
		self::assertSame('important', $card->style->getPropertyPriority('transition-duration'));
		try {
			$card->styleMap->append('padding', '8px');
			self::fail('Expected append() on non-list property padding to throw InvalidArgumentException.');
		} catch (\InvalidArgumentException) {
		}
		self::assertSame('', (string) $card->styleMap->get('--empty'));
		self::assertSame('10px 20px', (string) $card->styleMap->get('margin'));
		self::assertSame('url(a.png)', (string) $card->styleMap->get('background-image'));
	}

	public function testPreservesVerticalTabAndCustomPropertyCommasWhileSupportingGlobalKeywords(): void
	{
		$sheet = \Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet::parse("a {\x0Bmargin: 0; color: blue; }\nb {\fmargin: 4px; }");
		self::assertNotEmpty($sheet->getParseErrors());
		$secondRule = $sheet->cssRules[1];
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule::class, $secondRule);
		self::assertSame('4px', $secondRule->style->getPropertyValue('margin'));

		$cleanStyle = CSSStyleDeclaration::parse("--font-stack: Inter, sans-serif; --reset: inherit;");
		$map = new \Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap($cleanStyle);

		self::assertSame(['Inter, sans-serif'], array_map('strval', $map->getAll('--font-stack')));
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue::class, $map->get('--reset'));
		self::assertSame('inherit', (string) $map->get('--reset'));

		$this->expectException(\Error::class);
		/** @phpstan-ignore property.notFound */
		$_ = $map->unknownProp;
	}

	public function testStripsLeadingBomAndSupportsBrowserErrorRecovery(): void
	{
		$bomDecl = CSSStyleDeclaration::parse("\xEF\xBB\xBFcolor: red;\r\nmargin: 0;");
		self::assertSame('red', $bomDecl->getPropertyValue('color'));
		self::assertSame('0', $bomDecl->getPropertyValue('margin'));

		$diags = [];
		$browserDecl = CSSStyleDeclaration::parse(
			"\xEF\xBB\xBF--nul: r\x00ed; color: r\x00ed; content: \"abc\\; width: calc(10px; background: url(a.png",
			false,
			$diags,
			new \Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions(browserErrorRecovery: true),
		);
		self::assertSame("r\u{FFFD}ed", $browserDecl->getPropertyValue('--nul'));
		self::assertSame('', $browserDecl->getPropertyValue('color'));

		$unclosedStr = CSSStyleDeclaration::parse(
			'content: "hello\\',
			false,
			$diags,
			new \Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions(browserErrorRecovery: true),
		);
		self::assertSame('"hello"', $unclosedStr->getPropertyValue('content'));
		self::assertSame('content: "hello";', $unclosedStr->getCssText());

		$unclosedCalc = CSSStyleDeclaration::parse(
			'width: calc(10px + 20px',
			false,
			$diags,
			new \Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions(browserErrorRecovery: true),
		);
		self::assertSame('calc(10px + 20px)', $unclosedCalc->getPropertyValue('width'));
		self::assertSame('width: calc(10px + 20px);', $unclosedCalc->getCssText());

		// Token-path !important with whitespace/comments, duplicate detection, and escaped url()
		$tokenImportant = CSSStyleDeclaration::parse("content: \"a\" ! important; background: \\75 rl(a.png) !\timportant; color: red /* c */ ! /* c */ important; mask: \\75 rl(\"https://example.test\");");
		self::assertSame('"a"', $tokenImportant->getPropertyValue('content'));
		self::assertSame('important', $tokenImportant->getPropertyPriority('content'));
		self::assertSame('important', $tokenImportant->getPropertyPriority('background'));
		self::assertSame('important', $tokenImportant->getPropertyPriority('color'));
		self::assertSame('\75 rl("https://example.test")', $tokenImportant->getPropertyValue('mask'));

		$dupDiags = [];
		$dupImportant = CSSStyleDeclaration::parse('content: "a" !important ! important; color: blue ! important ! important;', false, $dupDiags);
		self::assertSame('', $dupImportant->getPropertyValue('content'));
		self::assertSame('', $dupImportant->getPropertyValue('color'));
		self::assertCount(2, $dupDiags);

		$unclosedImportantCustom = CSSStyleDeclaration::parse(
			'--x: calc(10px !important',
			false,
			$diags,
			new \Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions(browserErrorRecovery: true),
		);
		self::assertSame('calc(10px !important)', $unclosedImportantCustom->getPropertyValue('--x'));
		self::assertSame('', $unclosedImportantCustom->getPropertyPriority('--x'));
		self::assertSame('--x: calc(10px !important);', $unclosedImportantCustom->getCssText());

		// Standard property drops invalid `calc(10px !important)` under property grammar validation
		$unclosedImportantWidth = CSSStyleDeclaration::parse(
			'width: calc(10px !important',
			false,
			$diags,
			new \Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserOptions(browserErrorRecovery: true),
		);
		self::assertSame('', $unclosedImportantWidth->getPropertyValue('width'));
	}
}
