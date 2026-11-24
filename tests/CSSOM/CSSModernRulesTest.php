<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionDeclarations;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSImportRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMarginRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPageRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPositionTryRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSPropertyRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use PHPUnit\Framework\TestCase;

final class CSSModernRulesTest extends TestCase
{
	public function testParsesFunctionParametersAndDeclarationRuns(): void
	{
		$sheet = CSSStyleSheet::parse(<<<'CSS'
@function --lighter(--color <color>, --amount <number>: 0.2) returns <color> {
  --local: 1;
  result: color-mix(in srgb, var(--color), white var(--amount));
  @media (width > 1000px) { --inside: 2; }
  --after: 3;
}
CSS);

		$rule = $sheet->getCssRules()[0];
		self::assertInstanceOf(CSSFunctionRule::class, $rule);
		self::assertSame('--lighter', $rule->name);
		self::assertSame('<color>', $rule->returnType);
		self::assertSame([
			['name' => '--color', 'type' => '<color>', 'defaultValue' => null],
			['name' => '--amount', 'type' => '<number>', 'defaultValue' => '0.2'],
		], $rule->getParameters());
		self::assertInstanceOf(CSSFunctionDeclarations::class, $rule->cssRules[0]);
		self::assertSame('1', $rule->cssRules[0]->style->getPropertyValue('--local'));
		self::assertSame('color-mix(in srgb, var(--color), white var(--amount))', $rule->cssRules[0]->style->result);
		self::assertInstanceOf(CSSMediaRule::class, $rule->cssRules[1]);
		self::assertInstanceOf(CSSFunctionDeclarations::class, $rule->cssRules[2]);
		self::assertSame([], $sheet->getParseErrors());
	}

	public function testParsesPositionTryRuleDescriptors(): void
	{
		$sheet = CSSStyleSheet::parse('@position-try --custom-left { position-area: left; width: 20%; margin-right: 10px; }');
		$rule = $sheet->getCssRules()[0];

		self::assertInstanceOf(CSSPositionTryRule::class, $rule);
		self::assertSame('--custom-left', $rule->name);
		self::assertSame('left', $rule->style->getPropertyValue('position-area'));
		self::assertSame('20%', $rule->style->getPropertyValue('width'));
		self::assertSame([], $sheet->getParseErrors());
	}

	public function testParsesPageDescriptorsAndMarginBoxRules(): void
	{
		$sheet = CSSStyleSheet::parse('@page :first { size: A4; @top-left-corner { content: "A"; } margin: 1cm; }');
		$rule = $sheet->getCssRules()[0];

		self::assertInstanceOf(CSSPageRule::class, $rule);
		self::assertSame('A4', $rule->style->getPropertyValue('size'));
		self::assertSame('1cm', $rule->style->getPropertyValue('margin'));
		self::assertCount(1, $rule->getMarginRules());
		self::assertInstanceOf(CSSMarginRule::class, $rule->getMarginRules()[0]);
		self::assertSame('"A"', $rule->getMarginRules()[0]->style->getPropertyValue('content'));
		self::assertSame([], $sheet->getParseErrors());
		$roundTrip = CSSStyleSheet::parse($sheet->cssText);
		self::assertSame([], $roundTrip->getParseErrors());
		self::assertSame($sheet->cssText, $roundTrip->cssText);
	}

	public function testDescriptorRulesExposeTypedProperties(): void
	{
		$sheet = CSSStyleSheet::parse(<<<'CSS'
@property --length { syntax: "<length>"; inherits: false; initial-value: 0px; }
@font-face { font-family: Example; src: url(example.woff2); }
CSS);
		$propertyRule = $sheet->getCssRules()[0];
		$fontRule = $sheet->getCssRules()[1];

		self::assertInstanceOf(CSSPropertyRule::class, $propertyRule);
		self::assertSame('--length', $propertyRule->name);
		self::assertSame('<length>', $propertyRule->syntax);
		self::assertFalse($propertyRule->inherits);
		self::assertSame('0px', $propertyRule->initialValue);
		self::assertInstanceOf(CSSFontFaceRule::class, $fontRule);
		self::assertSame('Example', $fontRule->style->getPropertyValue('font-family'));
		self::assertSame([], $sheet->getParseErrors());
	}

	public function testFunctionNestedMediaInsertRuleUsesFunctionMode(): void
	{
		$sheet = CSSStyleSheet::parse('@function --f() { @media screen { result: 1px; } }');
		$fnRule = $sheet->getCssRules()[0];
		self::assertInstanceOf(CSSFunctionRule::class, $fnRule);
		$mediaRule = $fnRule->cssRules[0];
		self::assertInstanceOf(CSSMediaRule::class, $mediaRule);

		$mediaRule->insertRule('@media print { --local: 2px; }', 1);
		$nestedMedia = $mediaRule->cssRules[1];
		self::assertInstanceOf(CSSMediaRule::class, $nestedMedia);
		self::assertInstanceOf(CSSFunctionDeclarations::class, $nestedMedia->cssRules[0]);
		self::assertSame('2px', $nestedMedia->cssRules[0]->style->getPropertyValue('--local'));
	}

	public function testScopeRuleVariantsAndImportMalformedTails(): void
	{
		$sheet = CSSStyleSheet::parse('@scope { a { color: red; } } @scope to (.limit) { a { color: blue; } } @scope (.card) to (.slot) { a { color: green; } }');
		self::assertCount(3, $sheet->cssRules);
		self::assertNull($sheet->cssRules[0]->start);
		self::assertNull($sheet->cssRules[0]->end);
		self::assertNull($sheet->cssRules[1]->start);
		self::assertSame('.limit', $sheet->cssRules[1]->end);
		self::assertSame('.card', $sheet->cssRules[2]->start);
		self::assertSame('.slot', $sheet->cssRules[2]->end);
		self::assertSame([], $sheet->getParseErrors());

		foreach ([
			'@import "a.css" supports(color: red) layer(base);',
			'@import "a.css" layer();',
			'@import "a.css" layer(a) layer(b);',
			'@import "a.css" supports();',
			'@import "a.css" supports(color: red;',
		] as $badImport) {
			$badSheet = CSSStyleSheet::parse($badImport);
			self::assertNotEmpty($badSheet->getParseErrors(), 'Expected parse error for: ' . $badImport);
			self::assertCount(0, $badSheet->cssRules);

			try {
				new CSSImportRule(rtrim(substr($badImport, 8), ';'));
				self::fail('Expected malformed @import prelude to throw InvalidArgumentException: ' . $badImport);
			} catch (\InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
	}
}
