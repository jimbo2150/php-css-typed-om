<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use PHPUnit\Framework\TestCase;

class CSSNumericValueTest extends TestCase
{
	public function testToMethodSameUnit(): void
	{
		$value = new CSSUnitValue(10, 'px');
		$converted = $value->to('px');

		$this->assertInstanceOf(CSSUnitValue::class, $converted);
		$this->assertEquals(10, $converted->value);
		$this->assertEquals('px', $converted->unit);
		$this->assertNotSame($value, $converted); // Should be a new instance
	}

	public function testToMethodPxToEm(): void
	{
		$value = new CSSUnitValue(16, 'px');
		$converted = $value->to('em');

		$this->assertInstanceOf(CSSUnitValue::class, $converted);
		$this->assertEqualsWithDelta(1.0, $converted->value, 0.001);
		$this->assertEquals('em', $converted->unit);
	}

	public function testToMethodEmToPx(): void
	{
		$value = new CSSUnitValue(2, 'em');
		$converted = $value->to('px');

		$this->assertInstanceOf(CSSUnitValue::class, $converted);
		$this->assertEqualsWithDelta(32.0, $converted->value, 0.001);
		$this->assertEquals('px', $converted->unit);
	}

	public function testToMethodPtToPx(): void
	{
		$value = new CSSUnitValue(12, 'pt');
		$converted = $value->to('px');

		$this->assertInstanceOf(CSSUnitValue::class, $converted);
		$this->assertEqualsWithDelta(16.0, $converted->value, 0.01); // 12pt * 1.333 ≈ 16px
		$this->assertEquals('px', $converted->unit);
	}

	public function testToMethodPxToPt(): void
	{
		$value = new CSSUnitValue(16, 'px');
		$converted = $value->to('pt');

		$this->assertInstanceOf(CSSUnitValue::class, $converted);
		$this->assertEqualsWithDelta(12.0, $converted->value, 0.01); // 16px / 1.333 ≈ 12pt
		$this->assertEquals('pt', $converted->unit);
	}

	public function testToMethodCmToPx(): void
	{
		$value = new CSSUnitValue(1, 'cm');
		$converted = $value->to('px');

		$this->assertInstanceOf(CSSUnitValue::class, $converted);
		$this->assertEqualsWithDelta(37.795, $converted->value, 0.001);
		$this->assertEquals('px', $converted->unit);
	}

	public function testToMethodPxToCm(): void
	{
		$value = new CSSUnitValue(37.795, 'px');
		$converted = $value->to('cm');

		$this->assertInstanceOf(CSSUnitValue::class, $converted);
		$this->assertEqualsWithDelta(1.0, $converted->value, 0.001);
		$this->assertEquals('cm', $converted->unit);
	}

	public function testToSumMethodSingleUnit(): void
	{
		$value = new CSSUnitValue(10, 'px');
		$sum = $value->toSum('em');

		$this->assertInstanceOf(CSSMathSum::class, $sum);
		/** @var CSSMathSum $sum */
		$this->assertEquals(1, $sum->length);
		$this->assertEquals('em', $sum->inner_values[0]->unit);
		$this->assertEqualsWithDelta(0.625, $sum->inner_values[0]->value, 0.001); // 10px / 16 = 0.625em
	}

	public function testToSumMethodMultipleUnits(): void
	{
		$value = new CSSUnitValue(16, 'px');
		$sum = $value->toSum('px', 'em', 'pt');

		$this->assertInstanceOf(CSSMathSum::class, $sum);
		/** @var CSSMathSum $sum */
		$this->assertEquals(3, $sum->length);

		// First matching unit absorbs the px value; remaining units are zeroed to preserve total
		$this->assertEquals('px', $sum->inner_values[0]->unit);
		$this->assertEquals(16, $sum->inner_values[0]->value);

		$this->assertEquals('em', $sum->inner_values[1]->unit);
		$this->assertEqualsWithDelta(0.0, $sum->inner_values[1]->value, 0.001);

		$this->assertEquals('pt', $sum->inner_values[2]->unit);
		$this->assertEqualsWithDelta(0.0, $sum->inner_values[2]->value, 0.01);
	}

