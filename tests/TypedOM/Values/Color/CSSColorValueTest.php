<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Color;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColor;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSHSL;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSHWB;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSLab;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSLCH;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSOKLab;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSOKLCH;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSRGB;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnparsedValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use PHPUnit\Framework\TestCase;

final class CSSColorValueTest extends TestCase
{
	public function testParsesHexAndRgbUsingW3cColor5PercentageScale(): void
	{
		$hex = CSSColorValue::parse('#ff0000');
		self::assertInstanceOf(CSSRGB::class, $hex);
		self::assertEqualsWithDelta(100.0, $hex->r->value, 1e-9);
		self::assertSame('percent', $hex->r->unit);
		self::assertEqualsWithDelta(0.0, $hex->g->value, 1e-9);
		self::assertEqualsWithDelta(100.0, $hex->alpha->value, 1e-9);
		self::assertSame('rgb(100% 0% 0%)', (string) $hex);

		$hexAlpha = CSSColorValue::parse('#0f08');
		self::assertInstanceOf(CSSRGB::class, $hexAlpha);
		self::assertEqualsWithDelta(100.0, $hexAlpha->g->value, 1e-9);
		self::assertEqualsWithDelta((0x88 / 255.0) * 100.0, $hexAlpha->alpha->value, 1e-6);

		// Legacy rgb() 0..255 maps to 0%..100%
		$legacy = CSSColorValue::parse('rgb(255, 0, 0)');
		self::assertInstanceOf(CSSRGB::class, $legacy);
		self::assertSame('percent', $legacy->r->unit);
		self::assertEqualsWithDelta(100.0, $legacy->r->value, 1e-9);

		// Bare constructor floats 0..1 map to 0%..100%
		$constructed = new CSSRGB(1, 0, 0.5, 0.5);
		self::assertSame('rgb(100% 0% 50% / 50%)', (string) $constructed);

		// Two-argument parse matches CSSStyleValue::parse()
		$twoArg = CSSColorValue::parse('color', 'rgba(0, 255, 0, 0.25)');
		self::assertInstanceOf(CSSRGB::class, $twoArg);
		self::assertEqualsWithDelta(25.0, $twoArg->alpha->value, 1e-9);
	}

	public function testParsesAllColorSpacesAndMissingNoneComponents(): void
	{
		$hsl = CSSColorValue::parse('hsl(120deg 100% 50% / 50%)');
		self::assertInstanceOf(CSSHSL::class, $hsl);
		self::assertSame('hsl(120deg 100% 50% / 50%)', (string) $hsl);

		$hwb = CSSColorValue::parse('hwb(240 0% 0%)');
		self::assertInstanceOf(CSSHWB::class, $hwb);
		self::assertSame('hwb(240deg 0% 0%)', (string) $hwb);

		$lab = CSSColorValue::parse('lab(50% 40 -20)');
		self::assertInstanceOf(CSSLab::class, $lab);
		self::assertSame('lab(50% 40 -20)', (string) $lab);

		$lch = CSSColorValue::parse('lch(50% 60 180deg)');
		self::assertInstanceOf(CSSLCH::class, $lch);
		self::assertSame('lch(50% 60 180deg)', (string) $lch);

		$oklab = CSSColorValue::parse('oklab(0.6 0.1 -0.1)');
		self::assertInstanceOf(CSSOKLab::class, $oklab);
		self::assertEqualsWithDelta(60.0, $oklab->l->value, 1e-9);

		$oklch = CSSColorValue::parse('oklch(70% 0.15 none)');
		self::assertInstanceOf(CSSOKLCH::class, $oklch);
		self::assertInstanceOf(CSSKeywordValue::class, $oklch->h);
		self::assertSame('none', $oklch->h->value);

		$p3 = CSSColorValue::parse('color(display-p3 1 0.5 0 / 80%)');
		self::assertInstanceOf(CSSColor::class, $p3);
		self::assertSame('display-p3', $p3->colorSpace->value);
		self::assertCount(3, $p3->channels);
		self::assertSame('color(display-p3 1 0.5 0 / 80%)', (string) $p3);
	}

