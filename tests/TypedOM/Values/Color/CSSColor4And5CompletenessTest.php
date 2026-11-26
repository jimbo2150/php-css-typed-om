<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Color;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
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
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathValue;
use PHPUnit\Framework\TestCase;

final class CSSColor4And5CompletenessTest extends TestCase
{
	/** @var array<string, int> Official W3C CSS Color 4 §6.1 148 named colors */
	private const array W3C_NAMED_COLORS = [
		'aliceblue' => 0xF0F8FF, 'antiquewhite' => 0xFAEBD7, 'aqua' => 0x00FFFF, 'aquamarine' => 0x7FFFD4,
		'azure' => 0xF0FFFF, 'beige' => 0xF5F5DC, 'bisque' => 0xFFE4C4, 'black' => 0x000000,
		'blanchedalmond' => 0xFFEBCD, 'blue' => 0x0000FF, 'blueviolet' => 0x8A2BE2, 'brown' => 0xA52A2A,
		'burlywood' => 0xDEB887, 'cadetblue' => 0x5F9EA0, 'chartreuse' => 0x7FFF00, 'chocolate' => 0xD2691E,
		'coral' => 0xFF7F50, 'cornflowerblue' => 0x6495ED, 'cornsilk' => 0xFFF8DC, 'crimson' => 0xDC143C,
		'cyan' => 0x00FFFF, 'darkblue' => 0x00008B, 'darkcyan' => 0x008B8B, 'darkgoldenrod' => 0xB8860B,
		'darkgray' => 0xA9A9A9, 'darkgreen' => 0x006400, 'darkgrey' => 0xA9A9A9, 'darkkhaki' => 0xBDB76B,
		'darkmagenta' => 0x8B008B, 'darkolivegreen' => 0x556B2F, 'darkorange' => 0xFF8C00, 'darkorchid' => 0x9932CC,
		'darkred' => 0x8B0000, 'darksalmon' => 0xE9967A, 'darkseagreen' => 0x8FBC8F, 'darkslateblue' => 0x483D8B,
		'darkslategray' => 0x2F4F4F, 'darkslategrey' => 0x2F4F4F, 'darkturquoise' => 0x00CED1, 'darkviolet' => 0x9400D3,
		'deeppink' => 0xFF1493, 'deepskyblue' => 0x00BFFF, 'dimgray' => 0x696969, 'dimgrey' => 0x696969,
		'dodgerblue' => 0x1E90FF, 'firebrick' => 0xB22222, 'floralwhite' => 0xFFFAF0, 'forestgreen' => 0x228B22,
		'fuchsia' => 0xFF00FF, 'gainsboro' => 0xDCDCDC, 'ghostwhite' => 0xF8F8FF, 'gold' => 0xFFD700,
		'goldenrod' => 0xDAA520, 'gray' => 0x808080, 'green' => 0x008000, 'greenyellow' => 0xADFF2F,
		'grey' => 0x808080, 'honeydew' => 0xF0FFF0, 'hotpink' => 0xFF69B4, 'indianred' => 0xCD5C5C,
		'indigo' => 0x4B0082, 'ivory' => 0xFFFFF0, 'khaki' => 0xF0E68C, 'lavender' => 0xE6E6FA,
		'lavenderblush' => 0xFFF0F5, 'lawngreen' => 0x7CFC00, 'lemonchiffon' => 0xFFFACD, 'lightblue' => 0xADD8E6,
		'lightcoral' => 0xF08080, 'lightcyan' => 0xE0FFFF, 'lightgoldenrodyellow' => 0xFAFAD2, 'lightgray' => 0xD3D3D3,
		'lightgreen' => 0x90EE90, 'lightgrey' => 0xD3D3D3, 'lightpink' => 0xFFB6C1, 'lightsalmon' => 0xFFA07A,
		'lightseagreen' => 0x20B2AA, 'lightskyblue' => 0x87CEFA, 'lightslategray' => 0x778899, 'lightslategrey' => 0x778899,
		'lightsteelblue' => 0xB0C4DE, 'lightyellow' => 0xFFFFE0, 'lime' => 0x00FF00, 'limegreen' => 0x32CD32,
		'linen' => 0xFAF0E6, 'magenta' => 0xFF00FF, 'maroon' => 0x800000, 'mediumaquamarine' => 0x66CDAA,
		'mediumblue' => 0x0000CD, 'mediumorchid' => 0xBA55D3, 'mediumpurple' => 0x9370DB, 'mediumseagreen' => 0x3CB371,
		'mediumslateblue' => 0x7B68EE, 'mediumspringgreen' => 0x00FA9A, 'mediumturquoise' => 0x48D1CC, 'mediumvioletred' => 0xC71585,
		'midnightblue' => 0x191970, 'mintcream' => 0xF5FFFA, 'mistyrose' => 0xFFE4E1, 'moccasin' => 0xFFE4B5,
		'navajowhite' => 0xFFDEAD, 'navy' => 0x000080, 'oldlace' => 0xFDF5E6, 'olive' => 0x808000,
		'olivedrab' => 0x6B8E23, 'orange' => 0xFFA500, 'orangered' => 0xFF4500, 'orchid' => 0xDA70D6,
		'palegoldenrod' => 0xEEE8AA, 'palegreen' => 0x98FB98, 'paleturquoise' => 0xAFEEEE, 'palevioletred' => 0xDB7093,
		'papayawhip' => 0xFFEFD5, 'peachpuff' => 0xFFDAB9, 'peru' => 0xCD853F, 'pink' => 0xFFC0CB,
		'plum' => 0xDDA0DD, 'powderblue' => 0xB0E0E6, 'purple' => 0x800080, 'rebeccapurple' => 0x663399,
		'red' => 0xFF0000, 'rosybrown' => 0xBC8F8F, 'royalblue' => 0x4169E1, 'saddlebrown' => 0x8B4513,
		'salmon' => 0xFA8072, 'sandybrown' => 0xF4A460, 'seagreen' => 0x2E8B57, 'seashell' => 0xFFF5EE,
		'sienna' => 0xA0522D, 'silver' => 0xC0C0C0, 'skyblue' => 0x87CEEB, 'slateblue' => 0x6A5ACD,
		'slategray' => 0x708090, 'slategrey' => 0x708090, 'snow' => 0xFFFAFA, 'springgreen' => 0x00FF7F,
		'steelblue' => 0x4682B4, 'tan' => 0xD2B48C, 'teal' => 0x008080, 'thistle' => 0xD8BFD8,
		'tomato' => 0xFF6347, 'turquoise' => 0x40E0D0, 'violet' => 0xEE82EE, 'wheat' => 0xF5DEB3,
		'white' => 0xFFFFFF, 'whitesmoke' => 0xF5F5F5, 'yellow' => 0xFFFF00, 'yellowgreen' => 0x9ACD32,
	];