	public function testToSumPreservesTotalAcrossFirstMatchingBucketDuplicatesMixedAndNegatedTerms(): void
	{
		$converted = CSS::px(16)->toSum('em', 'pt');
		self::assertCount(2, $converted->values);
		self::assertSame('em', $converted->values[0]->unit);
		self::assertEqualsWithDelta(1.0, $converted->values[0]->value, 0.001);
		self::assertSame('pt', $converted->values[1]->unit);
		self::assertEqualsWithDelta(0.0, $converted->values[1]->value, 0.001);

		$duplicate = CSS::px(16)->toSum('px', 'px');
		self::assertCount(1, $duplicate->values);
		self::assertEqualsWithDelta(16.0, $duplicate->values[0]->value, 0.001);

		$mixed = CSSNumericValue::parse('calc(10px + 2em + 50%)')->toSum('px', 'percent');
		self::assertCount(2, $mixed->values);
		self::assertSame('px', $mixed->values[0]->unit);
		self::assertEqualsWithDelta(42.0, $mixed->values[0]->value, 0.001);
		self::assertSame('percent', $mixed->values[1]->unit);
		self::assertEqualsWithDelta(50.0, $mixed->values[1]->value, 0.001);

		$negated = (new CSSMathNegate(CSS::px(10)))->toSum('px');
		self::assertCount(1, $negated->values);
		self::assertSame('px', $negated->values[0]->unit);
		self::assertEqualsWithDelta(-10.0, $negated->values[0]->value, 0.001);
	}

	public function testToSumRejectsIncompatiblePercentConversion(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		(new CSSUnitValue(16, 'px'))->toSum('percent');
	}

	public function testArithmeticMethodsAcceptMultipleOperands(): void
	{
		$value = new CSSUnitValue(10, 'px');
		$sum = $value->add(new CSSUnitValue(2, 'em'), new CSSUnitValue(3, 'px'));
		$minimum = $value->min(new CSSUnitValue(2, 'px'), new CSSUnitValue(3, 'px'));

		self::assertInstanceOf(CSSMathSum::class, $sum);
		self::assertCount(3, $sum->values);
		self::assertInstanceOf(CSSMathMin::class, $minimum);
		self::assertCount(3, $minimum->values);
	}

	public function testRejectsIncompatibleUnitConversionAndAddition(): void
	{
		try {
			(new CSSUnitValue(10, 'px'))->to('deg');
			self::fail('Expected a cross-dimension conversion to throw.');
		} catch (\InvalidArgumentException $e) {
			self::assertStringContainsString('cannot convert', strtolower($e->getMessage()));
		}

		$this->expectException(\InvalidArgumentException::class);
		(new CSSUnitValue(10, 'px'))->add(new CSSUnitValue(1, 's'));
	}

	public function testConvertsAnglesAndTimes(): void
	{
		self::assertEqualsWithDelta(180.0, (new CSSUnitValue(M_PI, 'rad'))->to('deg')->value, 0.0001);
		self::assertEqualsWithDelta(1000.0, (new CSSUnitValue(1, 's'))->to('ms')->value, 0.0001);
	}

	public function testNumericTypeVectorsExposeUnitAndMathDimensions(): void
	{
		$lengthType = (new CSSUnitValue(10, 'px'))->type;
		$productType = (new CSSUnitValue(10, 'px'))->mul(new CSSUnitValue(2, 'px'))->type;
		$numberType = (new CSSUnitValue(2, 'number'))->type;

		self::assertSame(1, $lengthType->length);
		self::assertSame(2, $productType->length);
		self::assertSame([], $numberType->toArray());
	}

