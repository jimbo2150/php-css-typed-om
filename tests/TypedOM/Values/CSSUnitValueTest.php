<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSUnitValue class.
 */
class CSSUnitValueTest extends TestCase
{
    public function testConstructor()
    {
        $value = new CSSUnitValue(10.5, 'px');
        $this->assertInstanceOf(CSSUnitValue::class, $value);
        $this->assertSame(10.5, $value->value);
        $this->assertSame('px', $value->unit);
    }

    public function testGetNumericValue()
    {
        $value = new CSSUnitValue(42, 'em');
        $this->assertSame(42.0, $value->getNumericValue());
    }

    public function testSetNumericValue()
    {
        $value = new CSSUnitValue(10, 'px');
        $value->setNumericValue(20);
        $this->assertSame(20.0, $value->getNumericValue());
    }

    public function testGetUnit()
    {
        $value = new CSSUnitValue(15, 'rem');
        $this->assertSame('rem', $value->getUnit());
    }

    public function testSetUnit()
    {
        $value = new CSSUnitValue(10, 'px');
        $value->setUnit('em');
        $this->assertSame('em', $value->getUnit());
    }

    public function testToString()
    {
        $value = new CSSUnitValue(12.5, 'px');
        $this->assertSame('12.5px', $value->toString());
    }

    public function testToStringWithZero()
    {
        $value = new CSSUnitValue(0, 'px');
        $this->assertSame('0px', $value->toString());
    }

    public function testIsValid()
    {
        $value = new CSSUnitValue(10, 'px');
        $this->assertTrue($value->isValid());
    }

    public function testClone()
    {
        $value = new CSSUnitValue(5, 'em');
        $cloned = $value->clone();
        
        $this->assertInstanceOf(CSSUnitValue::class, $cloned);
        $this->assertNotSame($value, $cloned);
        $this->assertSame($value->value, $cloned->value);
        $this->assertSame($value->unit, $cloned->unit);
    }

    public function testTo()
    {
        $value = new CSSUnitValue(16, 'px');
        $result = $value->to('pt');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(12.0, $result->value); // 16px = 12pt
    }

    public function testToInvalidUnit()
    {
        $value = new CSSUnitValue(10, 'px');
        $result = $value->to('invalid');
        $this->assertNull($result);
    }

    public function testAdd()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->add($value2);
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(15.0, $result->value);
        $this->assertSame('px', $result->unit);
    }

    public function testAddIncompatibleUnits()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'em');
        $result = $value1->add($value2);
        
        $this->assertNull($result);
    }

    public function testSubtract()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(3, 'px');
        $result = $value1->subtract($value2);
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(7.0, $result->value);
        $this->assertSame('px', $result->unit);
    }

    public function testMultiply()
    {
        $value = new CSSUnitValue(5, 'px');
        $result = $value->multiply(3);
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(15.0, $result->value);
        $this->assertSame('px', $result->unit);
    }

    public function testDivide()
    {
        $value = new CSSUnitValue(15, 'px');
        $result = $value->divide(3);
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(5.0, $result->value);
        $this->assertSame('px', $result->unit);
    }

    public function testPropertyAccess()
    {
        $value = new CSSUnitValue(10, 'px');
        
        $this->assertSame(10.0, $value->value);
        $this->assertSame('px', $value->unit);
    }

    public function testPropertySet()
    {
        $value = new CSSUnitValue(10, 'px');
        
        $value->value = 20;
        $value->unit = 'em';
        
        $this->assertSame(20.0, $value->value);
        $this->assertSame('em', $value->unit);
    }

    public function testInvalidPropertyAccess()
    {
        $this->expectException(\InvalidArgumentException::class);
        $value = new CSSUnitValue(10, 'px');
        $invalid = $value->invalid;
    }

    public function testInvalidPropertySet()
    {
        $this->expectException(\InvalidArgumentException::class);
        $value = new CSSUnitValue(10, 'px');
        $value->invalid = 'test';
    }
}