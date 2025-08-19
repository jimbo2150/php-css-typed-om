<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewY;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSSkewY class.
 */
class CSSSkewYTest extends TestCase
{
    public function testConstructor()
    {
        $ay = new CSSUnitValue(30, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertInstanceOf(CSSSkewY::class, $skewY);
    }

    public function testGetAy()
    {
        $ay = new CSSUnitValue(30, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame($ay, $skewY->getAy());
    }

    public function testToString()
    {
        $ay = new CSSUnitValue(30, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(30deg)', $skewY->toString());
    }

    public function testToStringZeroValue()
    {
        $ay = new CSSUnitValue(0, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(0deg)', $skewY->toString());
    }

    public function testToStringNegativeValue()
    {
        $ay = new CSSUnitValue(-30, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(-30deg)', $skewY->toString());
    }

    public function testToStringDecimalValue()
    {
        $ay = new CSSUnitValue(30.5, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(30.5deg)', $skewY->toString());
    }

    public function testToStringLargeValue()
    {
        $ay = new CSSUnitValue(720, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(720deg)', $skewY->toString());
    }

    public function testToStringVerySmallValue()
    {
        $ay = new CSSUnitValue(0.001, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(0.001deg)', $skewY->toString());
    }

    public function testDifferentAngleUnits()
    {
        $ay = new CSSUnitValue(0.523, 'rad');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(0.523rad)', $skewY->toString());
    }

    public function testTurnUnit()
    {
        $ay = new CSSUnitValue(0.083, 'turn');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(0.083turn)', $skewY->toString());
    }

    public function testGradUnit()
    {
        $ay = new CSSUnitValue(33.333, 'grad');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(33.333grad)', $skewY->toString());
    }

    public function testIsValid()
    {
        $ay = new CSSUnitValue(30, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertTrue($skewY->isValid());
    }

    public function testClone()
    {
        $ay = new CSSUnitValue(30, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $cloned = $skewY->clone();
        
        $this->assertInstanceOf(CSSSkewY::class, $cloned);
        $this->assertNotSame($skewY, $cloned);
    }

    public function testToUnit()
    {
        $ay = new CSSUnitValue(30, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $result = $skewY->to('rad');
        
        $this->assertNull($result);
    }

    public function testVeryLargeAngle()
    {
        $ay = new CSSUnitValue(3600, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(3600deg)', $skewY->toString());
    }

    public function testVerySmallAngle()
    {
        $ay = new CSSUnitValue(0.00001, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(0.00001deg)', $skewY->toString());
    }

    public function testNegativeLargeAngle()
    {
        $ay = new CSSUnitValue(-720, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(-720deg)', $skewY->toString());
    }

    public function testDecimalPrecision()
    {
        $ay = new CSSUnitValue(30.123456, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(30.123456deg)', $skewY->toString());
    }

    public function testBoundaryValues()
    {
        $ay = new CSSUnitValue(90, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(90deg)', $skewY->toString());
    }

    public function testBeyondBoundaryValues()
    {
        $ay = new CSSUnitValue(180, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(180deg)', $skewY->toString());
    }

    public function testMultipleOf360()
    {
        $ay = new CSSUnitValue(360, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(360deg)', $skewY->toString());
    }

    public function testNegativeMultipleOf360()
    {
        $ay = new CSSUnitValue(-360, 'deg');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(-360deg)', $skewY->toString());
    }

    public function testFractionalTurn()
    {
        $ay = new CSSUnitValue(0.25, 'turn');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(0.25turn)', $skewY->toString());
    }

    public function testExactRadians()
    {
        $ay = new CSSUnitValue(pi(), 'rad');
        
        $skewY = new CSSSkewY($ay);
        $this->assertSame('skewY(3.1415926535898rad)', $skewY->toString());
    }
}