	public function testConvertsMathValuesWithoutDiscardingTheirStructure(): void
	{
		$sum = CSSNumericValue::parse('calc(16px + 16px)')->to('em');
		$product = (new CSSUnitValue(16, 'px'))->div(new CSSUnitValue(2, 'number'))->to('em');

		self::assertInstanceOf(CSSMathSum::class, $sum);
		self::assertSame('calc(1em + 1em)', (string) $sum);
		self::assertInstanceOf(CSSMathProduct::class, $product);
		self::assertSame('calc(1em / 2)', (string) $product);
	}

	public function testToSumMethodEmptyUnits(): void
	{
		$value = new CSSUnitValue(10, 'px');
		$sum = $value->toSum();

		$this->assertInstanceOf(CSSMathSum::class, $sum);
		$this->assertEquals(1, $sum->length);
		$this->assertSame($value, $sum->inner_values[0]);
	}

	public function testEqualsSameUnitValue(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(10, 'px');

		$this->assertTrue($value1->equals($value2));
	}

	public function testEqualsSameUnitDifferentValue(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(20, 'px');

		$this->assertFalse($value1->equals($value2));
	}

	public function testEqualsDifferentConvertibleUnits(): void
	{
		$value1 = new CSSUnitValue(16, 'px');
		$value2 = new CSSUnitValue(1, 'em');

		$this->assertTrue($value1->equals($value2));
	}

	public function testEqualsDifferentConvertibleUnitsNotEqual(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(1, 'em');

		$this->assertFalse($value1->equals($value2));
	}

	public function testEqualsDifferentConvertibleUnitsNotEqual3(): void
	{
		$value1 = new CSSUnitValue(1, 's');
		$value2 = new CSSUnitValue(100, 'ms');

		$this->assertFalse($value1->equals($value2));
	}

	public function testEqualsWithConversionError(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new class(10, 'em') extends CSSUnitValue {
			public function to(string $unit, ?\Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext $context = null): CSSNumericValue
			{
				throw new \Exception('Conversion failed');
			}
		};

		$this->assertFalse($value1->equals($value2));
	}

	public function testEqualsIncompatibleUnits(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(10, 'deg');

		$this->assertFalse($value1->equals($value2));
	}

	public function testEqualsMathSumSameValues(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(5, 'px');
		$sum1 = new CSSMathSum([$value1, $value2]);

		$value3 = new CSSUnitValue(10, 'px');
		$value4 = new CSSUnitValue(5, 'px');
		$sum2 = new CSSMathSum([$value3, $value4]);

		$this->assertTrue($sum1->equals($sum2));
	}

	public function testEqualsMathSumDifferentValues(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(5, 'px');
		$sum1 = new CSSMathSum([$value1, $value2]);

		$value3 = new CSSUnitValue(10, 'px');
		$value4 = new CSSUnitValue(6, 'px');
		$sum2 = new CSSMathSum([$value3, $value4]);

		$this->assertFalse($sum1->equals($sum2));
	}

	public function testEqualsDifferentTypes(): void
	{
		$unitValue = new CSSUnitValue(10, 'px');
		$sum = new CSSMathSum([new CSSUnitValue(10, 'px')]);

		$this->assertFalse($unitValue->equals($sum));
	}

	public function testEqualsMathSumDifferentNumberOfValues(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(5, 'px');
		$sum1 = new CSSMathSum([$value1, $value2]);

		$value3 = new CSSUnitValue(10, 'px');
		$sum2 = new CSSMathSum([$value3]);

		$this->assertFalse($sum1->equals($sum2));
	}

	public function testEqualsDifferentConvertibleUnitsNotEqual2(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(2, 'em');

		$this->assertFalse($value1->equals($value2));
	}

	public function testAddMethod(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(5, 'px');
		$result = $value1->add($value2);

		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(15, $result->value);
		$this->assertEquals('px', $result->unit);
	}

	public function testAddMethodStringCast(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(5, 'px');
		$result = $value1->add($value2);

		$this->assertEquals('15px', (string)$result);
	}

	public function testEqualsMathProductSameValues(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(2, 'number');
		$product1 = new CSSMathProduct([$value1, $value2]);

		$value3 = new CSSUnitValue(10, 'px');
		$value4 = new CSSUnitValue(2, 'number');
		$product2 = new CSSMathProduct([$value3, $value4]);

		$this->assertTrue($product1->equals($product2));
	}

