<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionDeclarations;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFunctionRule;
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
}
