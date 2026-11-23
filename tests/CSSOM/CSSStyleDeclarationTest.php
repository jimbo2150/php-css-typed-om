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

		self::assertSame(3, $style->getLength());
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
		$style->backgroundColor = 'red';
		$style->cssFloat = 'left';

		self::assertSame('red', $style->getPropertyValue('background-color'));
		self::assertSame('left', $style->getPropertyValue('float'));
		self::assertSame('red', $style->backgroundColor);
	}

	public function testIssetCachedStyleMapAndImportMediaSync(): void
	{
		$sheet = \Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet::parse('@import url("tokens.css") screen; .card { color: red; --empty: ; }');
		$importRule = $sheet->cssRules[0];
		self::assertTrue(isset($sheet->cssRules));
		self::assertTrue(isset($importRule->cssText));
		self::assertTrue(isset($importRule->media));

		$importRule->media->appendMedium('print');
		self::assertSame('@import url("tokens.css") screen, print;', $importRule->cssText);

		$card = $sheet->cssRules[1];
		self::assertSame($card->styleMap, $card->styleMap);
		$card->styleMap->set('margin', '10px 20px');
		$card->styleMap->set('background-image', 'url(a.png), url(b.png)');
		$card->style->setProperty('padding', '4px', 'important');
		$card->styleMap->append('padding', '8px');
		self::assertSame('4px, 8px', $card->style->getPropertyValue('padding'));
		self::assertSame('important', $card->style->getPropertyPriority('padding'));
		self::assertSame('', (string) $card->styleMap->get('--empty'));
		self::assertSame('10px 20px', (string) $card->styleMap->get('margin'));
		self::assertSame('url(a.png)', (string) $card->styleMap->get('background-image'));
	}

	public function testPreservesVerticalTabAndCustomPropertyCommasWhileSupportingGlobalKeywords(): void
	{
		$sheet = \Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet::parse("a {\x0Bmargin: 0; color: blue; }\nb {\fmargin: 4px; }");
		self::assertNotEmpty($sheet->getParseErrors());
		self::assertSame('4px', $sheet->cssRules[1]->style->getPropertyValue('margin'));

		$cleanStyle = CSSStyleDeclaration::parse("--font-stack: Inter, sans-serif; --reset: inherit;");
		$map = new \Jimbo2150\PhpCssTypedOm\TypedOM\StylePropertyMap($cleanStyle);

		self::assertSame(['Inter, sans-serif'], array_map('strval', $map->getAll('--font-stack')));
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue::class, $map->get('--reset'));
		self::assertSame('inherit', (string) $map->get('--reset'));

		$this->expectException(\Error::class);
		/** @phpstan-ignore-next-line */
		$map->unknownProp;
	}
}