	public function testSubMethod(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$sum = new CSSMathSum([new CSSUnitValue(5, 'px')]);
		$result = $value1->sub($sum);

		$this->assertInstanceOf(CSSMathSum::class, $result);
		/** @var CSSMathSum $result */
		$this->assertEquals(2, $result->length);
		$this->assertEquals(10, $result->inner_values[0]->value);
		$this->assertEquals('px', $result->inner_values[0]->unit);
		/** @var CSSMathNegate $negate */
		$negate = $result->inner_values[1];
		$this->assertInstanceOf(CSSMathNegate::class, $negate);
		/** @var CSSMathSum $innerSum */
		$innerSum = $negate->inner_values[0];
		$this->assertInstanceOf(CSSMathSum::class, $innerSum);
		$this->assertEquals(1, $innerSum->length);
		$this->assertEquals(5, $innerSum->inner_values[0]->value);
		$this->assertEquals('px', $innerSum->inner_values[0]->unit);
	}

	public function testSubMethodStringCast(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(5, 'px');
		$result = $value1->sub($value2);

		$this->assertEquals('5px', (string)$result);
	}

	public function testSubMethodWithCSSUnitValue(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(5, 'px');
		$result = $value1->sub($value2);

		self::assertInstanceOf(CSSUnitValue::class, $result);
		self::assertSame(5.0, $result->value);
		self::assertSame('px', $result->unit);
	}

	public function testMulMethod(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(2, 'number');
		$result = $value1->mul($value2);

		$this->assertInstanceOf(CSSMathProduct::class, $result);
		/** @var CSSMathProduct $result */
		$this->assertEquals(2, $result->length);
		$this->assertEquals(10, $result->inner_values[0]->value);
		$this->assertEquals('px', $result->inner_values[0]->unit);
		$this->assertInstanceOf(CSSUnitValue::class, $result->inner_values[1]);
		$this->assertEquals(2, $result->inner_values[1]->value);
		$this->assertEquals('number', $result->inner_values[1]->unit);
	}

	public function testMulMethodStringCast(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(2, 'number');
		$result = $value1->mul($value2);

		$this->assertEquals('calc(10px * 2)', (string)$result);
	}

	public function testDivMethod(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(2, 'number');
		$result = $value1->div($value2);

		$this->assertInstanceOf(CSSMathProduct::class, $result);
		/** @var CSSMathProduct $result */
		$this->assertEquals(2, $result->length);
		$this->assertEquals(10, $result->inner_values[0]->value);
		$this->assertEquals('px', $result->inner_values[0]->unit);
		$this->assertInstanceOf(CSSMathInvert::class, $result->inner_values[1]);
		/** @var CSSMathInvert $invert */
		$invert = $result->inner_values[1];
		$this->assertEquals(1, $invert->length);
		$this->assertEquals(2, $invert->inner_values[0]->value);
		$this->assertEquals('number', $invert->inner_values[0]->unit);
	}

	public function testDivMethodStringCast(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$value2 = new CSSUnitValue(2, 'number');
		$result = $value1->div($value2);

		$this->assertEquals('calc(10px / 2)', (string)$result);
	}

	public function testMinMethod(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$array = new CSSNumericArray([new CSSUnitValue(5, 'px'), new CSSUnitValue(15, 'px')]);
		$result = $value1->min($array);

		$this->assertInstanceOf(CSSMathMin::class, $result);
		/** @var CSSMathMin $result */
		$this->assertEquals(3, $result->length);
		$this->assertEquals(10, $result->inner_values[0]->value);
		$this->assertEquals('px', $result->inner_values[0]->unit);
		$this->assertEquals(5, $result->inner_values[1]->value);
		$this->assertEquals('px', $result->inner_values[1]->unit);
		$this->assertEquals(15, $result->inner_values[2]->value);
		$this->assertEquals('px', $result->inner_values[2]->unit);
	}

