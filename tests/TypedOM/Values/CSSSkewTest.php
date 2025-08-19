<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkew;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSSkew class.
 */
class CSSSkewTest extends TestCase
{
    public function testConstructor()
    {
        $ax = new CSSUnitValue(30, 'deg');
        $ay = new CSSUnitValue(45, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertInstanceOf(CSSSkew::class, $skew);
    }

    public function testGetAx()
    {
        $ax = new CSSUnitValue(30, 'deg');
        $ay = new CSSUnitValue(45, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame($ax, $skew->getAx());
    }

    public function testGetAy()
    {
        $ax = new CSSUnitValue(30, 'deg');
        $ay = new CSSUnitValue(45, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame($ay, $skew->getAy());
    }

    public function testToString()
    {
        $ax = new CSSUnitValue(30, 'deg');
        $ay = new CSSUnitValue(45, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(30deg, 45deg)', $skew->toString());
    }

    public function testToStringSameValues()
    {
        $ax = new CSSUnitValue(30, 'deg');
        $ay = new CSSUnitValue(30, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(30deg)', $skew->toString());
    }

    public function testToStringZeroValues()
    {
        $ax = new CSSUnitValue(0, 'deg');
        $ay = new CSSUnitValue(0, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(0deg)', $skew->toString());
    }

    public function testToStringNegativeValues()
    {
        $ax = new CSSUnitValue(-30, 'deg');
        $ay = new CSSUnitValue(-45, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(-30deg, -45deg)', $skew->toString());
    }

    public function testToStringDecimalValues()
    {
        $ax = new CSSUnitValue(30.5, 'deg');
        $ay = new CSSUnitValue(45.25, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(30.5deg, 45.25deg)', $skew->toString());
    }

    public function testToStringLargeValues()
    {
        $ax = new CSSUnitValue(720, 'deg');
        $ay = new CSSUnitValue(1080, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(720deg, 1080deg)', $skew->toString());
    }

    public function testToStringVerySmallValues()
    {
        $ax = new CSSUnitValue(0.001, 'deg');
        $ay = new CSSUnitValue(0.0001, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(0.001deg, 0.0001deg)', $skew->toString());
    }

    public function testDifferentAngleUnits()
    {
        $ax = new CSSUnitValue(0.523, 'rad');
        $ay = new CSSUnitValue(0.785, 'rad');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(0.523rad, 0.785rad)', $skew->toString());
    }

    public function testTurnUnit()
    {
        $ax = new CSSUnitValue(0.083, 'turn');
        $ay = new CSSUnitValue(0.125, 'turn');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(0.083turn, 0.125turn)', $skew->toString());
    }

    public function testGradUnit()
    {
        $ax = new CSSUnitValue(33.333, 'grad');
        $ay = new CSSUnitValue(50, 'grad');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(33.333grad, 50grad)', $skew->toString());
    }

    public function testIsValid()
    {
        $ax = new CSSUnitValue(30, 'deg');
        $ay = new CSSUnitValue(45, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertTrue($skew->isValid());
    }

    public function testClone()
    {
        $ax = new CSSUnitValue(30, 'deg');
        $ay = new CSSUnitValue(45, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $cloned = $skew->clone();
        
        $this->assertInstanceOf(CSSSkew::class, $cloned);
        $this->assertNotSame($skew, $cloned);
    }

    public function testToUnit()
    {
        $ax = new CSSUnitValue(30, 'deg');
        $ay = new CSSUnitValue(45, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $result = $skew->to('rad');
        
        $this->assertNull($result);
    }

    public function testSingleValueSkew()
    {
        $ax = new CSSUnitValue(30, 'deg');
        $ay = new CSSUnitValue(0, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(30deg)', $skew->toString());
    }

    public function testMixedUnits()
    {
        $ax = new CSSUnitValue(30, 'deg');
        $ay = new CSSUnitValue(0.523, 'rad');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(30deg, 0.523rad)', $skew->toString());
    }

    public function testVeryLargeSingleValue()
    {
        $ax = new CSSUnitValue(3600, 'deg');
        $ay = new CSSUnitValue(0, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(3600deg)', $skew->toString());
    }

    public function testVerySmallSingleValue()
    {
        $ax = new CSSUnitValue(0.00001, 'deg');
        $ay = new CSSUnitValue(0, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(0.00001deg)', $skew->toString());
    }

    public function testNegativeSingleValue()
    {
        $ax = new CSSUnitValue(-30, 'deg');
        $ay = new CSSUnitValue(0, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(-30deg)', $skew->toString());
    }

    public function testDecimalSingleValue()
    {
        $ax = new CSSUnitValue(30.123, 'deg');
        $ay = new CSSUnitValue(0, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(30.123deg)', $skew->toString());
    }

    public function testBothValuesSameSign()
    {
        $ax = new CSSUnitValue(-30, 'deg');
        $ay = new CSSUnitValue(-45, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(-30deg, -45deg)', $skew->toString());
    }

    public function testBothValuesDifferentSigns()
    {
        $ax = new CSSUnitValue(30, 'deg');
        $ay = new CSSUnitValue(-45, 'deg');
        
        $skew = new CSSSkew($ax, $ay);
        $this->assertSame('skew(30deg, -45deg)', $skew->toString());
    }
}