	public function testParsesAll148NamedColorsTransparentAndCurrentColor(): void
	{
		self::assertCount(148, self::W3C_NAMED_COLORS);
		foreach (self::W3C_NAMED_COLORS as $name => $hex) {
			$parsed = CSSColorValue::parse(ucfirst($name));
			self::assertInstanceOf(CSSRGB::class, $parsed, $name);
			self::assertEqualsWithDelta((($hex >> 16) & 0xFF) / 255.0 * 100.0, $parsed->r->value, 1e-6, $name);
			self::assertEqualsWithDelta((($hex >> 8) & 0xFF) / 255.0 * 100.0, $parsed->g->value, 1e-6, $name);
			self::assertEqualsWithDelta(($hex & 0xFF) / 255.0 * 100.0, $parsed->b->value, 1e-6, $name);
			self::assertEqualsWithDelta(100.0, $parsed->alpha->value, 1e-6, $name);
		}

		$transparent = CSSColorValue::parse('TRANSPARENT');
		self::assertInstanceOf(CSSRGB::class, $transparent);
		self::assertSame('rgb(0% 0% 0% / 0%)', (string) $transparent);

		$currentKeyword = CSSColorValue::parse('\63 urrentcolor');
		self::assertInstanceOf(CSSKeywordValue::class, $currentKeyword);
		self::assertSame('currentcolor', $currentKeyword->value);

		$ctx = new CSSContext(currentColor: new CSSRGB(0.2, 0.4, 0.6));
		$resolvedCurrent = CSSColorValue::parse('/*c*/ CurrentColor /*c*/', null, null, $ctx);
		self::assertInstanceOf(CSSRGB::class, $resolvedCurrent);
		self::assertEqualsWithDelta(20.0, $resolvedCurrent->r->value, 1e-9);
		self::assertNotSame($ctx->getCurrentColor(), $resolvedCurrent);

		$viaStyle = CSSStyleValue::parse('color', '\63 urrentcolor', null, $ctx);
		self::assertInstanceOf(CSSRGB::class, $viaStyle);
		self::assertEqualsWithDelta(40.0, $viaStyle->g->value, 1e-9);
	}

