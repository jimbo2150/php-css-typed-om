<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric\Math;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\Parser\CSSCalcParser;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstant;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathConstantEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use PHPUnit\Framework\TestCase;

final class CSSMathConstantsTest extends TestCase
{
	public function testConstantsAreRejectedOutsideMathFunctions(): void
	{
		foreach (['e', 'pi', 'infinity', '-infinity', 'NaN', 'nan'] as $bare) {
			try {
				CSSNumericValue::parse($bare);
				self::fail('Expected bare math constant to be rejected outside math expressions: ' . $bare);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
	}

	public function testConstantsParseAndFoldInsideMathFunctions(): void
	{
		$pi = CSSNumericValue::parse('calc(PI)');
		self::assertInstanceOf(CSSUnitValue::class, $pi);
		self::assertEqualsWithDelta(M_PI, $pi->value, 1e-12);

		$e = CSSNumericValue::parse('calc(E)');
		self::assertInstanceOf(CSSUnitValue::class, $e);
		self::assertEqualsWithDelta(M_E, $e->value, 1e-12);

		$sinPi = CSSNumericValue::parse('sin(p\\69)');
		self::assertInstanceOf(CSSUnitValue::class, $sinPi);
		self::assertEqualsWithDelta(0.0, $sinPi->value, 1e-12);

		$inf = CSSNumericValue::parse('calc(InFiNiTy)');
		self::assertInstanceOf(CSSMathConstant::class, $inf);
		self::assertSame(CSSMathConstantEnum::INFINITY, $inf->constant);
		self::assertSame('calc(infinity)', (string) $inf);
		self::assertTrue($inf->isInfinite());
		self::assertFalse($inf->isFinite());

		$negInf = CSSNumericValue::parse('calc(-INFINITY)');
		self::assertInstanceOf(CSSMathConstant::class, $negInf);
		self::assertSame(CSSMathConstantEnum::NEG_INFINITY, $negInf->constant);
		self::assertSame('calc(-infinity)', (string) $negInf);

		$escapedNegInf = CSSNumericValue::parse('calc(\\2d infinity)');
		self::assertInstanceOf(CSSMathConstant::class, $escapedNegInf);
		self::assertSame(CSSMathConstantEnum::NEG_INFINITY, $escapedNegInf->constant);

		$escapedMidNegInf = CSSNumericValue::parse('calc(-inf\\69 nity)');
		self::assertInstanceOf(CSSMathConstant::class, $escapedMidNegInf);
		self::assertSame(CSSMathConstantEnum::NEG_INFINITY, $escapedMidNegInf->constant);

		$nan = CSSNumericValue::parse('calc(nan)');
		self::assertInstanceOf(CSSMathConstant::class, $nan);
		self::assertSame(CSSMathConstantEnum::NAN, $nan->constant);
		self::assertSame('calc(NaN)', (string) $nan);
		self::assertTrue($nan->isNaN());

		// Also accepted in bare inner calc expression and function arguments
		$exprPi = CSSCalcParser::parseExpression('pi * 2');
		self::assertInstanceOf(CSSUnitValue::class, $exprPi);
		self::assertEqualsWithDelta(2.0 * M_PI, $exprPi->value, 1e-12);

		$minInf = CSSNumericValue::parse('min(10, infinity)');
		self::assertInstanceOf(CSSUnitValue::class, $minInf);
		self::assertSame(10.0, $minInf->value);

		$maxNegInf = CSSNumericValue::parse('max(-infinity, 5)');
		self::assertInstanceOf(CSSUnitValue::class, $maxNegInf);
		self::assertSame(5.0, $maxNegInf->value);
	}

	public function testRejectsUnaryPlusMinusDirectlyBeforeConstantsWhileAllowingParensAndMultiply(): void
	{
		foreach ([
			'calc(-pi)',
			'calc(-e)',
			'calc(-NaN)',
			'calc(+infinity)',
			'calc(+pi)',
			'calc(-/*c*/infinity)',
			'calc(-infinitypx)',
		] as $invalid) {
			try {
				CSSNumericValue::parse($invalid);
				self::fail('Expected invalid unary/constant syntax to throw: ' . $invalid);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		$negParenPi = CSSNumericValue::parse('calc(-(pi))');
		self::assertInstanceOf(CSSUnitValue::class, $negParenPi);
		self::assertEqualsWithDelta(-M_PI, $negParenPi->value, 1e-12);

		$negParenNegInf = CSSNumericValue::parse('calc(-(-infinity))');
		self::assertInstanceOf(CSSMathConstant::class, $negParenNegInf);
		self::assertSame(CSSMathConstantEnum::INFINITY, $negParenNegInf->constant);

		$negOneTimesPi = CSSNumericValue::parse('calc(-1 * pi)');
		self::assertInstanceOf(CSSUnitValue::class, $negOneTimesPi);
		self::assertEqualsWithDelta(-M_PI, $negOneTimesPi->value, 1e-12);

		$subPi = CSSNumericValue::parse('calc(10 - pi)');
		self::assertInstanceOf(CSSUnitValue::class, $subPi);
		self::assertEqualsWithDelta(10.0 - M_PI, $subPi->value, 1e-12);
	}

	public function testIeee754PropagationDimensionalDegeneratesAndSerialization(): void
	{
		$divZero = CSSNumericValue::parse('calc(1 / 0)');
		self::assertInstanceOf(CSSMathConstant::class, $divZero);
		self::assertSame(CSSMathConstantEnum::INFINITY, $divZero->constant);

		$negDivZero = CSSNumericValue::parse('calc(-10px / 0)');
		self::assertInstanceOf(CSSMathProduct::class, $negDivZero);
		self::assertSame('calc(-infinity * 1px)', (string) $negDivZero);
		self::assertSame(1, $negDivZero->type->length);

		$zeroDivZero = CSSNumericValue::parse('calc(0 / 0)');
		self::assertInstanceOf(CSSMathConstant::class, $zeroDivZero);
		self::assertSame(CSSMathConstantEnum::NAN, $zeroDivZero->constant);

		$infMinusInf = CSSNumericValue::parse('calc(infinity - infinity)');
		self::assertInstanceOf(CSSMathConstant::class, $infMinusInf);
		self::assertSame(CSSMathConstantEnum::NAN, $infMinusInf->constant);

		$zeroTimesInfPx = CSSNumericValue::parse('calc(0 * infinity * 1px)');
		self::assertInstanceOf(CSSMathProduct::class, $zeroTimesInfPx);
		self::assertSame('calc(NaN * 1px)', (string) $zeroTimesInfPx);
		self::assertSame(1, $zeroTimesInfPx->type->length);

		$oneOverInf = CSSNumericValue::parse('calc(1 / infinity)');
		self::assertInstanceOf(CSSUnitValue::class, $oneOverInf);
		self::assertSame(0.0, $oneOverInf->value);

		// Negated constant serialization always parenthesizes so round-trip stays valid
		$negatedPi = new CSSMathNegate(CSSMathConstant::pi());
		self::assertSame('calc(-(pi))', (string) $negatedPi);
		$reparsed = CSSNumericValue::parse((string) $negatedPi);
		self::assertInstanceOf(CSSUnitValue::class, $reparsed);
		self::assertEqualsWithDelta(-M_PI, $reparsed->value, 1e-12);

		$negatedNegInf = new CSSMathNegate(CSSMathConstant::negInfinity());
		self::assertSame('calc(-(-infinity))', (string) $negatedNegInf);
		$sumSubNegInf = new CSSMathSum([CSS::number(1), $negatedNegInf]);
		self::assertSame('calc(1 - (-infinity))', (string) $sumSubNegInf);
	}

	public function testEqualityAndClampingRules(): void
	{
		$nan = CSSMathConstant::nan();
		self::assertFalse($nan->equals($nan));
		self::assertFalse($nan->equals(CSSMathConstant::nan()));

		$nanPx = CSSNumericValue::parse('calc(NaN * 1px)');
		self::assertFalse($nanPx->equals($nanPx));

		$nanSum = new CSSMathSum([CSS::number(1), $nan]);
		self::assertFalse($nanSum->equals($nanSum));

		self::assertTrue(CSSMathConstant::infinity()->equals(CSSMathConstant::infinity()));
		self::assertFalse(CSSMathConstant::infinity()->equals(CSSMathConstant::negInfinity()));
		self::assertFalse(CSSMathConstant::pi()->equals(CSS::number(M_PI)));

		// §10.8 range clamping
		$clampedInf = CSSNumericValue::parse('calc(infinity * 1px)')->clampToRange(0.0, 500.0);
		self::assertInstanceOf(CSSUnitValue::class, $clampedInf);
		self::assertSame('500px', (string) $clampedInf);

		$clampedNegInf = CSSNumericValue::parse('calc(-infinity * 1px)')->clampToRange(0.0, 500.0);
		self::assertSame('0px', (string) $clampedNegInf);

		$clampedNaN = CSSNumericValue::parse('calc(NaN * 1px)')->clampToRange(10.0, 500.0);
		self::assertSame('10px', (string) $clampedNaN);

		$clampedNaNZero = CSSNumericValue::parse('calc(0 / 0)')->clampToRange();
		self::assertSame('0', (string) $clampedNaNZero);

		$unboundedInf = CSSNumericValue::parse('calc(infinity)')->clampToRange();
		self::assertInstanceOf(CSSUnitValue::class, $unboundedInf);
		self::assertSame(PHP_FLOAT_MAX, $unboundedInf->value);

		// Unresolved expression simplifies with context then clamps
		$relClamped = CSSNumericValue::parse('calc(2em + 10px)')->clampToRange(0.0, 30.0, new CSSContext(16.0));
		self::assertSame('30px', (string) $relClamped);

		// Compound degenerate product preserves its type instead of collapsing to CSSUnitValue
		$compoundInf = CSSNumericValue::parse('calc(infinity * 1px * 1px)')->clampToRange(0.0, 100.0);
		self::assertInstanceOf(CSSMathProduct::class, $compoundInf);
		self::assertSame(2, $compoundInf->type->length);

		// String constructor + to()/toSum() on CSSMathConstant
		$constPiStr = new CSSMathConstant('PI');
		self::assertSame(CSSMathConstantEnum::PI, $constPiStr->constant);
		$piToNum = CSSMathConstant::pi()->to('number');
		self::assertInstanceOf(CSSMathConstant::class, $piToNum);
		self::assertSame(CSSMathConstantEnum::PI, $piToNum->constant);
		$piSimplifiedToNum = CSSMathConstant::pi()->simplify()->to('number');
		self::assertInstanceOf(CSSUnitValue::class, $piSimplifiedToNum);
		self::assertEqualsWithDelta(M_PI, $piSimplifiedToNum->value, 1e-12);
		$eToNum = CSSMathConstant::e()->toSum('number');
		self::assertInstanceOf(CSSUnitValue::class, $eToNum->values[0]);
		self::assertEqualsWithDelta(M_E, $eToNum->values[0]->value, 1e-12);

		foreach (['unknown-const', '+infinity', 'pi '] as $invalidConst) {
			try {
				new CSSMathConstant($invalidConst);
				self::fail('Expected invalid CSSMathConstant string to throw: ' . $invalidConst);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		foreach ([CSSMathConstant::infinity(), CSSMathConstant::negInfinity(), CSSMathConstant::nan()] as $nonFiniteConst) {
			try {
				$nonFiniteConst->toSum('number');
				self::fail('Expected non-finite CSSMathConstant::toSum() to throw.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
			try {
				$nonFiniteConst->to('px');
				self::fail('Expected incompatible unit conversion on CSSMathConstant to throw.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
	}
}
