<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSMediaRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSImportRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSKeyframeRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSKeyframesRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSLayerBlockRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSLayerStatementRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSNestedDeclarations;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSNamespaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleProperties;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use PHPUnit\Framework\TestCase;

final class CSSStyleSheetTest extends TestCase
{
	public function testParsesStyleRulesDeclarationsAndGroupingRules(): void
	{
		$sheet = CSSStyleSheet::parse(
			'h1, h2 { color: red; margin: 0 !important; --payload: fn(a;b) }'
			. '@media (min-width: 10px) { p { width: 10px } }',
		);
		$rules = $sheet->getCssRules();

		self::assertCount(2, $rules);
		self::assertInstanceOf(CSSStyleRule::class, $rules[0]);
		self::assertInstanceOf(CSSStyleProperties::class, $rules[0]->style);
		self::assertSame('h1, h2', $rules[0]->getSelectorText());
		self::assertSame('red', $rules[0]->getStyle()->getPropertyValue('color'));
		self::assertSame('important', $rules[0]->getStyle()->getPropertyPriority('margin'));
		self::assertSame('fn(a;b)', $rules[0]->getStyle()->getPropertyValue('--payload'));
		self::assertInstanceOf(CSSMediaRule::class, $rules[1]);
		self::assertSame(0, count($sheet->getParseErrors()));
	}

	public function testRuleListMutationAndSerialization(): void
	{
		$sheet = CSSStyleSheet::parse('a { color: red }');

		self::assertSame(1, $sheet->insertRule('b { color: blue }', 1));
		self::assertSame("a { color: red; }\nb { color: blue; }", $sheet->getCssText());
		$sheet->deleteRule(0);

		self::assertSame('b { color: blue; }', $sheet->getCssText());
	}

	public function testInvalidRuleInsertionDoesNotChangeSheet(): void
	{
		$sheet = CSSStyleSheet::parse('a { color: red }');
		$before = $sheet->getCssText();

		try {
			$sheet->insertRule('broken {', 1);
			self::fail('Expected malformed rule insertion to throw.');
		} catch (InvalidArgumentException) {
			self::assertSame($before, $sheet->getCssText());
		}
	}

	public function testStylesheetParserRecoversAndReportsMalformedRule(): void
	{
		$sheet = CSSStyleSheet::parse('a { color: red } broken; b { color: blue }');

		self::assertCount(2, $sheet->getCssRules());
		self::assertNotEmpty($sheet->getParseErrors());
		self::assertSame('b', $sheet->getCssRules()[1]->getSelectorText());
	}

	public function testParsesStandaloneAtRuleTypesAndPreservesUnknownRules(): void
	{
		$sheet = CSSStyleSheet::parse(
			'@import url("theme.css") screen;'
			. '@namespace svg url("urn:svg");'
			. '@layer reset, theme;'
			. '@layer components { a { color: red } }'
			. '@keyframes fade { from { opacity: 0 } to { opacity: 1 } }'
			. '@custom-vendor-rule x { arbitrary: syntax; }',
		);

		$rules = $sheet->getCssRules();
		self::assertInstanceOf(CSSImportRule::class, $rules[0]);
		self::assertSame('theme.css', $rules[0]->getHref());
		self::assertInstanceOf(CSSNamespaceRule::class, $rules[1]);
		self::assertSame('svg', $rules[1]->getPrefix());
		self::assertInstanceOf(CSSLayerStatementRule::class, $rules[2]);
		self::assertInstanceOf(CSSLayerBlockRule::class, $rules[3]);
		self::assertInstanceOf(CSSKeyframesRule::class, $rules[4]);
		self::assertInstanceOf(CSSKeyframeRule::class, $rules[4]->getCssRules()[0]);
		$rules[4]->appendRule('50% { opacity: 0.5 }');
		self::assertSame('50%', $rules[4]->findRule('50%')->getKeyText());
		$rules[4]->deleteRule('50%');
		self::assertNull($rules[4]->findRule('50%'));
		self::assertStringStartsWith('@custom-vendor-rule', $rules[5]->getCssText());
		self::assertSame([], $sheet->getParseErrors());
	}

