<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstant;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstantEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathFunction;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use PHPUnit\Framework\TestCase;

final class CSSMathSimplifierTest extends TestCase
{
	private static function assertUnitValue(CSSNumericValue $value): CSSUnitValue
	{
		self::assertInstanceOf(CSSUnitValue::class, $value);

		return $value;
	}

	public function testBareRootLiteralsAreNotSimplifiedAtParseTime(): void
	{
		$bare = CSSNumericValue::parse('1in');
		self::assertInstanceOf(CSSUnitValue::class, $bare);
		self::assertSame(1.0, $bare->value);
		self::assertSame('in', $bare->unit);

		// Explicit simplify() canonicalizes bare units
		$canonical = $bare->simplify();
		self::assertInstanceOf(CSSUnitValue::class, $canonical);
		self::assertSame(96.0, $canonical->value);
		self::assertSame('px', $canonical->unit);
	}

	public function testCanonicalUnitOrderAndZeroDroppingInSumAndProduct(): void
	{
		// Number -> percentage -> ASCII-alphabetical dimensions -> non-leaf nodes
		$sum = CSSNumericValue::parse('calc(10vw + 2em + 50% + 1in + abs(1em - 1px))');
		self::assertInstanceOf(CSSMathSum::class, $sum);
		self::assertSame('calc(50% + 2em + 96px + 10vw + abs(1em - 1px))', (string) $sum);

		// Negative second term uses ` - ` serialization
		$diff = CSSNumericValue::parse('calc(10px - 5em)');
		self::assertSame('calc(-5em + 10px)', (string) $diff);
		$diff2 = CSSNumericValue::parse('calc(5em - 10px)');
		self::assertSame('calc(5em - 10px)', (string) $diff2);

		// Zero dropping preserves percentHint!
		$keepZeroPx = CSSNumericValue::parse('calc(50% + 10px - 10px)');
		self::assertSame('calc(50% + 0px)', (string) $keepZeroPx);
		self::assertSame('length', $keepZeroPx->type->percentHint);

		$keepZeroPct = CSSNumericValue::parse('calc(10px + 50% - 50%)');
		self::assertSame('calc(0% + 10px)', (string) $keepZeroPct);
		self::assertSame('length', $keepZeroPct->type->percentHint);

		// Zero term can be dropped when another same-dimension term preserves the percentHint
		$dropZeroPx = CSSNumericValue::parse('calc(50% + 10px - 10px + 2em)');
		self::assertSame('calc(50% + 2em)', (string) $dropZeroPx);
		self::assertSame('length', $dropZeroPx->type->percentHint);

		// Unit cancellation & canonical product ordering
		$cancelled = CSSNumericValue::parse('calc(1in / 2.54cm)');
		self::assertInstanceOf(CSSUnitValue::class, $cancelled);
		self::assertEqualsWithDelta(1.0, $cancelled->value, 1e-12);
		self::assertSame('number', $cancelled->unit);

		$emCancelled = CSSNumericValue::parse('calc(10em / 5em)');
		self::assertInstanceOf(CSSUnitValue::class, $emCancelled);
		self::assertSame(2.0, $emCancelled->value);

		// Percentages must NOT cancel in % / %
		$pctDivPct = CSSNumericValue::parse('calc(10% / 5%)');
		self::assertInstanceOf(CSSMathProduct::class, $pctDivPct);
		self::assertSame('calc(2 * 1% / 1%)', (string) $pctDivPct);

		// Product sorting: scalar first, then alphabetical dimensions
		$prodSorted = CSSNumericValue::parse('calc(2px * 5em)');
		self::assertInstanceOf(CSSMathProduct::class, $prodSorted);
		self::assertSame('calc(10 * 1em * 1px)', (string) $prodSorted);

		// Scalar distribution over sum
		$distributed = CSSNumericValue::parse('calc(2 * (10px + 5%))');
		self::assertInstanceOf(CSSMathSum::class, $distributed);
		self::assertSame('calc(10% + 20px)', (string) $distributed);
	}

