<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathDifference;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSMathDifference class.
 */
class CSSMathDifferenceTest extends TestCase
{
    public function testConstructor()
    {
        $value1 = new CSSUnitValue(30, 'px');
        $value2 = new CSSUnitValue(10, 'px');
        
        $difference = new CSSMathDifference($value1, $value2);
        $this->assertInstanceOf(CSSMathDifference::class, $difference);
    }

    public function testGetValues()
    {
        $value1 = new CSSUnitValue(30, 'px');
        $value2 = new CSSUnitValue(10, 'px');
        
        $difference = new CSSMathDifference($value1, $value2);
        $values = $difference->getValues();
        
        $this->assertCount(2, $values);
        $this->assertSame($value1, $values[0]);
        $this->assertSame($value2, $values[1]);
    }

    public function testToString()
    {
        $value1 = new CSSUnitValue(30, 'px');
        $value2 = new CSSUnitValue(10, 'px');
        
        $difference = new CSSMathDifference($value1, $value2);
        $this->assertSame('calc(30px - 10px)', $difference->toString());
    }

    public function testToStringWithMultipleValues()
    {
        $value1 = new CSSUnitValue(100, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        $value3 = new CSSUnitValue(10, 'px');
        
        $difference = new CSSMathDifference($value1, $value2, $value3);
        $this->assertSame('calc(100px - 20px - 10px)', $difference->toString());
    }

    public function testIsValid()
    {
        $value1 = new CSSUnitValue(30, 'px');
        $value2 = new CSSUnitValue(10, 'px');
        
        $difference = new CSSMathDifference($value1, $value2);
        $this->assertTrue($difference->isValid());
    }

    public function testClone()
    {
        $value1 = new CSSUnitValue(30, 'px');
        $value2 = new CSSUnitValue(10, 'px');
        
        $difference = new CSSMathDifference($value1, $value2);
        $cloned = $difference->clone();
        
        $this->assertInstanceOf(CSSMathDifference::class, $cloned);
        $this->assertNotSame($difference, $cloned);
    }

    public function testToUnit()
    {
        $value1 = new CSSUnitValue(30, 'px');
        $value2 = new CSSUnitValue(10, 'px');
        
        $difference = new CSSMathDifference($value1, $value2);
        $result = $difference->to('px');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(20.0, $result->value);
        $this->assertSame('px', $result->unit);
    }

    public function testToUnitWithIncompatibleUnits()
    {
        $value1 = new CSSUnitValue(30, 'px');
        $value2 = new CSSUnitValue(10, 'em');
        
        $difference = new CSSMathDifference($value1, $value2);
        $result = $difference->to('px');
        
        $this->assertNull($result);
    }

    public function testSingleValue()
    {
        $value = new CSSUnitValue(10, 'px');
        
        $difference = new CSSMathDifference($value);
        $this->assertSame('calc(10px)', $difference->toString());
    }

    public function testEmptyDifference()
    {
        $difference = new CSSMathDifference();
        $this->assertSame('calc()', $difference->toString());
    }
}
