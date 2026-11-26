<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric\Math;

use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathFunction;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathFunctionEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSRoundingStrategy;
use PHPUnit\Framework\TestCase;

final class CSSMathFunctionTest extends TestCase
{
	public function testSteppedFunctionsConstructionSerializationAndEquality(): void
	{
		$roundDefault = new CSSMathFunction('round', [CSS::em(1.5), CSS::px(2)]);
		self::assertSame('round', $roundDefault->operator);
		self::assertSame(CSSMathFunctionEnum::ROUND, $roundDefault->function);
		self::assertSame(CSSRoundingStrategy::NEAREST, $roundDefault->strategy);
		self::assertSame('round(1.5em, 2px)', (string) $roundDefault);
		self::assertSame(1, $roundDefault->type->length);

		$roundUp = new CSSMathFunction(CSSMathFunctionEnum::ROUND, [CSS::em(1.5), CSS::px(2)], CSSRoundingStrategy::UP);
		self::assertSame(CSSRoundingStrategy::UP, $roundUp->strategy);
		self::assertSame('round(up, 1.5em, 2px)', (string) $roundUp);
		self::assertFalse($roundDefault->equals($roundUp));

		$roundOneArg = new CSSMathFunction('round', CSS::number(2.3), 'to-zero');
		self::assertSame('round(to-zero, 2.3)', (string) $roundOneArg);
		self::assertSame([], $roundOneArg->type->toArray());

		$mod = new CSSMathFunction('mod', [CSS::em(5), CSS::px(3)]);
		self::assertSame('mod', $mod->operator);
		self::assertSame('mod(5em, 3px)', (string) $mod);
		self::assertSame(1, $mod->type->length);

		$rem = new CSSMathFunction('rem', [CSS::percent(55), CSS::px(10)]);
		self::assertSame('rem', $rem->operator);
		self::assertSame('rem(55%, 10px)', (string) $rem);
		self::assertSame('length', $rem->type->percentHint);
	}

	public function testTrigonometricExponentialAndSignFunctions(): void
	{
		$sin = new CSSMathFunction('sin', CSS::deg(30));
		self::assertSame('sin', $sin->operator);
		self::assertSame('sin(30deg)', (string) $sin);
		self::assertSame([], $sin->type->toArray());

		$cos = new CSSMathFunction('cos', CSS::number(1));
		self::assertSame('cos', $cos->operator);
		self::assertSame([], $cos->type->toArray());

		$tan = new CSSMathFunction('tan', CSS::rad(0.5));
		self::assertSame('tan', $tan->operator);
		self::assertSame([], $tan->type->toArray());

		$asin = new CSSMathFunction('asin', CSS::number(0.5));
		self::assertSame('asin', $asin->operator);
		self::assertSame(1, $asin->type->angle);

		$acos = new CSSMathFunction('acos', CSS::number(0.5));
		self::assertSame(1, $acos->type->angle);

		$atan = new CSSMathFunction('atan', CSS::number(1));
		self::assertSame(1, $atan->type->angle);

		$atan2 = new CSSMathFunction('atan2', [CSS::em(1), CSS::px(10)]);
		self::assertSame('atan2', $atan2->operator);
		self::assertSame('atan2(1em, 10px)', (string) $atan2);
		self::assertSame(1, $atan2->type->angle);

		$pow = new CSSMathFunction('pow', [CSS::number(2), CSS::number(3)]);
		self::assertSame('pow', $pow->operator);
		self::assertSame([], $pow->type->toArray());

		$sqrt = new CSSMathFunction('sqrt', CSS::number(9));
		self::assertSame('sqrt', $sqrt->operator);
		self::assertSame([], $sqrt->type->toArray());

		$hypot = new CSSMathFunction('hypot', [CSS::em(3), CSS::px(4), CSS::vw(5)]);
		self::assertSame('hypot', $hypot->operator);
		self::assertSame('hypot(3em, 4px, 5vw)', (string) $hypot);
		self::assertSame(1, $hypot->type->length);

		$log1 = new CSSMathFunction('log', CSS::number(10));
		self::assertSame('log(10)', (string) $log1);
		$log2 = new CSSMathFunction('log', [CSS::number(8), CSS::number(2)]);
		self::assertSame('log(8, 2)', (string) $log2);

		$exp = new CSSMathFunction('exp', CSS::number(1));
		self::assertSame('exp', $exp->operator);

		$abs = new CSSMathFunction('abs', CSS::em(-2));
		self::assertSame('abs', $abs->operator);
		self::assertSame(1, $abs->type->length);

		$sign = new CSSMathFunction('sign', CSS::em(-2));
		self::assertSame('sign', $sign->operator);
		self::assertSame([], $sign->type->toArray());
	}