	public function testColorSpaceConversionsMatchCssColor4ReferenceVectors(): void
	{
		// Pure sRGB red -> HSL, HWB, Lab, LCH, OKLab, OKLCH, and back to sRGB
		$red = new CSSRGB(1, 0, 0);

		$hsl = $red->toHSL();
		self::assertEqualsWithDelta(0.0, $hsl->h->value, 1e-6);
		self::assertEqualsWithDelta(100.0, $hsl->s->value, 1e-6);
		self::assertEqualsWithDelta(50.0, $hsl->l->value, 1e-6);

		$hwb = $red->toHWB();
		self::assertEqualsWithDelta(0.0, $hwb->h->value, 1e-6);
		self::assertEqualsWithDelta(0.0, $hwb->w->value, 1e-6);
		self::assertEqualsWithDelta(0.0, $hwb->b->value, 1e-6);

		// CSS Color 4 reference for sRGB red in Lab D50: ~(54.2917%, 80.8125, 69.8850)
		$lab = $red->toLab();
		self::assertEqualsWithDelta(54.2917, $lab->l->value, 1e-2);
		self::assertEqualsWithDelta(80.8125, $lab->a->value, 1e-2);
		self::assertEqualsWithDelta(69.8850, $lab->b->value, 1e-2);

		// CSS Color 4 reference for sRGB red in OKLab: ~(62.7955%, 0.224863, 0.125846)
		$oklab = $red->toOKLab();
		self::assertEqualsWithDelta(62.7955, $oklab->l->value, 1e-2);
		self::assertEqualsWithDelta(0.224863, $oklab->a->value, 1e-4);
		self::assertEqualsWithDelta(0.125846, $oklab->b->value, 1e-4);

		// Round-trip OKLCH -> sRGB
		$roundTrip = $red->toOKLCH()->toRGB();
		self::assertEqualsWithDelta(100.0, $roundTrip->r->value, 1e-4);
		self::assertEqualsWithDelta(0.0, $roundTrip->g->value, 1e-4);
		self::assertEqualsWithDelta(0.0, $roundTrip->b->value, 1e-4);

		// Generic to('display-p3') -> toRGB() round-trip
		$p3RoundTrip = $red->to('display-p3')->toRGB();
		self::assertEqualsWithDelta(100.0, $p3RoundTrip->r->value, 1e-4);
		self::assertEqualsWithDelta(0.0, $p3RoundTrip->g->value, 1e-4);
		self::assertEqualsWithDelta(0.0, $p3RoundTrip->b->value, 1e-4);
	}

	public function testRejectsInvalidSyntaxAndFallsBackInStyleValueParse(): void
	{
		// Relative color syntax & var() fall back to CSSUnparsedValue in CSSStyleValue::parse()
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('color', 'rgb(from red r g b / 50%)'));
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('color', 'rgb(var(--r) 0 0)'));

		// Legacy comma rgb() rejects mixing numbers & percentages and rejects `none`
		foreach (['rgb(255, 50%, 0)', 'rgb(none, 0, 0)', 'rgb(255, 0, 0 / 50%)', 'oklch(50%, 0.2, 30)'] as $invalid) {
			try {
				CSSColorValue::parse($invalid);
				self::fail('Expected invalid color syntax to throw: ' . $invalid);
			} catch (InvalidArgumentException) {
				self::assertTrue(true);
			}
		}

		// Custom color space allowed only for --* dashed ident, and conversion throws
		$custom = CSSColorValue::parse('color(--my-cmyk 0 0.5 1 0)');
		self::assertInstanceOf(CSSColor::class, $custom);
		self::assertSame('--my-cmyk', $custom->colorSpace->value);

		try {
			$custom->toRGB();
			self::fail('Expected custom color space conversion to throw.');
		} catch (InvalidArgumentException) {
			self::assertTrue(true);
		}

		// Unresolved math channel rejects conversion
		$mathRgb = new CSSRGB(CSSNumericValue::parse('calc(50% + 10%)'), 0, 0);
		$this->expectException(InvalidArgumentException::class);
		$mathRgb->toLab();
	}
}