	public function testMinMethodStringCast(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$array = new CSSNumericArray([new CSSUnitValue(5, 'px'), new CSSUnitValue(15, 'px')]);
		$result = $value1->min($array);

		$this->assertEquals('min(10px, 5px, 15px)', (string)$result);
	}

	public function testMaxMethod(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$array = new CSSNumericArray([new CSSUnitValue(5, 'px'), new CSSUnitValue(15, 'px')]);
		$result = $value1->max($array);

		$this->assertInstanceOf(CSSMathMax::class, $result);
		/** @var CSSMathMax $result */
		$this->assertEquals(3, $result->length);
		$this->assertEquals(10, $result->inner_values[0]->value);
		$this->assertEquals('px', $result->inner_values[0]->unit);
		$this->assertEquals(5, $result->inner_values[1]->value);
		$this->assertEquals('px', $result->inner_values[1]->unit);
		$this->assertEquals(15, $result->inner_values[2]->value);
		$this->assertEquals('px', $result->inner_values[2]->unit);
	}

	public function testMaxMethodStringCast(): void
	{
		$value1 = new CSSUnitValue(10, 'px');
		$array = new CSSNumericArray([new CSSUnitValue(5, 'px'), new CSSUnitValue(15, 'px')]);
		$result = $value1->max($array);

		$this->assertEquals('max(10px, 5px, 15px)', (string)$result);
	}

	public function testParseSimpleNumber(): void
	{
		$result = CSSNumericValue::parse('123');
		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(123, $result->value);
		$this->assertEquals('number', $result->unit);
	}

	public function testParseWithUnit(): void
	{
		$result = CSSNumericValue::parse('10px');
		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(10, $result->value);
		$this->assertEquals('px', $result->unit);
	}

	public function testParseNegative(): void
	{
		$result = CSSNumericValue::parse('-5em');
		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(-5, $result->value);
		$this->assertEquals('em', $result->unit);
	}

	public function testParseDecimal(): void
	{
		$result = CSSNumericValue::parse('1.5rem');
		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(1.5, $result->value);
		$this->assertEquals('rem', $result->unit);
	}

	public function testParsePercent(): void
	{
		$result = CSSNumericValue::parse('50%');
		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(50, $result->value);
		$this->assertEquals('percent', $result->unit);
	}

	public function testParseTrimmed(): void
	{
		$result = CSSNumericValue::parse('  10px  ');
		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(10, $result->value);
		$this->assertEquals('px', $result->unit);
	}

	public function testParseDegree(): void
	{
		$result = CSSNumericValue::parse('365deg');
		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(365, $result->value);
		$this->assertEquals('deg', $result->unit);
	}

	public function testParseTurn(): void
	{
		$result = CSSNumericValue::parse('5turn');
		$this->assertInstanceOf(CSSUnitValue::class, $result);
		$this->assertEquals(5, $result->value);
		$this->assertEquals('turn', $result->unit);
	}

	public function testParseInvalid(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		CSSNumericValue::parse('abc');
	}

	public function testParseInvalidMultiple(): void
	{
		$this->expectException(\InvalidArgumentException::class);
		CSSNumericValue::parse('10px 20px');
	}

	/**
	 * Test to method with custom CSSContext for em conversion.
	 */
	public function testToMethodWithCustomContextEm(): void
	{
		$value = new CSSUnitValue(20, 'px');
		$context = (new \Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext())->setFontSize(10.0);
		$converted = $value->to('em', $context);

		$this->assertInstanceOf(CSSUnitValue::class, $converted);
		$this->assertEqualsWithDelta(2.0, $converted->value, 0.001); // 20px / 10px font-size = 2em
		$this->assertEquals('em', $converted->unit);
	}

	/**
	 * Test to method with custom CSSContext for vw conversion.
	 */
	public function testToMethodWithCustomContextVw(): void
	{
		$value = new CSSUnitValue(48, 'px');
		$context = (new \Jimbo2150\PhpCssTypedOm\TypedOM\CSSContext())->setViewportWidth(800.0);
		$converted = $value->to('vw', $context);

		$this->assertInstanceOf(CSSUnitValue::class, $converted);
		$this->assertEqualsWithDelta(6.0, $converted->value, 0.001); // 48px / (800/100) = 6vw
		$this->assertEquals('vw', $converted->unit);
	}