	public function testParsesNestedStyleRules(): void
	{
		$sheet = CSSStyleSheet::parse('article { color: black; &:hover { color: blue } }');
		$rule = $sheet->getCssRules()[0];

		self::assertCount(1, $rule->getCssRules());
		self::assertInstanceOf(CSSStyleRule::class, $rule->getCssRules()[0]);
		self::assertSame('blue', $rule->getCssRules()[0]->getStyle()->getPropertyValue('color'));
		self::assertSame('article { color: black; &:hover { color: blue; } }', $sheet->getCssText());
		self::assertSame([], $sheet->getParseErrors());
	}

	public function testPreservesUnknownSemicolonAtRuleSource(): void
	{
		$source = '@custom-vendor-rule  syntax /* retained */;';
		$sheet = CSSStyleSheet::parse($source);

		self::assertSame($source, $sheet->getCssRules()[0]->getCssText());
		self::assertSame([], $sheet->getParseErrors());
	}

	public function testDeclarationRecoveryReportsSourceOffset(): void
	{
		$sheet = CSSStyleSheet::parse('a { broken; color: red }');

		self::assertSame('red', $sheet->getCssRules()[0]->getStyle()->getPropertyValue('color'));
		self::assertSame(4, $sheet->getParseErrors()[0]['offset']);
	}

	public function testParsesNestedConditionalDeclarationsAndRules(): void
	{
		$sheet = CSSStyleSheet::parse('article { color: black; @media (width > 10px) { color: blue; & > a { color: red } } }');
		$mediaRule = $sheet->getCssRules()[0]->getCssRules()[0];

		self::assertInstanceOf(CSSMediaRule::class, $mediaRule);
		self::assertInstanceOf(CSSNestedDeclarations::class, $mediaRule->getCssRules()[0]);
		self::assertSame('blue', $mediaRule->getCssRules()[0]->getStyle()->getPropertyValue('color'));
		self::assertSame('red', $mediaRule->getCssRules()[1]->getStyle()->getPropertyValue('color'));
		self::assertSame([], $sheet->getParseErrors());
	}

	public function testKeepsDeclarationsAfterNestedRulesInSourceOrder(): void
	{
		$sheet = CSSStyleSheet::parse('a { color: black; &:hover { color: blue } color: green; }');
		$rule = $sheet->getCssRules()[0];

		self::assertSame('black', $rule->getStyle()->getPropertyValue('color'));
		self::assertInstanceOf(CSSNestedDeclarations::class, $rule->getCssRules()[1]);
		self::assertSame('green', $rule->getCssRules()[1]->getStyle()->getPropertyValue('color'));
		self::assertSame('a { color: black; &:hover { color: blue; } color: green; }', $sheet->getCssText());
	}

	public function testStructuredAndOpaqueRulesRoundTrip(): void
	{
		$sheet = CSSStyleSheet::parse('@layer theme { .card { color: red; &:hover { color: blue } } } @future-rule  x { raw: value; }');
		$serialized = $sheet->getCssText();
		$roundTrip = CSSStyleSheet::parse($serialized);

		self::assertSame([], $roundTrip->getParseErrors());
		self::assertSame($serialized, $roundTrip->getCssText());
		self::assertSame($sheet->getCssRules()[1]->getCssText(), $roundTrip->getCssRules()[1]->getCssText());
	}

	public function testDeleteDetachesRuleAndReplaceSyncReplacesContents(): void
	{
		$sheet = CSSStyleSheet::parse('a { color: red }');
		$rule = $sheet->getCssRules()[0];
		self::assertSame($sheet, $rule->parentStyleSheet);

		$sheet->deleteRule(0);
		self::assertNull($rule->parentStyleSheet);
		$sheet->replaceSync('b { color: blue }');
		self::assertSame('b { color: blue; }', $sheet->cssText);
	}

	public function testMediaListMutationUpdatesRuleSerialization(): void
	{
		$sheet = CSSStyleSheet::parse('@media screen { a { color: red } }');
		$mediaRule = $sheet->getCssRules()[0];
		$mediaRule->media->appendMedium('print');

		self::assertSame('screen, print', $mediaRule->conditionText);
		self::assertStringStartsWith('@media screen, print {', $mediaRule->cssText);
	}

	public function testNestedParseDiagnosticUsesStylesheetOffset(): void
	{
		$source = '@media screen { a { broken; color: red } }';
		$sheet = CSSStyleSheet::parse($source);

		self::assertSame(strpos($source, 'broken'), $sheet->getParseErrors()[0]['offset']);
	}
}
