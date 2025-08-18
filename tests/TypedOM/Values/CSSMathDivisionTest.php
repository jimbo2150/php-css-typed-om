<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathDivision;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSMathDivision class.
 */
class CSSMathDivisionTest extends TestCase
{
    public function testConstructor()
    {
        $value1 = new CSSUnitValue(100, 'px');
        $value2 = new CSSUnitValue(2, '');
        
        $division = new CSSMathDivision($value1, $value2);
        $this->assertInstanceOf(CSSMathDivision::class, $division);
    }

    public function testGetValues()
    {
        $value1 = new CSSUnitValue(100, 'px');
        $value2 = new CSSUnitValue(2, '');
        
        $division = new CSSMathDivision($value1, $value2);
        $values = $division->getValues();
        
        $this->assertCount(2, $values);
        $this->assertSame($value1, $values[0]);
        $this->assertSame($value2, $values[1]);
    }

    public function testToString()
    {
        $value1 = new CSSUnitValue(100, 'px');
        $value2 = new CSSUnitValue(2, '');
        
        $division = new CSSMathDivision($value1, $value2);
        $this->assertSame('calc(100px / 2)', $division->toString());
    }

    public function testToStringWithMultipleValues()
    {
        $value1 = new CSSUnitValue(100, 'px');
        $value2 = new CSSUnitValue(2, '');
        $value3 = new CSSUnitValue(5, '');
        
        $division = new CSSMathDivision($value1, $value2, $value3);
        $this->assertSame('calc(100px / 2 / 5)', $division->toString());
    }

    public function testIsValid()
    {
        $value1 = new CSSUnitValue(100, 'px');
        $value2 = new CSSUnitValue(2, '');
        
        $division = new CSSMathDivision($value1, $value2);
        $this->assertTrue($division->isValid());
    }

    public function testClone()
    {
        $value1 = new CSSUnitValue(100, 'px');
        $value2 = new CSSUnitValue(2, '');
        
        $division = new CSSMathDivision($value1, $value2);
        $cloned = $division->clone();
        
        $this->assertInstanceOf(CSSMathDivision::class, $cloned);
        $this->assertNotSame($division, $cloned);
    }

    public function testToUnit()
    {
        $value1 = new CSSUnitValue(100, 'px');
        $value2 = new CSSUnitValue(2, '');
        
        $division = new CSSMathDivision($value1, $value2);
        $result = $division->to('px');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(50.0, $result->value);
        $this->assertSame('px', $result->unit);
    }

    public function testToUnitWithIncompatibleUnits()
    {
        $value1 = new CSSUnitValue(100, 'px');
        $value2 = new CSSUnitValue(2, 'em');
        
        $division = new CSSMathDivision($value1, $value2);
        $result = $division->to('px');
        
        $this->assertNull($result);
    }

    public function testSingleValue()
    {
        $value = new CSSUnitValue(100, 'px');
        
        $division = new CSSMathDivision($value);
        $this->assertSame('calc(100px)', $division->toString());
    }

    public function testEmptyDivision()
    {
        $division = new CSSMathDivision();
        $this->assertSame('calc()', $division->toString());
    }

    public function testDivisionByZero()
    {
        $value1 = new CSSUnitValue(100, 'px');
        $value2 = new CSSUnitValue(0, '');
        
        $division = new CSSMathDivision($value1, $value2);
        $result = $division->to('px');
        
        $this->assertNull($result);
    }
}