	public function testPowerlessAndMissingAnalogousCarryForwardPerCssColor4(): void
	{
		// HSL powerless hue at s=0%, saturation stays 0% (not none) per §7.1
		$blackHsl = CSSColorValue::parse('#000000')->toHSL();
		self::assertInstanceOf(CSSKeywordValue::class, $blackHsl->h);
		self::assertSame('none', $blackHsl->h->value);
		self::assertEqualsWithDelta(0.0, $blackHsl->s->value, 1e-9);
		self::assertEqualsWithDelta(0.0, $blackHsl->l->value, 1e-9);

		$whiteHsl = CSSColorValue::parse('#ffffff')->toHSL();
		self::assertInstanceOf(CSSKeywordValue::class, $whiteHsl->h);
		self::assertEqualsWithDelta(0.0, $whiteHsl->s->value, 1e-9);
		self::assertEqualsWithDelta(100.0, $whiteHsl->l->value, 1e-9);

		// HWB powerless hue when w + b >= 100%
		$grayHwb = CSSColorValue::parse('#808080')->toHWB();
		self::assertInstanceOf(CSSKeywordValue::class, $grayHwb->h);
		self::assertSame('none', $grayHwb->h->value);

		// LCH & OKLCH powerless hue when chroma ~ 0
		$grayLch = CSSColorValue::parse('#808080')->toLCH();
		self::assertInstanceOf(CSSKeywordValue::class, $grayLch->h);
		$grayOklch = CSSColorValue::parse('#808080')->toOKLCH();
		self::assertInstanceOf(CSSKeywordValue::class, $grayOklch->h);

		// Analogous carry-forward across all categories
		$hslMissing = CSSColorValue::parse('hsl(none 50% none)');
		$oklchFromHsl = $hslMissing->toOKLCH();
		self::assertInstanceOf(CSSKeywordValue::class, $oklchFromHsl->h);
		self::assertInstanceOf(CSSKeywordValue::class, $oklchFromHsl->l);

		$labMissing = CSSColorValue::parse('lab(none none 20)');
		$oklabFromLab = $labMissing->toOKLab();
		self::assertInstanceOf(CSSKeywordValue::class, $oklabFromLab->l);
		self::assertInstanceOf(CSSKeywordValue::class, $oklabFromLab->a);
		self::assertInstanceOf(CSSUnitValue::class, $oklabFromLab->b);

		$rgbMissing = CSSColorValue::parse('rgb(none 100% 0% / none)');
		/** @var CSSColor $p3FromRgb */
		$p3FromRgb = $rgbMissing->to('display-p3');
		self::assertInstanceOf(CSSKeywordValue::class, $p3FromRgb->channels[0]);
		self::assertSame('none', $p3FromRgb->channels[0]->value);
		self::assertInstanceOf(CSSKeywordValue::class, $p3FromRgb->alpha);

		$lchMissingL = CSSColorValue::parse('lch(none 40 120deg)');
		$labDirect = $lchMissingL->toLab();
		self::assertInstanceOf(CSSKeywordValue::class, $labDirect->l);

		// Same-space conversion short-circuits cleanly and preserves original channels
		$sameHsl = $hslMissing->toHSL();
		self::assertInstanceOf(CSSKeywordValue::class, $sameHsl->h);
		self::assertInstanceOf(CSSKeywordValue::class, $sameHsl->l);
	}