	public function testRoundModRemFoldingAndEdgeCases(): void
	{
		// Tie-breaking toward +infinity
		self::assertSame('3', (string) CSSNumericValue::parse('round(2.5)'));
		self::assertSame('-2', (string) CSSNumericValue::parse('round(-2.5)'));
		self::assertSame('12px', (string) CSSNumericValue::parse('round(nearest, 11.5px, 1px)'));
		self::assertSame('-10px', (string) CSSNumericValue::parse('round(nearest, -12.5px, 5px)'));

		// Strategies & negative step uses |B|
		self::assertSame('15px', (string) CSSNumericValue::parse('round(up, 11px, 5px)'));
		self::assertSame('10px', (string) CSSNumericValue::parse('round(down, 14px, -5px)'));
		self::assertSame('-10px', (string) CSSNumericValue::parse('round(to-zero, -14px, 5px)'));

		// Edge cases: B=0 -> NaN, huge quotient overflow -> returns A directly
		self::assertSame('calc(NaN * 1px)', (string) CSSNumericValue::parse('round(10px, 0px)'));
		self::assertSame('calc(NaN)', (string) CSSNumericValue::parse('round(infinity, infinity)'));
		self::assertSame('calc(infinity)', (string) CSSNumericValue::parse('round(up, 5, infinity)'));
		self::assertSame('0', (string) CSSNumericValue::parse('round(nearest, 5, infinity)'));
		$roundOverflow = CSSNumericValue::parse('round(1e308, 1e-300)');
		self::assertInstanceOf(CSSUnitValue::class, $roundOverflow);
		self::assertSame(1e308, $roundOverflow->value);

		// mod vs rem signs & edge cases
		self::assertSame('2px', (string) CSSNumericValue::parse('mod(-10px, 3px)'));
		self::assertSame('-1px', (string) CSSNumericValue::parse('rem(-10px, 3px)'));
		self::assertSame('-1px', (string) CSSNumericValue::parse('mod(11px, -3px)'));
		self::assertSame('2px', (string) CSSNumericValue::parse('rem(11px, -3px)'));
		self::assertSame('5', (string) CSSNumericValue::parse('mod(5, infinity)'));
		self::assertSame('calc(NaN)', (string) CSSNumericValue::parse('mod(-5, infinity)'));
		self::assertSame('-5', (string) CSSNumericValue::parse('rem(-5, infinity)'));
		self::assertSame('calc(NaN)', (string) CSSNumericValue::parse('mod(infinity, 5)'));
		self::assertSame('0', (string) CSSNumericValue::parse('mod(1e308, 0.5)'));
	}

