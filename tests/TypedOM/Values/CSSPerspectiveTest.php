<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPerspective;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSPerspective class.
 */
class CSSPerspectiveTest extends TestCase
{
    public function testConstructor()
    {
        $length = new CSSUnitValue(100, 'px');
        
        $perspective = new CSSPerspective($length);
        $this->assertInstanceOf(CSSPerspective::class, $perspective);
    }

    public function testGetLength()
    {
        $length = new CSSUnitValue(100, 'px');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame($length, $perspective->getLength());
    }

    public function testToString()
    {
        $length = new CSSUnitValue(100, 'px');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(100px)', $perspective->toString());
    }

    public function testIsValid()
    {
        $length = new CSSUnitValue(100, 'px');
        
        $perspective = new CSSPerspective($length);
        $this->assertTrue($perspective->isValid());
    }

    public function testClone()
    {
        $length = new CSSUnitValue(100, 'px');
        
        $perspective = new CSSPerspective($length);
        $cloned = $perspective->clone();
        
        $this->assertInstanceOf(CSSPerspective::class, $cloned);
        $this->assertNotSame($perspective, $cloned);
    }

    public function testToUnit()
    {
        $length = new CSSUnitValue(100, 'px');
        
        $perspective = new CSSPerspective($length);
        $result = $perspective->to('em');
        
        $this->assertNull($result);
    }

    public function testZeroLength()
    {
        $length = new CSSUnitValue(0, 'px');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(0px)', $perspective->toString());
    }

    public function testNegativeLength()
    {
        $length = new CSSUnitValue(-100, 'px');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(-100px)', $perspective->toString());
    }

    public function testDecimalLength()
    {
        $length = new CSSUnitValue(100.5, 'px');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(100.5px)', $perspective->toString());
    }

    public function testDifferentUnits()
    {
        $length = new CSSUnitValue(5, 'em');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(5em)', $perspective->toString());
    }

    public function testRemUnit()
    {
        $length = new CSSUnitValue(2, 'rem');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(2rem)', $perspective->toString());
    }

    public function testVhUnit()
    {
        $length = new CSSUnitValue(50, 'vh');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(50vh)', $perspective->toString());
    }

    public function testVwUnit()
    {
        $length = new CSSUnitValue(25, 'vw');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(25vw)', $perspective->toString());
    }

    public function testCmUnit()
    {
        $length = new CSSUnitValue(2.54, 'cm');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(2.54cm)', $perspective->toString());
    }

    public function testMmUnit()
    {
        $length = new CSSUnitValue(10, 'mm');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(10mm)', $perspective->toString());
    }

    public function testInUnit()
    {
        $length = new CSSUnitValue(1, 'in');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(1in)', $perspective->toString());
    }

    public function testPtUnit()
    {
        $length = new CSSUnitValue(72, 'pt');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(72pt)', $perspective->toString());
    }

    public function testPcUnit()
    {
        $length = new CSSUnitValue(6, 'pc');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(6pc)', $perspective->toString());
    }

    public function testVerySmallLength()
    {
        $length = new CSSUnitValue(0.1, 'px');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(0.1px)', $perspective->toString());
    }

    public function testVeryLargeLength()
    {
        $length = new CSSUnitValue(9999, 'px');
        
        $perspective = new CSSPerspective($length);
        $this->assertSame('perspective(9999px)', $perspective->toString());
    }
}
