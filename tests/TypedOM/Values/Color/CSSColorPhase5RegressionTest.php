<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Color;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
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
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use PHPUnit\Framework\TestCase;

final class CSSColorPhase5RegressionTest extends TestCase
{
	public function testSiblingFastPathsRejectUnresolvedCalcChannels(): void
	{
		$unresolved = CSSNumericValue::parse('calc(10% + 1em)');

		$cases = [
			static fn () => (new CSSLab($unresolved, 0, 0))->toLCH(),
			static fn () => (new CSSOKLab($unresolved, 0, 0))->toOKLCH(),
			static fn () => (new CSSColor('srgb', [$unresolved, 0, 0]))->toRGB(),
			static fn () => (new CSSColor('srgb-linear', [$unresolved, 0, 0]))->toRGB(),
			static fn () => (new CSSColor('xyz-d50', [$unresolved, 0, 0]))->toLab(),
			static fn () => (new CSSColor('xyz-d50', [$unresolved, 0, 0]))->toLCH(),
		];

		foreach ($cases as $idx => $case) {
			try {
				$case();
				self::fail('Expected InvalidArgumentException for unresolved calc() case #' . $idx);
			} catch (InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}
	}

	public function testSiblingFastPathsPreserveMissingAndPowerlessCarryForward(): void
	{
		$none = new CSSKeywordValue('none');

		// Lab -> LCH fast path: zero chroma yields powerless hue ('none') and carries missing L
		$labAchromatic = new CSSLab($none, 0, 0);
		$lchAchromatic = $labAchromatic->toLCH();
		$this->assertInstanceOf(CSSKeywordValue::class, $lchAchromatic->l);
		$this->assertSame('none', $lchAchromatic->l->value);
		$this->assertInstanceOf(CSSUnitValue::class, $lchAchromatic->c);
		$this->assertEqualsWithDelta(0.0, $lchAchromatic->c->value, 1e-9);
		$this->assertInstanceOf(CSSKeywordValue::class, $lchAchromatic->h);
		$this->assertSame('none', $lchAchromatic->h->value);

		// OKLab -> OKLCH fast path: zero chroma yields powerless hue ('none') and carries missing L
		$oklabAchromatic = new CSSOKLab($none, 0, 0);
		$oklchAchromatic = $oklabAchromatic->toOKLCH();
		$this->assertInstanceOf(CSSKeywordValue::class, $oklchAchromatic->l);
		$this->assertInstanceOf(CSSKeywordValue::class, $oklchAchromatic->h);

		// Missing opponent 'a' treats math a=0 -> chromaticity only from b=20
		$labMissingA = new CSSLab(CSS::percent(50), $none, 20);
		$lchFromMissingA = $labMissingA->toLCH();
		$this->assertInstanceOf(CSSUnitValue::class, $lchFromMissingA->c);
		$this->assertEqualsWithDelta(20.0, $lchFromMissingA->c->value, 1e-9);
		$this->assertInstanceOf(CSSUnitValue::class, $lchFromMissingA->h);
		$this->assertEqualsWithDelta(90.0, $lchFromMissingA->h->value, 1e-9);

		// HSL/HWB color-mix direct srgb path matches expected result and preserves alpha none/pct
		$mixedHsl = CSSColorValue::parse('color-mix(in hsl, red, blue)');
		$this->assertSame('color(srgb 1 0 1)', (string) $mixedHsl);

		$mixedHwb = CSSColorValue::parse('color-mix(in hwb, red, yellow)');
		$this->assertSame('color(srgb 1 0.5 0)', (string) $mixedHwb);

		// Direct XYZ-D50 -> Lab & LCH whitepoint constancy
		$d50White = new CSSColor('xyz-d50', [
			0.3457 / 0.3585,
			1.0,
			(1.0 - 0.3457 - 0.3585) / 0.3585,
		]);
		$labWhite = $d50White->toLab();
		$this->assertInstanceOf(CSSUnitValue::class, $labWhite->l);
		$this->assertEqualsWithDelta(100.0, $labWhite->l->value, 1e-6);
		$this->assertInstanceOf(CSSUnitValue::class, $labWhite->a);
		$this->assertEqualsWithDelta(0.0, $labWhite->a->value, 1e-6);
		$this->assertInstanceOf(CSSUnitValue::class, $labWhite->b);
		$this->assertEqualsWithDelta(0.0, $labWhite->b->value, 1e-6);

		// Cross-space whitepoint invariance across srgb / display-p3 / lab / oklab
		$srgbWhite = CSSColorValue::parse('#ffffff');
		$p3White = $srgbWhite->to('display-p3');
		$this->assertInstanceOf(CSSColor::class, $p3White);
		foreach ($p3White->channels as $ch) {
			$this->assertInstanceOf(CSSUnitValue::class, $ch);
			$this->assertEqualsWithDelta(1.0, $ch->value, 1e-5);
		}
		$oklchWhite = $srgbWhite->toOKLCH();
		$this->assertInstanceOf(CSSUnitValue::class, $oklchWhite->l);
		$this->assertEqualsWithDelta(100.0, $oklchWhite->l->value, 1e-4);
		$this->assertInstanceOf(CSSKeywordValue::class, $oklchWhite->h);
	}

	public function testGamutMapperAndThreeChannelTraitIsolation(): void
	{
		// Out-of-gamut OKLCH -> srgb & wide-gamut targets
		$vivid = CSSColorValue::parse('oklch(70% 0.4 150deg)');
		$mappedSrgb = $vivid->toGamut('srgb');
		$this->assertTrue($mappedSrgb->inGamut('srgb'));
		$mappedP3 = $vivid->toGamut('display-p3');
		$this->assertTrue($mappedP3->inGamut('display-p3'));

		// Lightness boundaries clamp cleanly to white / black
		$overWhite = new CSSOKLCH(CSS::percent(110), 0.3, 120);
		$this->assertSame('rgb(100% 100% 100%)', (string) $overWhite->toRGB(true));
		$underBlack = new CSSOKLCH(CSS::percent(-5), 0.3, 120);
		$this->assertSame('rgb(0% 0% 0%)', (string) $underBlack->toRGB(true));

		// Clone isolation via ThreeChannelColorTrait for all 7 3-channel classes
		$samples = [
			['rgb(10% 20% 30% / 50%)', CSSRGB::class],
			['hsl(120deg 50% 40% / 50%)', CSSHSL::class],
			['hwb(240deg 10% 20% / 50%)', CSSHWB::class],
			['lab(50% 20 -30 / 50%)', CSSLab::class],
			['lch(60% 40 120deg / 50%)', CSSLCH::class],
			['oklab(60% 0.1 -0.1 / 50%)', CSSOKLab::class],
			['oklch(65% 0.2 210deg / 50%)', CSSOKLCH::class],
		];

		foreach ($samples as [$css, $expectedClass]) {
			$parsed = CSSColorValue::parse($css);
			$this->assertInstanceOf($expectedClass, $parsed);
			$copy = $parsed->clone();
			$copy->setAlpha(0.25);
			$this->assertNotSame((string) $parsed, (string) $copy);
			$this->assertSame($css, (string) $parsed);
		}
	}

	public function testTrailingDotRejectionsCommentSplittingXyzAliasNoneAndOklchNegativeChroma(): void
	{
		// CSS <number-token> must reject trailing dots while preserving -0 and non-finite rejection
		foreach (['rgb(10. 20 30)', 'rgb(10.% 20% 30%)', 'rgb(10.e2 20 30)'] as $invalid) {
			try {
				CSSColorValue::parse($invalid);
				self::fail('Expected InvalidArgumentException for ' . $invalid);
			} catch (InvalidArgumentException) {
				$this->addToAssertionCount(1);
			}
		}

		try {
			CSSColorValue::parse('rgb(1e500 0 0)');
			self::fail('Expected InvalidArgumentException for overflow color channel.');
		} catch (InvalidArgumentException) {
			$this->addToAssertionCount(1);
		}

		$negZero = CSSColorValue::parse('rgb(-0 0 0)');
		$this->assertSame('rgb(-0% 0% 0%)', (string) $negZero);

		// Comments containing ) / ( inside calc() must not break splitColorArguments()
		$commented = CSSColorValue::parse('rgb(calc(10% /* ) ( */ + 20%) 30% 40% / 50%)');
		$this->assertSame('rgb(30% 30% 40% / 50%)', (string) $commented);

		// xyz <-> xyz-d65 aliasing preserves missing `none` components across to() and color-mix()
		$xyzNone = CSSColorValue::parse('color(xyz none 0.25 0.5)');
		$converted = $xyzNone->to('xyz-d65');
		$this->assertSame('color(xyz-d65 none 0.25 0.5)', (string) $converted);
		$this->assertInstanceOf(CSSColor::class, $converted);
		$convertedChannels = $converted->channels;
		$this->assertInstanceOf(CSSUnitValue::class, $convertedChannels[1]);
		$origComponents = $xyzNone->getComponentValuesInternal();
		$convertedComponents = $converted->getComponentValuesInternal();
		$this->assertNotSame($origComponents[1], $convertedComponents[1]);
		$convertedChannels[1]->value = 0.9;
		$this->assertSame('color(xyz none 0.25 0.5)', (string) $xyzNone);

		$mixed = CSSColorValue::parse('color-mix(in xyz-d65, color(xyz none 0.2 0.4), color(xyz 0.4 0.6 0.8))');
		$this->assertStringStartsWith('color(xyz-d65 0.4 0.4', (string) $mixed);
		[$floats, $alpha] = $xyzNone->toSpaceFloatsInternal('xyz-d65');
		$this->assertNull($floats[0]);
		$this->assertEqualsWithDelta(0.25, $floats[1], 1e-9);
		$this->assertEqualsWithDelta(0.5, $floats[2], 1e-9);
		$this->assertEqualsWithDelta(1.0, $alpha, 1e-9);

		// Negative chroma in CSSOKLCH normalizes magnitude and flips hue +180deg
		$negChroma = new CSSOKLCH(CSS::percent(70), -0.2, 30);
		[$okL, $okC, $okH] = $negChroma->toOklchFloatsInternal();
		$this->assertEqualsWithDelta(0.7, $okL, 1e-9);
		$this->assertEqualsWithDelta(0.2, $okC, 1e-9);
		$this->assertEqualsWithDelta(210.0, $okH, 1e-9);

		$gamutMapped = (new CSSOKLCH(CSS::percent(70), -0.5, 30))->toGamut('srgb');
		$this->assertTrue($gamutMapped->inGamut('srgb'));
	}
}
