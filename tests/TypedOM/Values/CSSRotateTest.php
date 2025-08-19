<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSRotate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSRotate class.
 */
class CSSRotateTest extends TestCase
{
    public function testConstructor()
    {
        $angle = new CSSUnitValue(45, 'deg');
        
        $rotate = new CSSRotate($angle);
        $this->assertInstanceOf(CSSRotate::class, $rotate);
    }

    public function testConstructorWithAxis()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertInstanceOf(CSSRotate::class, $rotate);
    }

    public function testGetAngle()
    {
        $angle = new CSSUnitValue(45, 'deg');
        
        $rotate = new CSSRotate($angle);
        $this->assertSame($angle, $rotate->getAngle());
    }

    public function testGetX()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame($x, $rotate->getX());
    }

    public function testGetY()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame($y, $rotate->getY());
    }

    public function testGetZ()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame($z, $rotate->getZ());
    }

    public function testToString()
    {
        $angle = new CSSUnitValue(45, 'deg');
        
        $rotate = new CSSRotate($angle);
        $this->assertSame('rotate(45deg)', $rotate->toString());
    }

    public function testToStringWithAxis()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(1, 0, 0, 45deg)', $rotate->toString());
    }

    public function testToStringZeroAngle()
    {
        $angle = new CSSUnitValue(0, 'deg');
        
        $rotate = new CSSRotate($angle);
        $this->assertSame('rotate(0deg)', $rotate->toString());
    }

    public function testToStringNegativeAngle()
    {
        $angle = new CSSUnitValue(-45, 'deg');
        
        $rotate = new CSSRotate($angle);
        $this->assertSame('rotate(-45deg)', $rotate->toString());
    }

    public function testToStringDecimalAngle()
    {
        $angle = new CSSUnitValue(45.5, 'deg');
        
        $rotate = new CSSRotate($angle);
        $this->assertSame('rotate(45.5deg)', $rotate->toString());
    }

    public function testToStringLargeAngle()
    {
        $angle = new CSSUnitValue(720, 'deg');
        
        $rotate = new CSSRotate($angle);
        $this->assertSame('rotate(720deg)', $rotate->toString());
    }

    public function testToStringVerySmallAngle()
    {
        $angle = new CSSUnitValue(0.001, 'deg');
        
        $rotate = new CSSRotate($angle);
        $this->assertSame('rotate(0.001deg)', $rotate->toString());
    }

    public function testDifferentAngleUnits()
    {
        $angle = new CSSUnitValue(0.785, 'rad');
        
        $rotate = new CSSRotate($angle);
        $this->assertSame('rotate(0.785rad)', $rotate->toString());
    }

    public function testTurnUnit()
    {
        $angle = new CSSUnitValue(0.25, 'turn');
        
        $rotate = new CSSRotate($angle);
        $this->assertSame('rotate(0.25turn)', $rotate->toString());
    }

    public function testGradUnit()
    {
        $angle = new CSSUnitValue(50, 'grad');
        
        $rotate = new CSSRotate($angle);
        $this->assertSame('rotate(50grad)', $rotate->toString());
    }

    public function testIsValid()
    {
        $angle = new CSSUnitValue(45, 'deg');
        
        $rotate = new CSSRotate($angle);
        $this->assertTrue($rotate->isValid());
    }

    public function testClone()
    {
        $angle = new CSSUnitValue(45, 'deg');
        
        $rotate = new CSSRotate($angle);
        $cloned = $rotate->clone();
        
        $this->assertInstanceOf(CSSRotate::class, $cloned);
        $this->assertNotSame($rotate, $cloned);
    }

    public function testToUnit()
    {
        $angle = new CSSUnitValue(45, 'deg');
        
        $rotate = new CSSRotate($angle);
        $result = $rotate->to('rad');
        
        $this->assertNull($result);
    }

    public function testRotate3DWithDifferentAxes()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(0, '');
        $y = new CSSUnitValue(1, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(0, 1, 0, 45deg)', $rotate->toString());
    }

    public function testRotate3DWithZAxis()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(0, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(1, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(0, 0, 1, 45deg)', $rotate->toString());
    }

    public function testRotate3DWithCustomAxis()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(0.707, '');
        $y = new CSSUnitValue(0.707, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(0.707, 0.707, 0, 45deg)', $rotate->toString());
    }

    public function testRotate3DWithNegativeAxis()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(-1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(-1, 0, 0, 45deg)', $rotate->toString());
    }

    public function testRotate3DWithDecimalAxis()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(0.333, '');
        $y = new CSSUnitValue(0.667, '');
        $z = new CSSUnitValue(0.999, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(0.333, 0.667, 0.999, 45deg)', $rotate->toString());
    }

    public function testRotate3DWithZeroAxis()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(0, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(0, 0, 0, 45deg)', $rotate->toString());
    }

    public function testRotate3DWithVeryLargeAxis()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(999, '');
        $y = new CSSUnitValue(888, '');
        $z = new CSSUnitValue(777, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(999, 888, 777, 45deg)', $rotate->toString());
    }

    public function testRotate3DWithVerySmallAxis()
    {
        $angle = new CSSUnitValue(45, 'deg');
        $x = new CSSUnitValue(0.0001, '');
        $y = new CSSUnitValue(0.00001, '');
        $z = new CSSUnitValue(0.000001, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(0.0001, 0.00001, 0.000001, 45deg)', $rotate->toString());
    }

    public function testRotate3DWithNegativeAngle()
    {
        $angle = new CSSUnitValue(-45, 'deg');
        $x = new CSSUnitValue(1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(1, 0, 0, -45deg)', $rotate->toString());
    }

    public function testRotate3DWithDecimalAngle()
    {
        $angle = new CSSUnitValue(45.5, 'deg');
        $x = new CSSUnitValue(1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(1, 0, 0, 45.5deg)', $rotate->toString());
    }

    public function testRotate3DWithLargeAngle()
    {
        $angle = new CSSUnitValue(720, 'deg');
        $x = new CSSUnitValue(1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(1, 0, 0, 720deg)', $rotate->toString());
    }

    public function testRotate3DWithVerySmallAngle()
    {
        $angle = new CSSUnitValue(0.001, 'deg');
        $x = new CSSUnitValue(1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(1, 0, 0, 0.001deg)', $rotate->toString());
    }

    public function testRotate3DWithDifferentAngleUnits()
    {
        $angle = new CSSUnitValue(0.785, 'rad');
        $x = new CSSUnitValue(1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(1, 0, 0, 0.785rad)', $rotate->toString());
    }

    public function testRotate3DWithTurnUnit()
    {
        $angle = new CSSUnitValue(0.25, 'turn');
        $x = new CSSUnitValue(1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(1, 0, 0, 0.25turn)', $rotate->toString());
    }

    public function testRotate3DWithGradUnit()
    {
        $angle = new CSSUnitValue(50, 'grad');
        $x = new CSSUnitValue(1, '');
        $y = new CSSUnitValue(0, '');
        $z = new CSSUnitValue(0, '');
        
        $rotate = new CSSRotate($angle, $x, $y, $z);
        $this->assertSame('rotate3d(1, 0, 0, 50grad)', $rotate->toString());
    }
}