	/**
	 * Test to method with default CSSContext for vh conversion.
	 */
	public function testToMethodWithDefaultContextVh(): void
	{
		$value = new CSSUnitValue(27, 'px');
		$converted = $value->to('vh'); // Uses default context (vh factor 5.4px)

		$this->assertInstanceOf(CSSUnitValue::class, $converted);
		$this->assertEqualsWithDelta(5.0, $converted->value, 0.001); // 27 / 5.4 = 5vh
		$this->assertEquals('vh', $converted->unit);
	}

	public function testFrom(): void
	{
		$this->assertEquals(CSSNumericValue::parse('10px'), (new CSSUnitValue(10, 'px'))->from('10px'));
	}

	public function testTypeMethod(): void
	{
		$pxValue = new CSSUnitValue(10, 'px');
		$this->assertSame(1, $pxValue->type()->length);
		$this->assertSame('length', $pxValue->unitType());

		$degValue = new CSSUnitValue(90, 'deg');
		$this->assertSame(1, $degValue->type()->angle);

		$sValue = new CSSUnitValue(2, 's');
		$this->assertSame(1, $sValue->type()->time);

		$hzValue = new CSSUnitValue(100, 'hz');
		$this->assertSame(1, $hzValue->type()->frequency);

		$khzValue = new CSSUnitValue(2, 'khz');
		$this->assertSame(1, $khzValue->type()->frequency);

		$dpiValue = new CSSUnitValue(300, 'dpi');
		$this->assertSame(1, $dpiValue->type()->resolution);

		$frValue = new CSSUnitValue(1, 'fr');
		$this->assertSame(1, $frValue->type()->flex);

		$percentValue = new CSSUnitValue(50, 'percent');
		$this->assertSame(1, $percentValue->type()->percent);
	}

	public function testTypeMethodWithNoUnit(): void
	{
		$this->expectException(\LogicException::class);
		$this->expectExceptionMessage('No unit set');
		$value = new CSSUnitValue(10, 'number');
		$reflectedProperty = new \ReflectionProperty(CSSUnitValue::class, 'unitObj');
		$reflectedProperty->setValue($value, null);
		$value->unitType();
	}

	public function testConcreteEvaluationAndPercentHintConsistency(): void
	{
		$converted = (new CSSUnitValue(2, 'number'))->mul(new CSSUnitValue(3, 'number'))->to('number');
		$this->assertSame('calc(2 * 3)', (string) $converted);
		$evaluatedNum = $converted->toSum('number');
		$this->assertSame(6.0, $evaluatedNum->inner_values[0]->value);
		$this->assertSame('number', $evaluatedNum->inner_values[0]->unit);

		$sum = (new CSSUnitValue(10, 'px'))->mul(new CSSUnitValue(2, 'number'))->toSum('px');
		$this->assertCount(1, $sum->inner_values);
		$this->assertSame(20.0, $sum->inner_values[0]->value);
		$this->assertSame('px', $sum->inner_values[0]->unit);

		$sumA = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum([
			new CSSUnitValue(10, 'px'),
			new CSSUnitValue(50, 'percent'),
		]);
		$sumB = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum([
			new CSSUnitValue(2, 's'),
			new CSSUnitValue(20, 'percent'),
		]);

		try {
			$sumA->mul($sumB);
			$this->fail('Expected multiplying conflicting percentHints to throw.');
		} catch (\InvalidArgumentException $e) {
			self::assertStringContainsString('percent', strtolower($e->getMessage()));
		}

		// Eager CSSMathProduct validation rejects conflicting percentHints even before ->type()
		try {
			new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct([$sumA, $sumB]);
			$this->fail('Expected CSSMathProduct constructor with conflicting percentHints to throw.');
		} catch (\InvalidArgumentException $e) {
			self::assertStringContainsString('percent', strtolower($e->getMessage()));
		}

		$neg = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate(new CSSUnitValue(5, 'px'));
		$negClone = clone $neg;
		$this->assertNotSame($neg->value, $negClone->value);
	}

