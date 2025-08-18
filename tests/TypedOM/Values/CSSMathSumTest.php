<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathSum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSMathSum class.
 */
class CSSMathSumTest extends TestCase
{
    public function testConstructor()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        
        $sum = new CSSMathSum($value1, $value2);
        $this->assertInstanceOf(CSSMathSum::class, $sum);
    }

    public function testGetValues()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        
        $sum = new CSSMathSum($value1, $value2);
        $values = $sum->getValues();
        
        $this->assertCount(2, $values);
        $this->assertSame($value1, $values[0]);
        $this->assertSame($value2, $values[1]);
    }

    public function testToString()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        
        $sum = new CSSMathSum($value1, $value2);
        $this->assertSame('calc(10px + 20px)', $sum->toString());
    }

    public function testToStringWithMultipleValues()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        $value3 = new CSSUnitValue(30, 'px');
        
        $sum = new CSSMathSum($value1, $value2, $value3);
        $this->assertSame('calc(10px + 20px + 30px)', $sum->toString());
    }

    public function testIsValid()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        
        $sum = new CSSMathSum($value1, $value2);
        $this->assertTrue($sum->isValid());
    }

    public function testClone()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        
        $sum = new CSSMathSum($value1, $value2);
        $cloned = $sum->clone();
        
        $this->assertInstanceOf(CSSMathSum::class, $cloned);
        $this->assertNotSame($sum, $cloned);
    }

    public function testToUnit()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        
        $sum = new CSSMathSum($value1, $value2);
        $result = $sum->to('px');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(30.0, $result->value);
        $this->assertSame('px', $result->unit);
    }

    public function testToUnitWithIncompatibleUnits()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'em');
        
        $sum = new CSSMathSum($value1, $value2);
        $result = $sum->to('px');
        
        $this->assertNull($result);
    }

    public function testSingleValue()
    {
        $value = new CSSUnitValue(10, 'px');
        
        $sum = new CSSMathSum($value);
        $this->assertSame('calc(10px)', $sum->toString());
    }

    public function testEmptySum()
    {
        $sum = new CSSMathSum();
        $this->assertSame('calc()', $sum->toString());
    }
}
