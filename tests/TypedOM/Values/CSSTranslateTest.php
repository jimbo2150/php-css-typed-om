<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTranslate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSTranslate class.
 */
class CSSTranslateTest extends TestCase
{
    public function testConstructor()
    {
        $x = new CSSUnitValue(10, 'px');
        $y = new CSSUnitValue(20, 'px');
        
        $translate = new CSSTranslate($x, $y);
        $this->assertInstanceOf(CSSTranslate::class, $translate);
    }

    public function testConstructorWithZ()
    {
        $x = new CSSUnitValue(10, 'px');
        $y = new CSSUnitValue(20, 'px');
        $z = new CSSUnitValue(30, 'px');
        
        $translate = new CSSTranslate($x, $y, $z);
        $this->assertInstanceOf(CSSTranslate::class, $translate);
    }

    public function testGetTransformType()
    {
        $x = new CSSUnitValue(10, 'px');
        $y = new CSSUnitValue(20, 'px');
        
        $translate = new CSSTranslate($x, $y);
        $this->assertSame('translate', $translate->getTransformType());
    }

    public function testToString2D()
    {
        $x = new CSSUnitValue(10, 'px');
        $y = new CSSUnitValue(20, 'px');
        
        $translate = new CSSTranslate($x, $y);
        $this->assertSame('translate(10px, 20px)', $translate->toString());
    }

    public function testToString3D()
    {
        $x = new CSSUnitValue(10, 'px');
        $y = new CSSUnitValue(20, 'px');
        $z = new CSSUnitValue(30, 'px');
        
        $translate = new CSSTranslate($x, $y, $z);
        $this->assertSame('translate3d(10px, 20px, 30px)', $translate->toString());
    }

    public function testToMatrix2D()
    {
        $x = new CSSUnitValue(10, 'px');
        $y = new CSSUnitValue(20, 'px');
        
        $translate = new CSSTranslate($x, $y);
        $matrix = $translate->toMatrix();
        
        $this->assertInstanceOf(DOMMatrix::class, $matrix);
        $this->assertSame(1, $matrix->m11);
        $this->assertSame(0, $matrix->m12);
        $this->assertSame(0, $matrix->m13);
        $this->assertSame(0, $matrix->m14);
        $this->assertSame(0, $matrix->m21);
        $this->assertSame(1, $matrix->m22);
        $this->assertSame(0, $matrix->m23);
        $this->assertSame(0, $matrix->m24);
        $this->assertSame(0, $matrix->m31);
        $this->assertSame(0, $matrix->m32);
        $this->assertSame(1, $matrix->m33);
        $this->assertSame(0, $matrix->m34);
        $this->assertSame(10, $matrix->m41);
        $this->assertSame(20, $matrix->m42);
        $this->assertSame(0, $matrix->m43);
        $this->assertSame(1, $matrix->m44);
    }

    public function testToMatrix3D()
    {
        $x = new CSSUnitValue(10, 'px');
        $y = new CSSUnitValue(20, 'px');
        $z = new CSSUnitValue(30, 'px');
        
        $translate = new CSSTranslate($x, $y, $z);
        $matrix = $translate->toMatrix();
        
        $this->assertInstanceOf(DOMMatrix::class, $matrix);
        $this->assertSame(10, $matrix->m41);
        $this->assertSame(20, $matrix->m42);
        $this->assertSame(30, $matrix->m43);
    }

    public function testClone()
    {
        $x = new CSSUnitValue(10, 'px');
        $y = new CSSUnitValue(20, 'px');
        
        $translate = new CSSTranslate($x, $y);
        $cloned = $translate->clone();
        
        $this->assertInstanceOf(CSSTranslate::class, $cloned);
        $this->assertNotSame($translate, $cloned);
    }

    public function testZeroValues()
    {
        $x = new CSSUnitValue(0, 'px');
        $y = new CSSUnitValue(0, 'px');
        
        $translate = new CSSTranslate($x, $y);
        $this->assertSame('translate(0px, 0px)', $translate->toString());
    }

    public function testNegativeValues()
    {
        $x = new CSSUnitValue(-10, 'px');
        $y = new CSSUnitValue(-20, 'px');
        
        $translate = new CSSTranslate($x, $y);
        $this->assertSame('translate(-10px, -20px)', $translate->toString());
    }
}