	public function testPercentHintValidationCalcUnclosedCommentAndMathNodeBudget(): void
	{
		// applyPercentHint('percent') must not double percent exponent, and invalid hints throw
		$pctType = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericType(['percent' => 1]);
		$hintedPct = $pctType->applyPercentHint('percent');
		self::assertSame(1, $hintedPct->percent);
		self::assertSame('percent', $hintedPct->percentHint);

		foreach (['invalid', 'percentHint'] as $badHint) {
			try {
				$pctType->applyPercentHint($badHint);
				self::fail('Expected invalid percent hint to throw: ' . $badHint);
			} catch (\InvalidArgumentException $e) {
				self::assertStringContainsString('percent hint', strtolower($e->getMessage()));
			}
		}

		// Unclosed comments in calc()/min() fail deterministically
		foreach ([
			'calc(1px /* unclosed)',
			'min(1px /* unclosed, 2px)',
			'min(1px /* ok */, /* unclosed)',
			'min(10px /* unclosed)',
		] as $unclosedExpr) {
			try {
				CSSNumericValue::parse($unclosedExpr);
				self::fail('Expected unclosed comment to throw InvalidArgumentException: ' . $unclosedExpr);
			} catch (\InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		// Negative zero canonicalization prevents invalid `--0px` output
		$negZero = CSSNumericValue::parse('calc(-0px)');
		self::assertInstanceOf(CSSUnitValue::class, $negZero);
		self::assertSame('0px', (string) $negZero);
		self::assertSame('0px', (string) (new CSSUnitValue(-0.0, 'px')));

		// Variadic operand caps enforce MAX_MATH_NODES even when bypassing constructor
		$overflowTerms = array_fill(0, CSSNumericValue::MAX_MATH_NODES + 1, new CSSUnitValue(1, 'px'));
		foreach ([
			static fn () => (new CSSUnitValue(1, 'px'))->add(...$overflowTerms),
			static fn () => (new CSSUnitValue(1, 'px'))->sub(...$overflowTerms),
			static fn () => (new CSSUnitValue(1, 'px'))->div(...$overflowTerms),
			static fn () => (new CSSUnitValue(1, 'px'))->min(...$overflowTerms),
			static fn () => (new CSSUnitValue(1, 'px'))->max(...$overflowTerms),
			static fn () => new CSSMathSum($overflowTerms),
		] as $overflowCall) {
			try {
				$overflowCall();
				self::fail('Expected operand explosion exceeding MAX_MATH_NODES to throw CSSParserLimitException.');
			} catch (\Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException $e) {
				self::assertStringContainsString('node count', strtolower($e->getMessage()));
			}
		}

		// Flattening large CSSNumericArray inputs checks limits incrementally
		$arr = new \Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray(
			array_fill(0, 20_000, new CSSUnitValue(1, 'px'))
		);
		try {
			(new CSSUnitValue(1, 'px'))->min($arr, $arr, $arr, $arr, $arr);
			self::fail('Expected flattened nested CSSNumericArray exceeding MAX_MATH_NODES to throw CSSParserLimitException.');
		} catch (\Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException $e) {
			self::assertStringContainsString('node count', strtolower($e->getMessage()));
		}

		// div() counts unflattened operands so nested product chains cannot exceed MAX_MATH_NODES
		$divChunks = array_fill(0, 50_000, new CSSUnitValue(2, 'number'));
		try {
			(new CSSUnitValue(10, 'px'))->div(...$divChunks);
			self::fail('Expected div() operand explosion to throw CSSParserLimitException.');
		} catch (\Jimbo2150\PhpCssTypedOm\CSSOM\CSSParserLimitException $e) {
			self::assertStringContainsString('node count', strtolower($e->getMessage()));
		}
	}
}
