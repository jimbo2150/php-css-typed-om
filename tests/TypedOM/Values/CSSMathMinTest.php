<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSMathMin class.
 */
class CSSMathMinTest extends TestCase
{
    public function testConstructor()
    {
        $values = [
            new CSSUnitValue(1, ''),
            new CSSUnitValue(2, ''),
            new CSSUnitValue(3, '')
        ];
        
        $min = new CSSMathMin($values);
        $this->assertInstanceOf(CSSMathMin::class, $min);
    }

    public function testGetValues()
    {
        $values = [
            new CSSUnitValue(1, ''),
            new CSSUnitValue(2, ''),
            new CSSUnitValue(3, '')
        ];
        
        $min = new CSSMathMin($values);
        $this->assertSame($values, $min->getValues());
    }

    public function testToString()
    {
        $values = [
            new CSSUnitValue(1, ''),
            new CSSUnitValue(2, ''),
            new CSSUnitValue(3, '')
        ];
        
        $min = new CSSMathMin($values);
        $this->assertSame('min(1, 2, 3)', $min->toString());
    }

    public function testIsValid()
    {
        $values = [
            new CSSUnitValue(1, ''),
            new CSSUnitValue(2, ''),
            new CSSUnitValue(3, '')
        ];
        
        $min = new CSSMathMin($values);
        $this->assertTrue($min->isValid());
    }

    public function testClone()
    {
        $values = [
            new CSSUnitValue(1, ''),
            new CSSUnitValue(2, ''),
            new CSSUnitValue(3, '')
        ];
        
        $min = new CSSMathMin($values);
        $cloned = $min->clone();
        
        $this->assertInstanceOf(CSSMathMin::class, $cloned);
        $this->assertNotSame($min, $cloned);
    }

    public function testToUnit()
    {
        $values = [
            new CSSUnitValue(1, ''),
            new CSSUnitValue(2, ''),
            new CSSUnitValue(3, '')
        ];
        
        $min = new CSSMathMin($values);
        $result = $min->to('');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(1.0, $result->value);
    }

    public function testToUnitWithIncompatibleUnits()
    {
        $values = [
            new CSSUnitValue(1, 'px'),
            new CSSUnitValue(2, 'em')
        ];
        
        $min = new CSSMathMin($values);
        $result = $min->to('px');
        
        $this->assertNull($result);
    }

    public function testSingleValue()
    {
        $values = [new CSSUnitValue(5, '')];
        
        $min = new CSSMathMin($values);
        $result = $min->to('');
        
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
        
        $min = new CSSMathMin($values);
        $result = $min->to('');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(-5.0, $result->value);
    }

    public function testMixedPositiveNegative()
    {
        $values = [
            new CSSUnitValue(-1, ''),
            new CSSUnitValue(5, ''),
            new CSSUnitValue(-3, '')
        ];
        
        $min = new CSSMathMin($values);
        $result = $min->to('');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(-3.0, $result->value);
    }

    public function testDecimalValues()
    {
        $values = [
            new CSSUnitValue(1.5, ''),
            new CSSUnitValue(2.7, ''),
            new CSSUnitValue(1.9, '')
        ];
        
        $min = new CSSMathMin($values);
        $result = $min->to('');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(1.5, $result->value);
    }

    public function testEmptyValues()
    {
        $values = [];
        
        $min = new CSSMathMin($values);
        $result = $min->to('');
        
        $this->assertNull($result);
    }
}
