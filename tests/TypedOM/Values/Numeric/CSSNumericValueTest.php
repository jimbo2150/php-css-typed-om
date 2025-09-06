<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use PHPUnit\Framework\TestCase;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericValue;

class CSSNumericValueTest extends TestCase
{
    public function testToMethodSameUnit()
    {
        $value = new CSSUnitValue(10, 'px');
        $converted = $value->to('px');

        $this->assertInstanceOf(CSSUnitValue::class, $converted);
        $this->assertEquals(10, $converted->value);
        $this->assertEquals('px', $converted->unit);
        $this->assertNotSame($value, $converted); // Should be a new instance
    }

    public function testToMethodPxToEm()
    {
        $value = new CSSUnitValue(16, 'px');
        $converted = $value->to('em');

        $this->assertInstanceOf(CSSUnitValue::class, $converted);
        $this->assertEqualsWithDelta(1.0, $converted->value, 0.001);
        $this->assertEquals('em', $converted->unit);
    }

    public function testToMethodEmToPx()
    {
        $value = new CSSUnitValue(2, 'em');
        $converted = $value->to('px');

        $this->assertInstanceOf(CSSUnitValue::class, $converted);
        $this->assertEqualsWithDelta(32.0, $converted->value, 0.001);
        $this->assertEquals('px', $converted->unit);
    }

    public function testToMethodPtToPx()
    {
        $value = new CSSUnitValue(12, 'pt');
        $converted = $value->to('px');

        $this->assertInstanceOf(CSSUnitValue::class, $converted);
        $this->assertEqualsWithDelta(16.0, $converted->value, 0.01); // 12pt * 1.333 ≈ 16px
        $this->assertEquals('px', $converted->unit);
    }

    public function testToMethodPxToPt()
    {
        $value = new CSSUnitValue(16, 'px');
        $converted = $value->to('pt');

        $this->assertInstanceOf(CSSUnitValue::class, $converted);
        $this->assertEqualsWithDelta(12.0, $converted->value, 0.01); // 16px / 1.333 ≈ 12pt
        $this->assertEquals('pt', $converted->unit);
    }

    public function testToMethodCmToPx()
    {
        $value = new CSSUnitValue(1, 'cm');
        $converted = $value->to('px');

        $this->assertInstanceOf(CSSUnitValue::class, $converted);
        $this->assertEqualsWithDelta(37.795, $converted->value, 0.001);
        $this->assertEquals('px', $converted->unit);
    }

    public function testToMethodPxToCm()
    {
        $value = new CSSUnitValue(37.795, 'px');
        $converted = $value->to('cm');

        $this->assertInstanceOf(CSSUnitValue::class, $converted);
        $this->assertEqualsWithDelta(1.0, $converted->value, 0.001);
        $this->assertEquals('cm', $converted->unit);
    }

    public function testToSumMethodSingleUnit()
    {
        $value = new CSSUnitValue(10, 'px');
        $sum = $value->toSum('em');

        $this->assertInstanceOf(CSSMathSum::class, $sum);
        /** @var CSSMathSum $sum */
        $this->assertEquals(1, $sum->length);
        $this->assertEquals('em', $sum->inner_values[0]->unit);
        $this->assertEqualsWithDelta(0.625, $sum->inner_values[0]->value, 0.001); // 10px / 16 = 0.625em
    }

    public function testToSumMethodMultipleUnits()
    {
        $value = new CSSUnitValue(16, 'px');
        $sum = $value->toSum('px', 'em', 'pt');

        $this->assertInstanceOf(CSSMathSum::class, $sum);
        /** @var CSSMathSum $sum */
        $this->assertEquals(3, $sum->length);

        // First value: px to px (same)
        $this->assertEquals('px', $sum->inner_values[0]->unit);
        $this->assertEquals(16, $sum->inner_values[0]->value);

        // Second value: px to em
        $this->assertEquals('em', $sum->inner_values[1]->unit);
        $this->assertEqualsWithDelta(1.0, $sum->inner_values[1]->value, 0.001);

        // Third value: px to pt
        $this->assertEquals('pt', $sum->inner_values[2]->unit);
        $this->assertEqualsWithDelta(12.0, $sum->inner_values[2]->value, 0.01);
    }

	public function testToSumMethodFromSum()
	   {
	       $sum = (new CSSUnitValue(16, 'px'))->add(CSS::vw('23'))->toSum('percent', 'percent');

	       $this->assertInstanceOf(CSSMathSum::class, $sum);
	       /** @var CSSMathSum $sum */
	       $this->assertEquals(2, $sum->length);

	       // First value: px to percent
	       $this->assertEquals('%', $sum->inner_values[0]->unit);
	       $this->assertEquals(16, $sum->inner_values[0]->value);

	       // Second value: vw to percent
	       $this->assertEquals('%', $sum->inner_values[1]->unit);
	       $this->assertEquals(23, $sum->inner_values[1]->value);
	   }