	public function testTrigExpAndSignFunctionsFolding(): void
	{
		// Exact quadrant trigonometry
		self::assertSame(0.0, self::assertUnitValue(CSSNumericValue::parse('sin(180deg)'))->value);
		self::assertSame(1.0, self::assertUnitValue(CSSNumericValue::parse('sin(0.25turn)'))->value);
		self::assertSame(0.0, self::assertUnitValue(CSSNumericValue::parse('cos(100grad)'))->value);
		self::assertSame(-1.0, self::assertUnitValue(CSSNumericValue::parse('cos(0.5turn)'))->value);
		self::assertSame(1.0, self::assertUnitValue(CSSNumericValue::parse('tan(45deg)'))->value);
		$tan90 = CSSNumericValue::parse('tan(90deg)');
		self::assertInstanceOf(CSSMathConstant::class, $tan90);
		self::assertSame(CSSMathConstantEnum::INFINITY, $tan90->constant);
		$tanNeg270 = CSSNumericValue::parse('tan(-270deg)');
		self::assertInstanceOf(CSSMathConstant::class, $tanNeg270);
		self::assertSame(CSSMathConstantEnum::INFINITY, $tanNeg270->constant);
		$tanNeg90 = CSSNumericValue::parse('tan(-90deg)');
		self::assertInstanceOf(CSSMathConstant::class, $tanNeg90);
		self::assertSame(CSSMathConstantEnum::NEG_INFINITY, $tanNeg90->constant);
		$tan270 = CSSNumericValue::parse('tan(270deg)');
		self::assertInstanceOf(CSSMathConstant::class, $tan270);
		self::assertSame(CSSMathConstantEnum::NEG_INFINITY, $tan270->constant);
		$sinInf = CSSNumericValue::parse('sin(infinity)');
		self::assertInstanceOf(CSSMathConstant::class, $sinInf);
		self::assertSame(CSSMathConstantEnum::NAN, $sinInf->constant);

		// Inverse trig -> deg
		self::assertEqualsWithDelta(90.0, self::assertUnitValue(CSSNumericValue::parse('asin(1)'))->value, 1e-12);
		self::assertEqualsWithDelta(180.0, self::assertUnitValue(CSSNumericValue::parse('acos(-1)'))->value, 1e-12);
		$asinOut = CSSNumericValue::parse('asin(2)');
		self::assertSame('calc(NaN * 1deg)', (string) $asinOut);
		self::assertSame(1, $asinOut->type->angle);
		self::assertEqualsWithDelta(90.0, self::assertUnitValue(CSSNumericValue::parse('atan(infinity)'))->value, 1e-12);
		self::assertEqualsWithDelta(-90.0, self::assertUnitValue(CSSNumericValue::parse('atan(-infinity)'))->value, 1e-12);
		self::assertEqualsWithDelta(45.0, self::assertUnitValue(CSSNumericValue::parse('atan2(1in, 96px)'))->value, 1e-12);

		// Exponential & logarithmic
		self::assertSame(8.0, self::assertUnitValue(CSSNumericValue::parse('pow(2, 3)'))->value);
		self::assertSame(1.0, self::assertUnitValue(CSSNumericValue::parse('pow(NaN, 0)'))->value);
		self::assertSame(1.0, self::assertUnitValue(CSSNumericValue::parse('pow(-1, infinity)'))->value);
		$powNaN = CSSNumericValue::parse('pow(-2, 0.5)');
		self::assertInstanceOf(CSSMathConstant::class, $powNaN);
		self::assertSame(CSSMathConstantEnum::NAN, $powNaN->constant);
		self::assertSame(5.0, self::assertUnitValue(CSSNumericValue::parse('sqrt(25)'))->value);
		$sqrtNaN = CSSNumericValue::parse('sqrt(-1)');
		self::assertInstanceOf(CSSMathConstant::class, $sqrtNaN);
		self::assertSame(CSSMathConstantEnum::NAN, $sqrtNaN->constant);
		self::assertSame(5.0, self::assertUnitValue(CSSNumericValue::parse('hypot(3, 4)'))->value);
		self::assertSame('5px', (string) CSSNumericValue::parse('hypot(3px, 4px)'));
		self::assertSame('calc(infinity * 1px)', (string) CSSNumericValue::parse('hypot(NaN * 1px, infinity * 1px)'));
		$hypotLarge = CSSNumericValue::parse('hypot(3e299, 4e299)');
		self::assertInstanceOf(CSSUnitValue::class, $hypotLarge);
		self::assertEqualsWithDelta(5e299, $hypotLarge->value, 1e290);
		self::assertEqualsWithDelta(1.0, self::assertUnitValue(CSSNumericValue::parse('log(e)'))->value, 1e-12);
		self::assertEqualsWithDelta(3.0, self::assertUnitValue(CSSNumericValue::parse('log(8, 2)'))->value, 1e-12);
		$logInf = CSSNumericValue::parse('log(10, 1)');
		self::assertInstanceOf(CSSMathConstant::class, $logInf);
		self::assertSame(CSSMathConstantEnum::INFINITY, $logInf->constant);
		$logNegInf = CSSNumericValue::parse('log(0.5, 1)');
		self::assertInstanceOf(CSSMathConstant::class, $logNegInf);
		self::assertSame(CSSMathConstantEnum::NEG_INFINITY, $logNegInf->constant);
		$logNaN = CSSNumericValue::parse('log(1, 1)');
		self::assertInstanceOf(CSSMathConstant::class, $logNaN);
		self::assertSame(CSSMathConstantEnum::NAN, $logNaN->constant);
		self::assertEqualsWithDelta(M_E, self::assertUnitValue(CSSNumericValue::parse('exp(1)'))->value, 1e-12);
		self::assertSame(0.0, self::assertUnitValue(CSSNumericValue::parse('exp(-infinity)'))->value);

		// Sign & abs
		self::assertSame('12px', (string) CSSNumericValue::parse('abs(-12px)'));
		self::assertSame(-1.0, self::assertUnitValue(CSSNumericValue::parse('sign(-12px)'))->value);
		self::assertSame(0.0, self::assertUnitValue(CSSNumericValue::parse('sign(0deg)'))->value);
		self::assertSame(1.0, self::assertUnitValue(CSSNumericValue::parse('sign(2s)'))->value);
		// Relative length and percentage sign() are preserved without context
		self::assertInstanceOf(CSSMathFunction::class, CSSNumericValue::parse('sign(2em)'));
		self::assertInstanceOf(CSSMathFunction::class, CSSNumericValue::parse('sign(-10%)'));
	}

