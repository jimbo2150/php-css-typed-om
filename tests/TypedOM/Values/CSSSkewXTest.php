<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewX;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSSkewX class.
 */
class CSSSkewXTest extends TestCase
{
    public function testConstructor()
    {
        $ax = new CSSUnitValue(30, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertInstanceOf(CSSSkewX::class, $skewX);
    }

    public function testGetAx()
    {
        $ax = new CSSUnitValue(30, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame($ax, $skewX->getAx());
    }

    public function testToString()
    {
        $ax = new CSSUnitValue(30, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(30deg)', $skewX->toString());
    }

    public function testToStringZeroValue()
    {
        $ax = new CSSUnitValue(0, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(0deg)', $skewX->toString());
    }

    public function testToStringNegativeValue()
    {
        $ax = new CSSUnitValue(-30, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(-30deg)', $skewX->toString());
    }

    public function testToStringDecimalValue()
    {
        $ax = new CSSUnitValue(30.5, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(30.5deg)', $skewX->toString());
    }

    public function testToStringLargeValue()
    {
        $ax = new CSSUnitValue(720, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(720deg)', $skewX->toString());
    }

    public function testToStringVerySmallValue()
    {
        $ax = new CSSUnitValue(0.001, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(0.001deg)', $skewX->toString());
    }

    public function testDifferentAngleUnits()
    {
        $ax = new CSSUnitValue(0.523, 'rad');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(0.523rad)', $skewX->toString());
    }

    public function testTurnUnit()
    {
        $ax = new CSSUnitValue(0.083, 'turn');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(0.083turn)', $skewX->toString());
    }

    public function testGradUnit()
    {
        $ax = new CSSUnitValue(33.333, 'grad');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(33.333grad)', $skewX->toString());
    }

    public function testIsValid()
    {
        $ax = new CSSUnitValue(30, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertTrue($skewX->isValid());
    }

    public function testClone()
    {
        $ax = new CSSUnitValue(30, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $cloned = $skewX->clone();
        
        $this->assertInstanceOf(CSSSkewX::class, $cloned);
        $this->assertNotSame($skewX, $cloned);
    }

    public function testToUnit()
    {
        $ax = new CSSUnitValue(30, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $result = $skewX->to('rad');
        
        $this->assertNull($result);
    }

    public function testVeryLargeAngle()
    {
        $ax = new CSSUnitValue(3600, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(3600deg)', $skewX->toString());
    }

    public function testVerySmallAngle()
    {
        $ax = new CSSUnitValue(0.00001, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(0.00001deg)', $skewX->toString());
    }

    public function testNegativeLargeAngle()
    {
        $ax = new CSSUnitValue(-720, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(-720deg)', $skewX->toString());
    }

    public function testDecimalPrecision()
    {
        $ax = new CSSUnitValue(30.123456, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(30.123456deg)', $skewX->toString());
    }

    public function testMixedUnitCompatibility()
    {
        $ax = new CSSUnitValue(30, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(30deg)', $skewX->toString());
    }

    public function testBoundaryValues()
    {
        $ax = new CSSUnitValue(90, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(90deg)', $skewX->toString());
    }

    public function testBeyondBoundaryValues()
    {
        $ax = new CSSUnitValue(180, 'deg');
        
        $skewX = new CSSSkewX($ax);
        $this->assertSame('skewX(180deg)', $skewX->toString());
    }
}
