<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSMathMax class.
 */
class CSSMathMaxTest extends TestCase
{
    public function testConstructor()
    {
        $values = [
            new CSSUnitValue(1, ''),
            new CSSUnitValue(2, ''),
            new CSSUnitValue(3, '')
        ];
        
        $max = new CSSMathMax($values);
        $this->assertInstanceOf(CSSMathMax::class, $max);
    }

    public function testGetValues()
    {
        $values = [
            new CSSUnitValue(1, ''),
            new CSSUnitValue(2, ''),
            new CSSUnitValue(3, '')
        ];
        
        $max = new CSSMathMax($values);
        $this->assertSame($values, $max->getValues());
    }

    public function testToString()
    {
        $values = [
            new CSSUnitValue(1, ''),
            new CSSUnitValue(2, ''),
            new CSSUnitValue(3, '')
        ];
        
        $max = new CSSMathMax($values);
        $this->assertSame('max(1, 2, 3)', $max->toString());
    }

    public function testIsValid()
    {
        $values = [
            new CSSUnitValue(1, ''),
            new CSSUnitValue(2, ''),
            new CSSUnitValue(3, '')
        ];
        
        $max = new CSSMathMax($values);
        $this->assertTrue($max->isValid());
    }

    public function testClone()
    {
        $values = [
            new CSSUnitValue(1, ''),
            new CSSUnitValue(2, ''),
            new CSSUnitValue(3, '')
        ];
        
        $max = new CSSMathMax($values);
        $cloned = $max->clone();
        
        $this->assertInstanceOf(CSSMathMax::class, $cloned);
        $this->assertNotSame($max, $cloned);
    }

    public function testToUnit()
    {
        $values = [
            new CSSUnitValue(1, ''),
            new CSSUnitValue(2, ''),
            new CSSUnitValue(3, '')
        ];
        
        $max = new CSSMathMax($values);
        $result = $max->to('');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(3.0, $result->value);
    }

    public function testToUnitWithIncompatibleUnits()
    {
        $values = [
            new CSSUnitValue(1, 'px'),
            new CSSUnitValue(2, 'em')
        ];
        
        $max = new CSSMathMax($values);
        $result = $max->to('px');
        
        $this->assertNull($result);
    }

    public function testSingleValue()
    {
        $values = [new CSSUnitValue(5, '')];
        
        $max = new CSSMathMax($values);
        $result = $max->to('');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(5.0, $result->value);
    }

    public function testNegativeValues()
    {
        $values = [
            new CSSUnitValue(-1, ''),
            new CSSUnitValue(-5, ''),
            new CSSUnitValue(-3, '')
        ];
        
        $max = new CSSMathMax($values);
        $result = $max->to('');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(-1.0, $result->value);
    }

    public function testMixedPositiveNegative()
    {
        $values = [
            new CSSUnitValue(-1, ''),
            new CSSUnitValue(5, ''),
            new CSSUnitValue(-3, '')
        ];
        
        $max = new CSSMathMax($values);
        $result = $max->to('');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(5.0, $result->value);
    }

    public function testDecimalValues()
    {
        $values = [
            new CSSUnitValue(1.5, ''),
            new CSSUnitValue(2.7, ''),
            new CSSUnitValue(1.9, '')
        ];
        
        $max = new CSSMathMax($values);
        $result = $max->to('');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(2.7, $result->value);
    }

    public function testEmptyValues()
    {
        $values = [];
        
        $max = new CSSMathMax($values);
        $result = $max->to('');
        
        $this->assertNull($result);
    }
}