    public function testToSumMethodEmptyUnits()
    {
        $value = new CSSUnitValue(10, 'px');
        $sum = $value->toSum();

        $this->assertInstanceOf(CSSMathSum::class, $sum);
        $this->assertEquals(0, $sum->length);
    }

    public function testEqualsSameUnitValue()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(10, 'px');

        $this->assertTrue($value1->equals($value2));
    }

    public function testEqualsSameUnitDifferentValue()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');

        $this->assertFalse($value1->equals($value2));
    }

    public function testEqualsDifferentConvertibleUnits()
    {
        $value1 = new CSSUnitValue(16, 'px');
        $value2 = new CSSUnitValue(1, 'em');

        $this->assertTrue($value1->equals($value2));
    }

    public function testEqualsDifferentConvertibleUnitsNotEqual()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(1, 'em');

        $this->assertFalse($value1->equals($value2));
    }

    public function testEqualsDifferentConvertibleUnitsNotEqual3()
    {
        $value1 = new CSSUnitValue(1, 's');
        $value2 = new CSSUnitValue(100, 'ms');

        $this->assertFalse($value1->equals($value2));
    }

    public function testEqualsWithConversionError()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = $this->getMockBuilder(CSSUnitValue::class)
            ->setConstructorArgs([10, 'em'])
            ->onlyMethods(['to'])
            ->getMock();

        $value2->method('to')->will($this->throwException(new \Exception()));

        $this->assertFalse($value1->equals($value2));
    }

    public function testEqualsIncompatibleUnits()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(10, 'deg');

        $this->assertFalse($value1->equals($value2));
    }

    public function testEqualsMathSumSameValues()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $sum1 = new CSSMathSum([$value1, $value2]);

        $value3 = new CSSUnitValue(10, 'px');
        $value4 = new CSSUnitValue(5, 'px');
        $sum2 = new CSSMathSum([$value3, $value4]);

        $this->assertTrue($sum1->equals($sum2));
    }

    public function testEqualsMathSumDifferentValues()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $sum1 = new CSSMathSum([$value1, $value2]);

        $value3 = new CSSUnitValue(10, 'px');
        $value4 = new CSSUnitValue(6, 'px');
        $sum2 = new CSSMathSum([$value3, $value4]);

        $this->assertFalse($sum1->equals($sum2));
    }

    public function testEqualsDifferentTypes()
    {
        $unitValue = new CSSUnitValue(10, 'px');
        $sum = new CSSMathSum([new CSSUnitValue(10, 'px')]);

        $this->assertFalse($unitValue->equals($sum));
    }

    public function testEqualsMathSumDifferentNumberOfValues()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $sum1 = new CSSMathSum([$value1, $value2]);

        $value3 = new CSSUnitValue(10, 'px');
        $sum2 = new CSSMathSum([$value3]);

        $this->assertFalse($sum1->equals($sum2));
    }

    public function testEqualsDifferentConvertibleUnitsNotEqual2()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, 'em');

        $this->assertFalse($value1->equals($value2));
    }

    public function testAddMethod()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->add($value2);

        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(15, $result->value);
        $this->assertEquals('px', $result->unit);
    }

    public function testAddMethodStringCast()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->add($value2);

        $this->assertEquals('15px', (string)$result);
    }

    public function testEqualsMathProductSameValues()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, 'number');
        $product1 = new CSSMathProduct([$value1, $value2]);

        $value3 = new CSSUnitValue(10, 'px');
        $value4 = new CSSUnitValue(2, 'number');
        $product2 = new CSSMathProduct([$value3, $value4]);

        $this->assertTrue($product1->equals($product2));
    }

    public function testSubMethod()
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

    public function testSubMethodStringCast()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->sub($value2);

        $this->assertEquals('5px', (string)$result);
    }

    public function testSubMethodWithCSSUnitValue()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->sub($value2);

        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(5, $result->value);
        $this->assertEquals('px', $result->unit);
    }

    public function testSubMethodWithCSSUnitValueStringCast()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->sub($value2);

        $this->assertEquals('5px', (string)$result);
    }

    public function testMulMethod()
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
        $this->assertEquals('', $result->inner_values[1]->unit);
    }

    public function testMulMethodStringCast()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, 'number');
        $result = $value1->mul($value2);

        $this->assertEquals('calc(10px * 2)', (string)$result);
    }

    public function testDivMethod()
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
        $this->assertEquals('', $invert->inner_values[0]->unit);
    }

    public function testDivMethodStringCast()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, 'number');
        $result = $value1->div($value2);

        $this->assertEquals('calc(10px * calc(1 / 2))', (string)$result);
    }

    public function testMinMethod()
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

    public function testMinMethodStringCast()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $array = new CSSNumericArray([new CSSUnitValue(5, 'px'), new CSSUnitValue(15, 'px')]);
        $result = $value1->min($array);

        $this->assertEquals('min(10px, 5px, 15px)', (string)$result);
    }

    public function testMaxMethod()
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

    public function testMaxMethodStringCast()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $array = new CSSNumericArray([new CSSUnitValue(5, 'px'), new CSSUnitValue(15, 'px')]);
        $result = $value1->max($array);

        $this->assertEquals('max(10px, 5px, 15px)', (string)$result);
    }

    public function testParseSimpleNumber()
    {
        $result = CSSNumericValue::parse('123');
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(123, $result->value);
        $this->assertEquals('', $result->unit);
    }

    public function testParseWithUnit()
    {
        $result = CSSNumericValue::parse('10px');
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(10, $result->value);
        $this->assertEquals('px', $result->unit);
    }

    public function testParseNegative()
    {
        $result = CSSNumericValue::parse('-5em');
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(-5, $result->value);
        $this->assertEquals('em', $result->unit);
    }

    public function testParseDecimal()
    {
        $result = CSSNumericValue::parse('1.5rem');
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(1.5, $result->value);
        $this->assertEquals('rem', $result->unit);
    }

    public function testParsePercent()
    {
        $result = CSSNumericValue::parse('50%');
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(50, $result->value);
        $this->assertEquals('%', $result->unit);
    }

    public function testParseTrimmed()
    {
        $result = CSSNumericValue::parse('  10px  ');
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(10, $result->value);
        $this->assertEquals('px', $result->unit);
    }

	public function testParseDegree()
    {
        $result = CSSNumericValue::parse('365deg');
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(365, $result->value);
        $this->assertEquals('deg', $result->unit);
    }

	public function testParseTurn()
    {
        $result = CSSNumericValue::parse('5turn');
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(5, $result->value);
        $this->assertEquals('turn', $result->unit);
    }

    public function testParseInvalid()
    {
        $this->expectException(\InvalidArgumentException::class);
        CSSNumericValue::parse('abc');
    }

    public function testParseInvalidMultiple()
    {
        $this->expectException(\InvalidArgumentException::class);
        CSSNumericValue::parse('10px 20px');
    }

    /**
     * Test to method with custom CSSContext for em conversion.
     */
    public function testToMethodWithCustomContextEm()
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
    public function testToMethodWithCustomContextVw()
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
    public function testToMethodWithDefaultContextVh()
    {
        $value = new CSSUnitValue(27, 'px');
        $converted = $value->to('vh'); // Uses default context (vh factor 5.4px)

        $this->assertInstanceOf(CSSUnitValue::class, $converted);
        $this->assertEqualsWithDelta(5.0, $converted->value, 0.001); // 27 / 5.4 = 5vh
        $this->assertEquals('vh', $converted->unit);
    }

	public function testFrom()
	{
		$this->assertEquals(CSSNumericValue::parse('10px'), (new CSSUnitValue(10, 'px'))->from('10px'));
	}

    public function testTypeMethod()
    {
        $pxValue = new CSSUnitValue(10, 'px');
        $this->assertEquals('length', $pxValue->type());

        $degValue = new CSSUnitValue(90, 'deg');
        $this->assertEquals('angle', $degValue->type());

        $sValue = new CSSUnitValue(2, 's');
        $this->assertEquals('time', $sValue->type());

        $hzValue = new CSSUnitValue(100, 'hz');
        $this->assertEquals('frequency', $hzValue->type());

        $dpiValue = new CSSUnitValue(300, 'dpi');
        $this->assertEquals('resolution', $dpiValue->type());

        $frValue = new CSSUnitValue(1, 'fr');
        $this->assertEquals('flex', $frValue->type());

        $percentValue = new CSSUnitValue(50, 'percent');
        $this->assertEquals('percent', $percentValue->type());
    }

    public function testTypeMethodWithNoUnit()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('No unit set');
        $value = new CSSUnitValue(10, 'number');
  $reflectedProperty = new \ReflectionProperty(CSSUnitValue::class, 'unitObj');
  $reflectedProperty->setAccessible(true);
  $reflectedProperty->setValue($value, null);
        $value->type();
    }
}