	public function testCloningConversionsAndToSum(): void
	{
		$round = new CSSMathFunction('round', [CSS::em(1.5), CSS::px(8)], CSSRoundingStrategy::DOWN);
		$clone = $round->clone();
		self::assertInstanceOf(CSSMathFunction::class, $clone);
		self::assertNotSame($round, $clone);
		self::assertSame(CSSRoundingStrategy::DOWN, $clone->strategy);
		self::assertTrue($round->equals($clone));

		// Same-dimension functions support to() unit conversion
		$absPx = (new CSSMathFunction('abs', CSS::em(-2)))->to('px', new CSSContext(16.0));
		self::assertInstanceOf(CSSMathFunction::class, $absPx);
		self::assertSame('abs(-32px)', (string) $absPx);

		$roundToPx = $round->to('px', new CSSContext(16.0));
		self::assertInstanceOf(CSSMathFunction::class, $roundToPx);
		self::assertSame('round(down, 24px, 8px)', (string) $roundToPx);

		// Number-returning functions support to('number')
		$sinNum = (new CSSMathFunction('sin', CSS::deg(30)))->to('number');
		self::assertInstanceOf(CSSMathFunction::class, $sinNum);
		self::assertSame('sin(30deg)', (string) $sinNum);

		// Angle-returning functions convert via simplification
		$asinRad = (new CSSMathFunction('asin', CSS::number(1)))->to('rad');
		self::assertInstanceOf(CSSUnitValue::class, $asinRad);
		self::assertEqualsWithDelta(M_PI_2, $asinRad->value, 1e-9);

		// toSum evaluates foldable CSSMathFunction terms
		$sumWithAbs = new CSSMathSum([CSS::px(10), new CSSMathFunction('abs', CSS::em(-1))]);
		$toSum = $sumWithAbs->toSum('px');
		self::assertSame(26.0, $toSum->values[0]->value);

		$sinSum = (new CSSMathFunction('sin', CSS::deg(90)))->toSum('number');
		self::assertEqualsWithDelta(1.0, $sinSum->values[0]->value, 1e-9);
	}

