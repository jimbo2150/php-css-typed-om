<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
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
	       $this->assertEquals('percent', $sum->inner_values[0]->unit);
	       $this->assertEquals(16, $sum->inner_values[0]->value);

	       // Second value: vw to percent
	       $this->assertEquals('percent', $sum->inner_values[1]->unit);
	       $this->assertEquals(23, $sum->inner_values[1]->value);
	   }

    public function testToSumMethodEmptyUnits()
    {
        $value = new CSSUnitValue(10, 'px');
        $sum = $value->toSum();

        $this->assertInstanceOf(CSSMathSum::class, $sum);
        $this->assertEquals(0, $sum->length);
    }
}