	public function testMinMaxClampAndContextSimplification(): void
	{
		self::assertSame('5px', (string) CSSNumericValue::parse('min(1in, 5px, 20px)'));
		self::assertSame('min(5px, 50%)', (string) CSSNumericValue::parse('min(10px, 50%, 5px)'));
		self::assertSame('10px', (string) CSSNumericValue::parse('clamp(10px, 5px, 20px)'));
		// When MIN > MAX, MIN wins per CSS Values 4 §10.3
		self::assertSame('30px', (string) CSSNumericValue::parse('clamp(30px, 15px, 20px)'));

		// Relative units resolve cleanly when CSSContext is passed to simplify()
		$expr = CSSNumericValue::parse('calc(10vw + 2em + 1in)');
		$ctx = new CSSContext(16.0, 1000.0, 500.0);
		$resolved = $expr->simplify($ctx);
		self::assertInstanceOf(CSSUnitValue::class, $resolved);
		self::assertSame(228.0, $resolved->value);

		$signEm = CSSNumericValue::parse('sign(-2em)')->simplify($ctx);
		self::assertInstanceOf(CSSUnitValue::class, $signEm);
		self::assertSame(-1.0, $signEm->value);

		// Unresolved non-leaf terms with ±infinity and 0 * non-leaf are not discarded prematurely
		$guardedSum = CSSNumericValue::parse('calc(infinity * 1px + 1px / (1em / 1px - 16))');
		self::assertInstanceOf(CSSMathSum::class, $guardedSum);

		$guardedZeroProd = CSSNumericValue::parse('calc(0 * (1px / (1em / 1px - 16)))');
		self::assertInstanceOf(CSSMathProduct::class, $guardedZeroProd);

		// Verify min/max single-arg unwraps
		self::assertInstanceOf(CSSUnitValue::class, (new CSSMathMin([CSS::px(5)]))->simplify());
		self::assertInstanceOf(CSSUnitValue::class, (new CSSMathMax([CSS::px(5)]))->simplify());

		// Sum overflow removes overflowed finite bucket cleanly while retaining unresolved non-leaf terms
		$overflowSum = CSSNumericValue::parse('calc(1e308px + 1e308px + 5px + min(1em, 1vw))');
		self::assertInstanceOf(CSSMathSum::class, $overflowSum);
		self::assertSame('calc(infinity * 1px + min(1em, 1vw))', (string) $overflowSum);

		// round(nearest) overflow near PHP_FLOAT_MAX resolves to infinity cleanly (no tie distortion to $lower)
		$roundMaxInf = CSSNumericValue::parse('round(nearest, 1.6e308, 1e308)');
		self::assertInstanceOf(CSSMathConstant::class, $roundMaxInf);
		self::assertSame(CSSMathConstantEnum::INFINITY, $roundMaxInf->constant);

		// Percentage stepped functions remain unfolded (percent basis unknown), while negative/infinite step edges evaluate accurately
		self::assertInstanceOf(CSSMathFunction::class, CSSNumericValue::parse('round(25%, 10%)'));
		self::assertInstanceOf(CSSMathFunction::class, CSSNumericValue::parse('rem(25%, 10%)'));
		self::assertSame('5px', (string) CSSNumericValue::parse('round(nearest, 7px, -5px)'));

		// Radian trig fast-path asserts CSSUnitValue & 'number' unit directly
		foreach ([['sin(1rad)', sin(1.0)], ['cos(1rad)', cos(1.0)], ['tan(1rad)', tan(1.0)]] as [$trigCss, $expected]) {
			$trigVal = CSSNumericValue::parse($trigCss);
			self::assertInstanceOf(CSSUnitValue::class, $trigVal);
			self::assertSame('number', $trigVal->unit);
			self::assertEqualsWithDelta($expected, $trigVal->value, 1e-12);
		}

		// pow(1, NaN/INF), pow(0, -1), atan2 quadrants & NaN, hypot(0,0), log domain edges
		$powOneNaN = CSSNumericValue::parse('pow(1, NaN)');
		self::assertInstanceOf(CSSUnitValue::class, $powOneNaN);
		self::assertSame(1.0, $powOneNaN->value);
		$powOneInf = CSSNumericValue::parse('pow(1, infinity)');
		self::assertInstanceOf(CSSUnitValue::class, $powOneInf);
		self::assertSame(1.0, $powOneInf->value);
		$powNegOneNaN = CSSNumericValue::parse('pow(-1, NaN)');
		self::assertInstanceOf(CSSMathConstant::class, $powNegOneNaN);
		self::assertSame(CSSMathConstantEnum::NAN, $powNegOneNaN->constant);
		$powZeroNeg = CSSNumericValue::parse('pow(0, -1)');
		self::assertInstanceOf(CSSMathConstant::class, $powZeroNeg);
		self::assertSame(CSSMathConstantEnum::INFINITY, $powZeroNeg->constant);

		self::assertEqualsWithDelta(-135.0, self::assertUnitValue(CSSNumericValue::parse('atan2(-1px, -1px)'))->value, 1e-12);
		self::assertEqualsWithDelta(0.0, self::assertUnitValue(CSSNumericValue::parse('atan2(0px, 1px)'))->value, 1e-12);
		self::assertEqualsWithDelta(45.0, self::assertUnitValue(CSSNumericValue::parse('atan2(infinity * 1px, infinity * 1px)'))->value, 1e-12);
		self::assertSame('calc(NaN * 1deg)', (string) CSSNumericValue::parse('atan2(NaN * 1px, 1px)'));
		self::assertSame('0', (string) CSSNumericValue::parse('hypot(0, 0)'));
		self::assertSame('calc(NaN * 1px)', (string) CSSNumericValue::parse('hypot(NaN * 1px, 3px)'));
		self::assertSame('calc(-infinity)', (string) CSSNumericValue::parse('log(0)'));
		self::assertSame('calc(NaN)', (string) CSSNumericValue::parse('log(-1)'));
		self::assertSame('calc(infinity)', (string) CSSNumericValue::parse('log(infinity)'));

		// Canonicalization overflow in simplify() (1e308in * 96 -> infinity * 1px)
		$overflowIn = CSS::in(1e308)->simplify();
		self::assertInstanceOf(CSSMathProduct::class, $overflowIn);
		self::assertSame('calc(infinity * 1px)', (string) $overflowIn);

		// One-sided clampToRange() and invalid-bound validation
		self::assertEqualsWithDelta(10.0, self::assertUnitValue(CSSMathConstant::infinity()->clampToRange(null, 10.0))->value, 1e-12);
		self::assertEqualsWithDelta(-5.0, self::assertUnitValue(CSSMathConstant::negInfinity()->clampToRange(-5.0, null))->value, 1e-12);
		foreach ([
			[NAN, 10.0],
			[0.0, INF],
			[20.0, 10.0],
		] as [$badMin, $badMax]) {
			try {
				CSS::number(5)->clampToRange($badMin, $badMax);
				self::fail('Expected invalid clampToRange() bounds to throw.');
			} catch (\InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
	}
}