	public function testParsingNewFunctionsAndEscapedStrategies(): void
	{
		$parsedRound = CSSNumericValue::parse('r\\6fund(  \\75 p /*c*/, 1em, 10px)');
		self::assertInstanceOf(CSSMathFunction::class, $parsedRound);
		self::assertSame(CSSMathFunctionEnum::ROUND, $parsedRound->function);
		self::assertSame(CSSRoundingStrategy::UP, $parsedRound->strategy);
		self::assertSame('round(up, 1em, 10px)', (string) $parsedRound);

		$nestedInCalc = CSSNumericValue::parse('calc(1em + abs(-2vw + 1px))');
		self::assertInstanceOf(CSSMathSum::class, $nestedInCalc);
		self::assertSame('calc(1em + abs(1px - 2vw))', (string) $nestedInCalc);

		$foldedInCalc = CSSNumericValue::parse('calc(1em + abs(-2vw))');
		self::assertSame('calc(1em + 2vw)', (string) $foldedInCalc);

		// Unresolved percentage inside non-linear function is preserved
		$unfoldedMod = CSSNumericValue::parse('mod(10%, 3%)');
		self::assertInstanceOf(CSSMathFunction::class, $unfoldedMod);
		self::assertSame('mod(10%, 3%)', (string) $unfoldedMod);

		// CSSMathNegate wrapping CSSMathFunction / Min / Max / Clamp parenthesizes safely and round-trips
		$negatedAbs = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate(
			new CSSMathFunction('abs', CSS::percent(1)),
		);
		self::assertSame('calc(-(abs(1%)))', (string) $negatedAbs);
		self::assertTrue($negatedAbs->equals(CSSNumericValue::parse((string) $negatedAbs)));

		$negatedMin = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate(
			new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin([CSS::em(1), CSS::vw(1)]),
		);
		self::assertSame('calc(-(min(1em, 1vw)))', (string) $negatedMin);
		self::assertTrue($negatedMin->equals(CSSNumericValue::parse((string) $negatedMin)));

		$negatedMax = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate(
			new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax([CSS::em(1), CSS::vw(1)]),
		);
		self::assertSame('calc(-(max(1em, 1vw)))', (string) $negatedMax);

		$negatedClamp = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate(
			new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathClamp(CSS::em(1), CSS::vw(2), CSS::px(50)),
		);
		self::assertSame('calc(-(clamp(1em, 2vw, 50px)))', (string) $negatedClamp);

		// CSS Syntax L3 §4.3.3: -<func>( is a single invalid function token, not unary negation
		foreach (['calc(-abs(1%))', 'calc(-sin(1deg))', 'calc(-min(1px, 2px))', 'calc(1px + -abs(1%))', '-abs(1%)'] as $badNegFn) {
			try {
				CSSNumericValue::parse($badNegFn);
				self::fail('Expected invalid -func( token to be rejected: ' . $badNegFn);
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// percentHint propagation and rejection in CSSMathFunction
		$absHint = new CSSMathFunction('abs', new CSSMathSum([CSS::px(10), CSS::percent(50)]));
		self::assertSame('length', $absHint->type->percentHint);
		self::assertSame(1, $absHint->type->length);

		$roundHint = new CSSMathFunction('round', [new CSSMathSum([CSS::px(10), CSS::percent(50)]), CSS::px(5)]);
		self::assertSame('length', $roundHint->type->percentHint);

		$hypotHint = new CSSMathFunction('hypot', [CSS::px(10), CSS::percent(50)]);
		self::assertSame('length', $hypotHint->type->percentHint);
	}

	public function testRejectsInvalidOperatorsStrategiesAritiesAndTypes(): void
	{
		$invalidCalls = [
			static fn () => new CSSMathFunction('sum', [CSS::number(1)]),
			static fn () => new CSSMathFunction('unknown', [CSS::number(1)]),
			static fn () => new CSSMathFunction('sin', CSS::number(1), CSSRoundingStrategy::UP),
			static fn () => new CSSMathFunction('round', CSS::number(1), 'invalid-strategy'),
			static fn () => new CSSMathFunction('round', CSS::px(10)),
			static fn () => new CSSMathFunction('round', [CSS::number(1), CSS::number(2), CSS::number(3)]),
			static fn () => new CSSMathFunction('sin', CSS::px(10)),
			static fn () => new CSSMathFunction('asin', CSS::deg(45)),
			static fn () => new CSSMathFunction('atan2', [CSS::px(10), CSS::s(1)]),
			static fn () => new CSSMathFunction('pow', [CSS::px(2), CSS::number(3)]),
			static fn () => new CSSMathFunction('pow', [CSS::number(2), CSS::px(3)]),
			static fn () => new CSSMathFunction('sqrt', CSS::px(9)),
			static fn () => new CSSMathFunction('log', CSS::deg(10)),
			static fn () => new CSSMathFunction('log', [CSS::number(10), CSS::number(2), CSS::number(3)]),
			static fn () => new CSSMathFunction('exp', CSS::px(1)),
			static fn () => new CSSMathFunction('mod', CSS::px(10)),
			static fn () => new CSSMathFunction('mod', [CSS::px(10), CSS::deg(2)]),
			static fn () => new CSSMathFunction('hypot', [CSS::px(3), CSS::s(4)]),
			static fn () => new CSSMathFunction('round', [
				new CSSMathSum([CSS::px(10), CSS::percent(50)]),
				new CSSMathSum([CSS::s(5), CSS::percent(5)]),
			]),
			static fn () => new CSSMathFunction('hypot', [
				new CSSMathSum([CSS::px(10), CSS::percent(50)]),
				new CSSMathSum([CSS::s(5), CSS::percent(5)]),
			]),
			static fn () => CSSNumericValue::parse('round()'),
			static fn () => CSSNumericValue::parse('round(up)'),
			static fn () => CSSNumericValue::parse('round(10px, 20px, 30px)'),
			static fn () => CSSNumericValue::parse('sin()'),
			static fn () => CSSNumericValue::parse('sin(1, 2)'),
			static fn () => CSSNumericValue::parse('pow(2)'),
			static fn () => CSSNumericValue::parse('atan2(1px)'),
		];

		foreach ($invalidCalls as $call) {
			try {
				$call();
				self::fail('Expected InvalidArgumentException for invalid CSSMathFunction usage.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}
	}
}
