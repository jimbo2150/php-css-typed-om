<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSMathInvert class.
 */
class CSSMathInvertTest extends TestCase
{
    public function testConstructor()
    {
        $value = new CSSUnitValue(2, '');
        
        $invert = new CSSMathInvert($value);
        $this->assertInstanceOf(CSSMathInvert::class, $invert);
    }

    public function testGetValue()
    {
        $value = new CSSUnitValue(2, '');
        
        $invert = new CSSMathInvert($value);
        $this->assertSame($value, $invert->getValue());
    }

    public function testToString()
    {
        $value = new CSSUnitValue(2, '');
        
        $invert = new CSSMathInvert($value);
        $this->assertSame('calc(1 / 2)', $invert->toString());
    }

    public function testIsValid()
    {
        $value = new CSSUnitValue(2, '');
        
        $invert = new CSSMathInvert($value);
        $this->assertTrue($invert->isValid());
    }

    public function testClone()
    {
        $value = new CSSUnitValue(2, '');
        
        $invert = new CSSMathInvert($value);
        $cloned = $invert->clone();
        
        $this->assertInstanceOf(CSSMathInvert::class, $cloned);
        $this->assertNotSame($invert, $cloned);
    }

    public function testToUnit()
    {
        $value = new CSSUnitValue(2, '');
        
        $invert = new CSSMathInvert($value);
        $result = $invert->to('');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(0.5, $result->value);
        $this->assertSame('', $result->unit);
    }

    public function testToUnitWithIncompatibleUnits()
    {
        $value = new CSSUnitValue(2, 'px');
        
        $invert = new CSSMathInvert($value);
        $result = $invert->to('em');
        
        $this->assertNull($result);
    }

    public function testFractionalValue()
    {
        $value = new CSSUnitValue(0.5, '');
        
        $invert = new CSSMathInvert($value);
        $result = $invert->to('');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(2.0, $result->value);
    }

    public function testZeroValue()
    {
        $value = new CSSUnitValue(0, '');
        
        $invert = new CSSMathInvert($value);
        $result = $invert->to('');
        
        $this->assertNull($result);
    }

    public function testNegativeValue()
    {
        $value = new CSSUnitValue(-2, '');
        
        $invert = new CSSMathInvert($value);
        $result = $invert->to('');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(-0.5, $result->value);
    }
}
