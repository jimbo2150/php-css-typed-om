<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSScale;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSScale class.
 */
class CSSScaleTest extends TestCase
{
    public function testConstructor()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertInstanceOf(CSSScale::class, $scale);
    }

    public function testConstructorWithZ()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        $sz = new CSSUnitValue(4, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertInstanceOf(CSSScale::class, $scale);
    }

    public function testGetSx()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame($sx, $scale->getSx());
    }

    public function testGetSy()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame($sy, $scale->getSy());
    }

    public function testGetSz()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        $sz = new CSSUnitValue(4, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame($sz, $scale->getSz());
    }

    public function testGetSzNull()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertNull($scale->getSz());
    }

    public function testToString()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(2, 3)', $scale->toString());
    }

    public function testToStringWithZ()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        $sz = new CSSUnitValue(4, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame('scale3d(2, 3, 4)', $scale->toString());
    }

    public function testToStringZeroValues()
    {
        $sx = new CSSUnitValue(0, '');
        $sy = new CSSUnitValue(0, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(0, 0)', $scale->toString());
    }

    public function testToStringNegativeValues()
    {
        $sx = new CSSUnitValue(-2, '');
        $sy = new CSSUnitValue(-3, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(-2, -3)', $scale->toString());
    }

    public function testToStringDecimalValues()
    {
        $sx = new CSSUnitValue(2.5, '');
        $sy = new CSSUnitValue(3.7, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(2.5, 3.7)', $scale->toString());
    }

    public function testToStringLargeValues()
    {
        $sx = new CSSUnitValue(1000, '');
        $sy = new CSSUnitValue(2000, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(1000, 2000)', $scale->toString());
    }

    public function testToStringVerySmallValues()
    {
        $sx = new CSSUnitValue(0.001, '');
        $sy = new CSSUnitValue(0.002, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(0.001, 0.002)', $scale->toString());
    }

    public function testToStringFractionalValues()
    {
        $sx = new CSSUnitValue(0.5, '');
        $sy = new CSSUnitValue(1.5, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(0.5, 1.5)', $scale->toString());
    }

    public function testToStringEqualValues()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(2, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(2, 2)', $scale->toString());
    }

    public function testToStringOneValue()
    {
        $sx = new CSSUnitValue(1, '');
        $sy = new CSSUnitValue(1, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(1, 1)', $scale->toString());
    }

    public function testToStringWithZZeroValues()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        $sz = new CSSUnitValue(0, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame('scale3d(2, 3, 0)', $scale->toString());
    }

    public function testToStringWithZNegativeValues()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        $sz = new CSSUnitValue(-4, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame('scale3d(2, 3, -4)', $scale->toString());
    }

    public function testToStringWithZDecimalValues()
    {
        $sx = new CSSUnitValue(2.5, '');
        $sy = new CSSUnitValue(3.7, '');
        $sz = new CSSUnitValue(4.9, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame('scale3d(2.5, 3.7, 4.9)', $scale->toString());
    }

    public function testToStringWithZLargeValues()
    {
        $sx = new CSSUnitValue(1000, '');
        $sy = new CSSUnitValue(2000, '');
        $sz = new CSSUnitValue(3000, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame('scale3d(1000, 2000, 3000)', $scale->toString());
    }

    public function testToStringWithZVerySmallValues()
    {
        $sx = new CSSUnitValue(0.001, '');
        $sy = new CSSUnitValue(0.002, '');
        $sz = new CSSUnitValue(0.003, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame('scale3d(0.001, 0.002, 0.003)', $scale->toString());
    }

    public function testToStringWithZEqualValues()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(2, '');
        $sz = new CSSUnitValue(2, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame('scale3d(2, 2, 2)', $scale->toString());
    }

    public function testToStringWithZOneValue()
    {
        $sx = new CSSUnitValue(1, '');
        $sy = new CSSUnitValue(1, '');
        $sz = new CSSUnitValue(1, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame('scale3d(1, 1, 1)', $scale->toString());
    }

    public function testIsValid()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertTrue($scale->isValid());
    }

    public function testClone()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        
        $scale = new CSSScale($sx, $sy);
        $cloned = $scale->clone();
        
        $this->assertInstanceOf(CSSScale::class, $cloned);
        $this->assertNotSame($scale, $cloned);
    }

    public function testToUnit()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(3, '');
        
        $scale = new CSSScale($sx, $sy);
        $result = $scale->to('px');
        
        $this->assertNull($result);
    }

    public function testScaleWithVeryLargeValues()
    {
        $sx = new CSSUnitValue(999999, '');
        $sy = new CSSUnitValue(888888, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(999999, 888888)', $scale->toString());
    }

    public function testScaleWithVerySmallValues()
    {
        $sx = new CSSUnitValue(0.000001, '');
        $sy = new CSSUnitValue(0.000002, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(0.000001, 0.000002)', $scale->toString());
    }

    public function testScale3DWithVeryLargeValues()
    {
        $sx = new CSSUnitValue(999999, '');
        $sy = new CSSUnitValue(888888, '');
        $sz = new CSSUnitValue(777777, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame('scale3d(999999, 888888, 777777)', $scale->toString());
    }

    public function testScale3DWithVerySmallValues()
    {
        $sx = new CSSUnitValue(0.000001, '');
        $sy = new CSSUnitValue(0.000002, '');
        $sz = new CSSUnitValue(0.000003, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame('scale3d(0.000001, 0.000002, 0.000003)', $scale->toString());
    }

    public function testScaleWithMixedSigns()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(-3, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(2, -3)', $scale->toString());
    }

    public function testScale3DWithMixedSigns()
    {
        $sx = new CSSUnitValue(2, '');
        $sy = new CSSUnitValue(-3, '');
        $sz = new CSSUnitValue(4, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame('scale3d(2, -3, 4)', $scale->toString());
    }

    public function testScaleWithHighPrecision()
    {
        $sx = new CSSUnitValue(2.123456789, '');
        $sy = new CSSUnitValue(3.987654321, '');
        
        $scale = new CSSScale($sx, $sy);
        $this->assertSame('scale(2.123456789, 3.987654321)', $scale->toString());
    }

    public function testScale3DWithHighPrecision()
    {
        $sx = new CSSUnitValue(2.123456789, '');
        $sy = new CSSUnitValue(3.987654321, '');
        $sz = new CSSUnitValue(4.555555555, '');
        
        $scale = new CSSScale($sx, $sy, $sz);
        $this->assertSame('scale3d(2.123456789, 3.987654321, 4.555555555)', $scale->toString());
    }
}
