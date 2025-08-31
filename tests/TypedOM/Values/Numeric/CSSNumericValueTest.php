<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSNumericArray;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax;
use PHPUnit\Framework\TestCase;

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
        $this->assertEquals(1, $sum->length);
        $this->assertEquals('em', $sum->inner_values[0]->unit);
        $this->assertEqualsWithDelta(0.625, $sum->inner_values[0]->value, 0.001); // 10px / 16 = 0.625em
    }

    public function testToSumMethodMultipleUnits()
    {
        $value = new CSSUnitValue(16, 'px');
        $sum = $value->toSum('px', 'em', 'pt');

        $this->assertInstanceOf(CSSMathSum::class, $sum);
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

    public function testAddMethod()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->add($value2);

        $this->assertInstanceOf(CSSMathSum::class, $result);
        $this->assertEquals(2, $result->length);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertEquals(5, $result->inner_values[1]->value);
        $this->assertEquals('px', $result->inner_values[1]->unit);
    }

    public function testAddMethodStringCast()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->add($value2);

        $this->assertEquals('calc(10px + 5px)', (string)$result);
    }

    public function testSubMethod()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $sum = new CSSMathSum([new CSSUnitValue(5, 'px')]);
        $result = $value1->sub($sum);

        $this->assertInstanceOf(CSSMathSum::class, $result);
        $this->assertEquals(2, $result->length);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertInstanceOf(CSSMathSum::class, $result->inner_values[1]);
        $this->assertEquals(5, $result->inner_values[1]->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[1]->inner_values[0]->unit);
    }

    public function testSubMethodStringCast()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $sum = new CSSMathSum([new CSSUnitValue(5, 'px')]);
        $result = $value1->sub($sum);

        $this->assertEquals('calc(10px + calc(5px))', (string)$result);
    }

    public function testSubMethodWithCSSUnitValue()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->sub($value2);

        $this->assertInstanceOf(CSSMathSum::class, $result);
        $this->assertEquals(2, $result->length);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertEquals(5, $result->inner_values[1]->value);
        $this->assertEquals('px', $result->inner_values[1]->unit);
    }

    public function testSubMethodWithCSSUnitValueStringCast()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->sub($value2);

        $this->assertEquals('calc(10px + 5px)', (string)$result);
    }

    public function testMulMethod()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, 'number');
        $result = $value1->mul($value2);

        $this->assertInstanceOf(CSSMathProduct::class, $result);
        $this->assertEquals(2, $result->length);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
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
        $this->assertEquals(2, $result->length);
        $this->assertEquals(10, $result->inner_values[0]->value);
        $this->assertEquals('px', $result->inner_values[0]->unit);
        $this->assertEquals(2, $result->inner_values[1]->value);
        $this->assertEquals('', $result->inner_values[1]->unit);
    }

    public function testDivMethodStringCast()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, 'number');
        $result = $value1->div($value2);

        $this->assertEquals('calc(10px * 2)', (string)$result);
    }

    public function testMinMethod()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $array = new CSSNumericArray([new CSSUnitValue(5, 'px'), new CSSUnitValue(15, 'px')]);
        $result = $value1->min($array);

        $this->assertInstanceOf(CSSMathMin::class, $result);
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
}