	public function testCssColor4GamutMappingAndInGamutChecks(): void
	{
		$p3Red = CSSColorValue::parse('color(display-p3 1 0 0)');
		self::assertTrue($p3Red->inGamut());
		self::assertTrue($p3Red->inGamut('display-p3'));
		self::assertFalse($p3Red->inGamut('srgb'));
		self::assertFalse($p3Red->inGamut('rgb'));
		self::assertTrue($p3Red->inGamut('oklch'));

		// Default toRGB() remains unclipped
		$unclipped = $p3Red->toRGB();
		self::assertGreaterThan(100.0, $unclipped->r->value);

		// Gamut-mapped toRGB(true) / toGamut('srgb') brings all channels into [0, 100%] / [0, 1]
		$mappedRgb = $p3Red->toRGB(true);
		self::assertTrue($mappedRgb->inGamut('srgb'));
		self::assertGreaterThanOrEqual(0.0, $mappedRgb->r->value);
		self::assertLessThanOrEqual(100.0, $mappedRgb->r->value);
		self::assertGreaterThanOrEqual(0.0, $mappedRgb->g->value);
		self::assertGreaterThanOrEqual(0.0, $mappedRgb->b->value);

		/** @var CSSColor $mappedSrgb */
		$mappedSrgb = $p3Red->toGamut('srgb');
		self::assertSame('srgb', $mappedSrgb->colorSpace->value);
		self::assertTrue($mappedSrgb->inGamut());

		// Same-space out-of-gamut CSSRGB / CSSHSL / CSSHWB / CSSColor
		$overRgb = new CSSRGB(1.5, -0.2, 0.1);
		self::assertFalse($overRgb->inGamut());
		$fixedRgb = $overRgb->toRGB(true);
		self::assertTrue($fixedRgb->inGamut());
		self::assertTrue($overRgb->toGamut()->inGamut());

		$overHsl = new CSSHSL(120, 150, 50);
		self::assertFalse($overHsl->inGamut());
		self::assertTrue($overHsl->toHSL(true)->inGamut());

		$overHwb = new CSSHWB(120, -20, 0);
		self::assertFalse($overHwb->inGamut());
		self::assertTrue($overHwb->toHWB(true)->inGamut());

		// Lightness extremes & extreme chroma
		$tooBright = CSSColorValue::parse('oklch(120% 0.5 180deg)')->toGamut('srgb');
		self::assertSame('color(srgb 1 1 1)', (string) $tooBright);
		$tooDark = CSSColorValue::parse('oklch(-10% 0.5 180deg)')->toGamut('srgb');
		self::assertSame('color(srgb 0 0 0)', (string) $tooDark);

		$extremeChroma = CSSColorValue::parse('oklch(50% 1e308 120deg)')->toGamut('srgb');
		self::assertTrue($extremeChroma->inGamut());
		$negChroma = CSSColorValue::parse('oklch(50% -0.5 120deg)')->toGamut('srgb');
		self::assertTrue($negChroma->inGamut());
	}

