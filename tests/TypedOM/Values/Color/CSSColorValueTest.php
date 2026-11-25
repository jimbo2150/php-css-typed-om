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
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// Custom color space allowed only for --* dashed ident, and conversion throws
		$custom = CSSColorValue::parse('color(--my-cmyk 0 0.5 1 0)');
		self::assertInstanceOf(CSSColor::class, $custom);
		self::assertSame('--my-cmyk', $custom->colorSpace->value);

		try {
			$custom->toRGB();
			self::fail('Expected custom color space conversion to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// Unresolved math channel rejects conversion
		$mathRgb = new CSSRGB(CSSNumericValue::parse('calc(50% + 10%)'), 0, 0);
		$this->expectException(InvalidArgumentException::class);
		$mathRgb->toLab();
	}

	public function testScaledRgbMathChannelsColorSpaceValidationAndPropertyHooks(): void
	{
		$calcRgb = CSSColorValue::parse('rgb(calc(255 * 0.5) 0 0)');
		self::assertInstanceOf(CSSRGB::class, $calcRgb);
		self::assertSame('percent', $calcRgb->r->getNumericCategory());
		self::assertInstanceOf(CSSNumericValue::class, $calcRgb->alpha);

		$lch = CSSColorValue::parse('lch(50% 40 120deg)');
		$lchRgb = $lch->toRGB();
		self::assertEqualsWithDelta(38.756, $lchRgb->r->value, 0.05);
		$lchRoundTrip = $lchRgb->toLCH();
		self::assertEqualsWithDelta(50.0, $lchRoundTrip->l->value, 0.05);
		self::assertEqualsWithDelta(40.0, $lchRoundTrip->c->value, 0.05);
		self::assertEqualsWithDelta(120.0, $lchRoundTrip->h->value, 0.05);

		$oklch = CSSColorValue::parse('oklch(70% 0.1 150deg)');
		$oklchRgb = $oklch->toRGB();
		self::assertEqualsWithDelta(43.626, $oklchRgb->r->value, 0.05);
		$oklchRoundTrip = $oklchRgb->toOKLCH();
		self::assertEqualsWithDelta(70.0, $oklchRoundTrip->l->value, 0.05);
		self::assertEqualsWithDelta(0.1, $oklchRoundTrip->c->value, 0.01);
		self::assertEqualsWithDelta(150.0, $oklchRoundTrip->h->value, 0.1);

		$hwbRgb = CSSColorValue::parse('hwb(120deg 20% 20%)')->toRGB();
		self::assertEqualsWithDelta(20.0, $hwbRgb->r->value, 0.01);
		self::assertEqualsWithDelta(80.0, $hwbRgb->g->value, 0.01);

		$custom3 = new CSSColor('--tri', [0.1, 0.2, 0.3]);
		$spaceRef = $custom3->colorSpace;
		self::assertSame('--tri', $spaceRef->value);
		$custom3->colorSpace = 'srgb';
		self::assertSame('srgb', $custom3->colorSpace->value);
		self::assertSame('--tri', $spaceRef->value);

		$clonedRgb = clone CSSColorValue::parse('rgb(10 20 30 / 0.5)');
		self::assertInstanceOf(CSSRGB::class, $clonedRgb);

		$custom4 = new CSSColor('--quad', [0.1, 0.2, 0.3, 0.4]);
		$this->expectException(InvalidArgumentException::class);
		$custom4->colorSpace = 'srgb';
	}

	public function testBradfordWhitepointExactRoundTripCommentBoundariesAndMathScaling(): void
	{
		// Exact W3C D65 whitepoint maps to exact W3C D50 whitepoint
		$d65 = new CSSColor('xyz-d65', [0.3127 / 0.3290, 1.0, (1.0 - 0.3127 - 0.3290) / 0.3290]);
		/** @var CSSColor $d50 */
		$d50 = $d65->to('xyz-d50');
		self::assertEqualsWithDelta(0.3457 / 0.3585, $d50->channels[0]->value, 1e-12);
		self::assertEqualsWithDelta(1.0, $d50->channels[1]->value, 1e-12);
		self::assertEqualsWithDelta((1.0 - 0.3457 - 0.3585) / 0.3585, $d50->channels[2]->value, 1e-12);

		// Short-circuit toPredefinedColor preserves original channels (including none / percent)
		$origSrgb = CSSColorValue::parse('color(srgb 10% 0.2 none)');
		$sameSrgb = $origSrgb->to('srgb');
		self::assertSame('color(srgb 10% 0.2 none)', (string) $sameSrgb);
		self::assertNotSame($origSrgb, $sameSrgb);

		// Whitespace before '(' rejected; comments inside rgb() separate tokens; inner calc(10% +/*c*/5%) still rejected
		$commentRgb = CSSColorValue::parse('rgb(100%/*c*/0% 0%)');
		self::assertSame('rgb(100% 0% 0%)', (string) $commentRgb);
		foreach ([
			'rgb (1 2 3)',
			'rgb(calc(10% +/*c*/5%) 0% 0%)',
		] as $badColor) {
			try {
				CSSColorValue::parse($badColor);
				self::fail('Expected invalid color syntax to throw: ' . $badColor);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// Number-typed CSSMathValue channels scale to percent where appropriate and reject percentHint
		$numMath = \Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser::parse('calc(0.2 + 0.3)');
		$scaledRgb = new CSSRGB($numMath, 0, 0, $numMath);
		self::assertSame('percent', $scaledRgb->r->getNumericCategory());
		self::assertSame('percent', $scaledRgb->alpha->getNumericCategory());
		$parsedOklabMath = CSSColorValue::parse('oklab(calc(0.2 + 0.3) 0 0 / calc(0.2 + 0.3))');
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Color\CSSOKLab::class, $parsedOklabMath);
		self::assertSame('percent', $parsedOklabMath->l->getNumericCategory());
		self::assertSame('percent', $parsedOklabMath->alpha->getNumericCategory());

		try {
			new CSSRGB(
				new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct([
					CSSNumericValue::parse('1'),
					new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum([
						CSSNumericValue::parse('10px'),
						CSSNumericValue::parse('20%'),
					]),
					new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert([CSSNumericValue::parse('10px')]),
				]),
				0,
				0,
			);
			self::fail('Expected percentHint-bearing channel to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// Clone isolation across all 8 CSSColorValue subclasses (in-place channel & alpha mutation without setter replacement)
		$rgb = new CSSRGB(1, 0, 0);
		$rgbClone = clone $rgb;
		self::assertNotSame($rgb->r, $rgbClone->r);
		self::assertNotSame($rgb->alpha, $rgbClone->alpha);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $rgbClone->r);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $rgbClone->alpha);
		$rgbClone->r->value = 25.0;
		$rgbClone->alpha->value = 20.0;
		self::assertEqualsWithDelta(100.0, $rgb->r->value, 1e-9);
		self::assertEqualsWithDelta(100.0, $rgb->alpha->value, 1e-9);

		$hsl = new CSSHSL(120, 1.0, 0.5);
		$hslClone = clone $hsl;
		self::assertNotSame($hsl->h, $hslClone->h);
		self::assertNotSame($hsl->alpha, $hslClone->alpha);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $hslClone->h);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $hslClone->alpha);
		$hslClone->h->value = 240.0;
		$hslClone->alpha->value = 20.0;
		self::assertEqualsWithDelta(120.0, $hsl->h->value, 1e-9);
		self::assertEqualsWithDelta(100.0, $hsl->alpha->value, 1e-9);

		$hwb = new CSSHWB(60, 0.1, 0.2);
		$hwbClone = clone $hwb;
		self::assertNotSame($hwb->w, $hwbClone->w);
		self::assertNotSame($hwb->alpha, $hwbClone->alpha);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $hwbClone->w);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $hwbClone->alpha);
		$hwbClone->w->value = 50.0;
		$hwbClone->alpha->value = 20.0;
		self::assertEqualsWithDelta(0.1, $hwb->w->value, 1e-9);
		self::assertEqualsWithDelta(100.0, $hwb->alpha->value, 1e-9);

		$lab = new CSSLab(50, 10, -10);
		$labClone = clone $lab;
		self::assertNotSame($lab->a, $labClone->a);
		self::assertNotSame($lab->alpha, $labClone->alpha);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $labClone->a);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $labClone->alpha);
		$labClone->a->value = 40.0;
		$labClone->alpha->value = 20.0;
		self::assertEqualsWithDelta(10.0, $lab->a->value, 1e-9);
		self::assertEqualsWithDelta(100.0, $lab->alpha->value, 1e-9);

		$lch = new CSSLCH(50, 20, 90);
		$lchClone = clone $lch;
		self::assertNotSame($lch->c, $lchClone->c);
		self::assertNotSame($lch->alpha, $lchClone->alpha);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $lchClone->c);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $lchClone->alpha);
		$lchClone->c->value = 80.0;
		$lchClone->alpha->value = 20.0;
		self::assertEqualsWithDelta(20.0, $lch->c->value, 1e-9);
		self::assertEqualsWithDelta(100.0, $lch->alpha->value, 1e-9);

		$oklab = new CSSOKLab(0.5, 0.1, -0.1);
		$oklabClone = clone $oklab;
		self::assertNotSame($oklab->b, $oklabClone->b);
		self::assertNotSame($oklab->alpha, $oklabClone->alpha);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $oklabClone->b);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $oklabClone->alpha);
		$oklabClone->b->value = 0.2;
		$oklabClone->alpha->value = 20.0;
		self::assertEqualsWithDelta(-0.1, $oklab->b->value, 1e-9);
		self::assertEqualsWithDelta(100.0, $oklab->alpha->value, 1e-9);

		$oklch = new CSSOKLCH(0.6, 0.1, 180);
		$oklchClone = clone $oklch;
		self::assertNotSame($oklch->l, $oklchClone->l);
		self::assertNotSame($oklch->alpha, $oklchClone->alpha);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $oklchClone->l);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $oklchClone->alpha);
		$oklchClone->l->value = 90.0;
		$oklchClone->alpha->value = 20.0;
		self::assertEqualsWithDelta(60.0, $oklch->l->value, 1e-9);
		self::assertEqualsWithDelta(100.0, $oklch->alpha->value, 1e-9);

		$color = new CSSColor('srgb', [0.1, 0.2, 0.3]);
		$colorClone = clone $color;
		self::assertNotSame($color->colorSpace, $colorClone->colorSpace);
		self::assertNotSame($color->channels[0], $colorClone->channels[0]);
		self::assertNotSame($color->alpha, $colorClone->alpha);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $colorClone->channels[0]);
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $colorClone->alpha);
		$colorClone->colorSpace->value = 'display-p3';
		$ch0 = $colorClone->channels[0];
		self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue::class, $ch0);
		$ch0->value = 0.9;
		$colorClone->alpha->value = 20.0;
		self::assertSame('srgb', $color->colorSpace->value);
		self::assertEqualsWithDelta(0.1, $color->channels[0]->value, 1e-9);
		self::assertEqualsWithDelta(100.0, $color->alpha->value, 1e-9);
	}
}
