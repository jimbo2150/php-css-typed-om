<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\CSSOM;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceDescriptors;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSFontFaceRule;
use Jimbo2150\PhpCssTypedOm\CSSOM\CSSStyleSheet;
use Jimbo2150\PhpCssTypedOm\CSSOM\FontFace;
use PHPUnit\Framework\TestCase;

final class FontFaceTest extends TestCase
{
	public function testConstructsDefaultsMutatesAndConvertsToCssFontFaceRule(): void
	{
		$face = new FontFace('"Inter"', 'url("/fonts/inter.woff2") format("woff2")', [
			'weight' => '100 900',
			'display' => 'swap',
			'variant' => 'small-caps',
		]);

		self::assertSame('"Inter"', $face->family);
		self::assertSame('url("/fonts/inter.woff2") format("woff2")', $face->source);
		self::assertSame('unloaded', $face->status);
		self::assertSame('normal', $face->style);
		self::assertSame('100 900', $face->weight);
		self::assertSame('swap', $face->display);
		self::assertSame('small-caps', $face->variant);
		self::assertSame('U+0-10FFFF', $face->unicodeRange);

		// toCSSFontFaceRule omits non-CSS-Fonts-4 `variant` so strict CSSFontFaceDescriptors succeeds
		$rule = $face->toCSSFontFaceRule();
		self::assertInstanceOf(CSSFontFaceRule::class, $rule);
		self::assertSame('"Inter"', $rule->style->fontFamily);
		self::assertSame('100 900', $rule->style->fontWeight);
		self::assertSame('swap', $rule->style->fontDisplay);
		self::assertSame('', $rule->style->getPropertyValue('font-variant'));

		// Round-trip via parsed stylesheet
		$sheet = CSSStyleSheet::parse($rule->cssText);
		self::assertSame([], $sheet->getParseErrors());
		$ruleNode = $sheet->cssRules[0];
		self::assertInstanceOf(CSSFontFaceRule::class, $ruleNode);
		$parsed = FontFace::fromCSSFontFaceRule($ruleNode);
		self::assertSame('"Inter"', $parsed->family);
		self::assertSame('100 900', $parsed->weight);
		self::assertSame('swap', $parsed->display);
	}

	public function testValidatesDescriptorsStatusImmutabilityAndMissingRequiredRuleDescriptors(): void
	{
		$face = new FontFace('Demo', 'url("demo.woff2")');
		$threw = false;
		try {
			$face->__set('status', 'loaded');
		} catch (Error $e) {
			$threw = true;
			self::assertStringContainsString('status', $e->getMessage());
			self::assertSame('unloaded', $face->status);
		}
		self::assertTrue($threw);

		try {
			new FontFace('Demo', 'url("demo.woff2")', ['unknown' => '1']);
			self::fail('Expected unknown descriptor key to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		try {
			$face->__set('weight', '700; color: red');
			self::fail('Expected CSS injection in descriptor to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
			self::assertSame('normal', $face->weight);
		}

		// fromCSSFontFaceRule requires both font-family and src
		$incomplete = new CSSFontFaceRule(new CSSFontFaceDescriptors('font-family: MissingSrc;'));
		$this->expectException(InvalidArgumentException::class);
		FontFace::fromCSSFontFaceRule($incomplete);
	}
}