	public function testColorMixSupportsAllSpacesHueModesPremultipliedAlphaAndMissing(): void
	{
		// Rectangular srgb 50/50
		/** @var CSSColor $mixSrgb */
		$mixSrgb = CSSColorValue::parse('color-mix(in srgb, red, blue)');
		self::assertInstanceOf(CSSColor::class, $mixSrgb);
		self::assertSame('srgb', $mixSrgb->colorSpace->value);
		self::assertEqualsWithDelta(0.5, $mixSrgb->channels[0]->value, 1e-6);
		self::assertEqualsWithDelta(0.0, $mixSrgb->channels[1]->value, 1e-6);
		self::assertEqualsWithDelta(0.5, $mixSrgb->channels[2]->value, 1e-6);

		// Reversed percentage order & percentage < 100% alphaMultiplier
		/** @var CSSColor $mixAlpha */
		$mixAlpha = CSSColorValue::parse('color-mix(in srgb, 20% red, 20% blue)');
		self::assertEqualsWithDelta(0.5, $mixAlpha->channels[0]->value, 1e-6);
		self::assertEqualsWithDelta(40.0, $mixAlpha->alpha->value, 1e-6);

		// Percentage > 100% normalizes without alphaMultiplier
		/** @var CSSColor $mixOver100 */
		$mixOver100 = CSSColorValue::parse('color-mix(in srgb, red 80%, blue 80%)');
		self::assertEqualsWithDelta(0.5, $mixOver100->channels[0]->value, 1e-6);
		self::assertEqualsWithDelta(100.0, $mixOver100->alpha->value, 1e-6);

		// Premultiplied alpha + zero-alpha fallback
		/** @var CSSColor $premul */
		$premul = CSSColorValue::parse('color-mix(in srgb, rgb(100% 0% 0% / 50%), transparent)');
		self::assertEqualsWithDelta(1.0, $premul->channels[0]->value, 1e-6);
		self::assertEqualsWithDelta(25.0, $premul->alpha->value, 1e-6);

		/** @var CSSColor $zeroAlpha */
		$zeroAlpha = CSSColorValue::parse('color-mix(in srgb, rgb(100% 0% 0% / 0%), rgb(0% 0% 100% / 0%))');
		self::assertEqualsWithDelta(0.5, $zeroAlpha->channels[0]->value, 1e-6);
		self::assertEqualsWithDelta(0.5, $zeroAlpha->channels[2]->value, 1e-6);
		self::assertEqualsWithDelta(0.0, $zeroAlpha->alpha->value, 1e-6);

		// Polar spaces & all 4 hue interpolation modes
		$shorter = CSSColorValue::parse('color-mix(in oklch shorter hue, oklch(70% 0.2 30deg), oklch(70% 0.2 330deg))');
		self::assertInstanceOf(CSSOKLCH::class, $shorter);
		self::assertEqualsWithDelta(0.0, $shorter->h->value, 1e-6);

		$longer = CSSColorValue::parse('color-mix(in oklch longer hue, oklch(70% 0.2 30deg), oklch(70% 0.2 330deg))');
		self::assertInstanceOf(CSSOKLCH::class, $longer);
		self::assertEqualsWithDelta(180.0, $longer->h->value, 1e-6);

		$longerZeroDiff = CSSColorValue::parse('color-mix(in oklch longer hue, oklch(70% 0.2 60deg), oklch(70% 0.2 60deg))');
		self::assertInstanceOf(CSSOKLCH::class, $longerZeroDiff);
		self::assertEqualsWithDelta(240.0, $longerZeroDiff->h->value, 1e-6);

		$increasing = CSSColorValue::parse('color-mix(in oklch increasing hue, oklch(70% 0.2 330deg), oklch(70% 0.2 30deg))');
		self::assertInstanceOf(CSSOKLCH::class, $increasing);
		self::assertEqualsWithDelta(0.0, $increasing->h->value, 1e-6);

		$decreasing = CSSColorValue::parse('color-mix(in oklch decreasing hue, oklch(70% 0.2 30deg), oklch(70% 0.2 330deg))');
		self::assertInstanceOf(CSSOKLCH::class, $decreasing);
		self::assertEqualsWithDelta(0.0, $decreasing->h->value, 1e-6);

		// Output classes per Color 5 §3.3
		self::assertInstanceOf(CSSLab::class, CSSColorValue::parse('color-mix(in lab, red, blue)'));
		self::assertInstanceOf(CSSLCH::class, CSSColorValue::parse('color-mix(in lch, red, blue)'));
		self::assertInstanceOf(CSSOKLab::class, CSSColorValue::parse('color-mix(in oklab, red, blue)'));
		self::assertInstanceOf(CSSOKLCH::class, CSSColorValue::parse('color-mix(in oklch, red, blue)'));
		/** @var CSSColor $hslMix */
		$hslMix = CSSColorValue::parse('color-mix(in hsl, white, blue)');
		self::assertInstanceOf(CSSColor::class, $hslMix);
		self::assertSame('srgb', $hslMix->colorSpace->value);

		/** @var CSSColor $hwbMix */
		$hwbMix = CSSColorValue::parse('color-mix(in hwb, white, blue)');
		self::assertInstanceOf(CSSColor::class, $hwbMix);
		self::assertSame('srgb', $hwbMix->colorSpace->value);

		/** @var CSSColor $p3Mix */
		$p3Mix = CSSColorValue::parse('color-mix(in display-p3, red, blue)');
		self::assertInstanceOf(CSSColor::class, $p3Mix);
		self::assertSame('display-p3', $p3Mix->colorSpace->value);

		// Missing component inheritance
		$missingMix = CSSColorValue::parse('color-mix(in oklch, oklch(50% 0 none), oklch(70% 0.2 120deg))');
		self::assertInstanceOf(CSSOKLCH::class, $missingMix);
		self::assertEqualsWithDelta(120.0, $missingMix->h->value, 1e-6);

		$bothMissing = CSSColorValue::parse('color-mix(in oklch, oklch(50% 0 none / none), oklch(70% 0 none / none))');
		self::assertInstanceOf(CSSOKLCH::class, $bothMissing);
		self::assertInstanceOf(CSSKeywordValue::class, $bothMissing->h);
		self::assertInstanceOf(CSSKeywordValue::class, $bothMissing->alpha);

		// Nested color-mix() + clamped calc() percentages
		$nested = CSSColorValue::parse('color-mix(in srgb, color-mix(in srgb, red calc(150%), blue), white)');
		self::assertInstanceOf(CSSColor::class, $nested);
		self::assertEqualsWithDelta(1.0, $nested->channels[0]->value, 1e-6);
		self::assertEqualsWithDelta(0.5, $nested->channels[1]->value, 1e-6);
	}

	public function testRelativeColorSyntaxAcrossAll8Spaces(): void
	{
		// rgb()
		$rgb = CSSColorValue::parse('rgb(from #336699 calc(r + 17) g b)');
		self::assertInstanceOf(CSSRGB::class, $rgb);
		self::assertEqualsWithDelta((0x33 + 17) / 255.0 * 100.0, $rgb->r->value, 1e-4);
		self::assertEqualsWithDelta(0x66 / 255.0 * 100.0, $rgb->g->value, 1e-4);

		// Nested legacy origin inside modern relative color
		$nestedLegacy = CSSColorValue::parse('rgb(from rgb(255, 0, 0) r g b)');
		self::assertSame('rgb(100% 0% 0%)', (string) $nestedLegacy);

		// hsl(), hwb(), lab(), lch(), oklab(), oklch(), color()
		$hsl = CSSColorValue::parse('hsl(from red calc(h + 120) s calc(l - 10))');
		self::assertInstanceOf(CSSHSL::class, $hsl);
		self::assertEqualsWithDelta(120.0, $hsl->h->value, 1e-4);
		self::assertEqualsWithDelta(40.0, $hsl->l->value, 1e-4);

		$hwb = CSSColorValue::parse('hwb(from blue h calc(w + 10) b)');
		self::assertInstanceOf(CSSHWB::class, $hwb);
		self::assertEqualsWithDelta(240.0, $hwb->h->value, 1e-4);
		self::assertEqualsWithDelta(10.0, $hwb->w->value, 1e-4);

		$lab = CSSColorValue::parse('lab(from red calc(l + 10) a b)');
		self::assertInstanceOf(CSSLab::class, $lab);
		self::assertEqualsWithDelta(64.2917, $lab->l->value, 1e-2);

		$lch = CSSColorValue::parse('lch(from red l calc(c * 0.5) calc(h + 180))');
		self::assertInstanceOf(CSSLCH::class, $lch);

		$oklab = CSSColorValue::parse('oklab(from #336699 calc(l + 0.1) a b)');
		self::assertInstanceOf(CSSOKLab::class, $oklab);

		$oklch = CSSColorValue::parse('oklch(from #336699 calc(l + 0.1) c h)');
		self::assertInstanceOf(CSSOKLCH::class, $oklch);
		self::assertGreaterThan(50.0, $oklch->l->value);

		/** @var CSSColor $p3 */
		$p3 = CSSColorValue::parse('color(from red display-p3 r calc(g + 0.25) b / calc(alpha * 0.5))');
		self::assertSame('display-p3', $p3->colorSpace->value);
		self::assertEqualsWithDelta(50.0, $p3->alpha->value, 1e-6);

		// XYZ channel keywords x, y, z
		/** @var CSSColor $xyz */
		$xyz = CSSColorValue::parse('color(from white xyz-d65 x y calc(z * 0.5))');
		self::assertSame('xyz-d65', $xyz->colorSpace->value);

		// Missing pass-through vs 0 inside calc()
		$passNone = CSSColorValue::parse('oklch(from oklch(70% 0.1 none) l c h)');
		self::assertInstanceOf(CSSOKLCH::class, $passNone);
		self::assertInstanceOf(CSSKeywordValue::class, $passNone->h);

		$calcNone = CSSColorValue::parse('oklch(from oklch(70% 0.1 none) l c calc(h + 120))');
		self::assertInstanceOf(CSSOKLCH::class, $calcNone);
		self::assertInstanceOf(CSSUnitValue::class, $calcNone->h);
		self::assertEqualsWithDelta(120.0, $calcNone->h->value, 1e-6);

		// Inherited alpha when / alpha omitted
		$inheritedAlpha = CSSColorValue::parse('rgb(from rgb(100% 0% 0% / 25%) r g b)');
		self::assertEqualsWithDelta(25.0, $inheritedAlpha->alpha->value, 1e-6);

		// Unsimplified relative-unit math in channel keeps CSSMathValue
		$mathRel = CSSColorValue::parse('rgb(from red calc(10% + sin(1em / 1px) * 5%) 0 0)');
		self::assertInstanceOf(CSSRGB::class, $mathRel);
		self::assertInstanceOf(CSSMathValue::class, $mathRel->r);

		// Invalid relative color expressions
		foreach ([
			'rgb(from red calc(r + 10%) g b)',
			'rgb(from red calc(-r) g b)',
			'rgb(from red calc(-/*c*/r) g b)',
			'oklch(from red l c calc(h + 180deg))',
			'oklch(from red r g b)',
			'rgb(from red, r, g, b)',
			'color(from red --custom 1 2 3)',
		] as $bad) {
			try {
				CSSColorValue::parse($bad);
				self::fail('Expected invalid relative color syntax to throw: ' . $bad);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
	}

	public function testLightDarkAndStyleValueContextResolution(): void
	{
		// Default light-dark() selects first branch
		self::assertSame('rgb(100% 100% 100%)', (string) CSSColorValue::parse('light-dark(white, black)'));

		$darkCtx = new CSSContext(colorScheme: 'dark');
		self::assertSame('rgb(0% 0% 0%)', (string) CSSColorValue::parse('light-dark(white, black)', null, null, $darkCtx));

		// currentcolor inside light-dark() is valid even without currentColor in context
		$kw = CSSColorValue::parse('light-dark(currentcolor, black)');
		self::assertInstanceOf(CSSKeywordValue::class, $kw);
		self::assertSame('currentcolor', $kw->value);

		// Inactive invalid branch still rejected
		try {
			CSSColorValue::parse('light-dark(white, not-a-color)');
			self::fail('Expected invalid dark branch in light-dark() to throw.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		// CSSStyleValue::parse() preserves light-dark() as CSSUnparsedValue when no context is passed,
		// while validating both branches and rejecting disallowed properties
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('color', 'light-dark(white, black)'));
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('color', 'color-mix(in srgb, light-dark(white, black), red)'));
		self::assertInstanceOf(CSSRGB::class, CSSStyleValue::parse('color', 'light-dark(white, black)', null, $darkCtx));

		// Multi-color property shorthand with light-dark() falls back cleanly
		self::assertInstanceOf(CSSUnparsedValue::class, CSSStyleValue::parse('border-color', 'light-dark(red, blue) green'));

		foreach ([
			['width', 'light-dark(white, black)'],
			['opacity', 'color-mix(in srgb, currentcolor, red)'],
			['color', 'light-dark(white, bogus)'],
			['color', 'color-mix(in rgb, red, blue)'],
			['color', 'color-mix(in srgb, currentcolor, bogus)'],
		] as [$prop, $badVal]) {
			try {
				CSSStyleValue::parse($prop, $badVal);
				self::fail("Expected {$prop}: {$badVal} to throw InvalidArgumentException.");
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
	}
}
