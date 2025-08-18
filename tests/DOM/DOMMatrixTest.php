<?php

declare(strict_types=1);

namespace Tests\DOM;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrixReadOnly;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DOMMatrix class.
 */
class DOMMatrixTest extends TestCase
{
    public function testConstructor()
    {
        $matrix = new DOMMatrix();
        $this->assertInstanceOf(DOMMatrix::class, $matrix);
        $this->assertSame(1.0, $matrix->m11);
    }

    public function testInvertSelf()
    {
        $matrix = new DOMMatrix('matrix(2, 0, 0, 2, 10, 20)');
        $result = $matrix->invertSelf();
        
        $this->assertSame($matrix, $result);
        $this->assertSame(0.5, $matrix->m11);
        $this->assertSame(0.0, $matrix->m12);
        $this->assertSame(0.0, $matrix->m21);
        $this->assertSame(0.5, $matrix->m22);
        $this->assertSame(-5.0, $matrix->m41);
        $this->assertSame(-10.0, $matrix->m42);
    }

    public function testMultiplySelf()
    {
        $matrix1 = new DOMMatrix('matrix(2, 0, 0, 2, 0, 0)');
        $matrix2 = new DOMMatrix('matrix(1, 0, 0, 1, 10, 20)');
        
        $result = $matrix1->multiplySelf($matrix2);
        
        $this->assertSame($matrix1, $result);
        $this->assertSame(2.0, $matrix1->m11);
        $this->assertSame(0.0, $matrix1->m12);
        $this->assertSame(0.0, $matrix1->m21);
        $this->assertSame(2.0, $matrix1->m22);
        $this->assertSame(20.0, $matrix1->m41);
        $this->assertSame(40.0, $matrix1->m42);
    }

    public function testPreMultiplySelf()
    {
        $matrix1 = new DOMMatrix('matrix(2, 0, 0, 2, 0, 0)');
        $matrix2 = new DOMMatrix('matrix(1, 0, 0, 1, 10, 20)');
        
        $result = $matrix1->preMultiplySelf($matrix2);
        
        $this->assertSame($matrix1, $result);
        $this->assertSame(2.0, $matrix1->m11);
        $this->assertSame(0.0, $matrix1->m12);
        $this->assertSame(0.0, $matrix1->m21);
        $this->assertSame(2.0, $matrix1->m22);
        $this->assertSame(20.0, $matrix1->m41);
        $this->assertSame(40.0, $matrix1->m42);
    }

    public function testTranslateSelf()
    {
        $matrix = new DOMMatrix();
        $result = $matrix->translateSelf(10, 20, 30);
        
        $this->assertSame($matrix, $result);
        $this->assertSame(10.0, $matrix->m41);
        $this->assertSame(20.0, $matrix->m42);
        $this->assertSame(30.0, $matrix->m43);
    }

    public function testScaleSelf()
    {
        $matrix = new DOMMatrix();
        $result = $matrix->scaleSelf(2, 3, 4);
        
        $this->assertSame($matrix, $result);
        $this->assertSame(2.0, $matrix->m11);
        $this->assertSame(3.0, $matrix->m22);
        $this->assertSame(4.0, $matrix->m33);
    }

    public function testScaleSelfWithNullScaleY()
    {
        $matrix = new DOMMatrix();
        $result = $matrix->scaleSelf(2);
        
        $this->assertSame($matrix, $result);
        $this->assertSame(2.0, $matrix->m11);
        $this->assertSame(2.0, $matrix->m22);
        $this->assertSame(1.0, $matrix->m33);
    }

    public function testRotateSelf()
    {
        $matrix = new DOMMatrix();
        $result = $matrix->rotateSelf(45);
        
        $this->assertSame($matrix, $result);
        $this->assertEqualsWithDelta(0.7071, $matrix->m11, 0.0001);
        $this->assertEqualsWithDelta(0.7071, $matrix->m12, 0.0001);
        $this->assertEqualsWithDelta(-0.7071, $matrix->m21, 0.0001);
        $this->assertEqualsWithDelta(0.7071, $matrix->m22, 0.0001);
    }

    public function testRotateSelfWithMultipleAngles()
    {
        $matrix = new DOMMatrix();
        $result = $matrix->rotateSelf(45, 30, 60);
        
        $this->assertSame($matrix, $result);
        $this->assertNotEquals(1.0, $matrix->m11);
        $this->assertNotEquals(0.0, $matrix->m12);
    }

    public function testScale3dSelf()
    {
        $matrix = new DOMMatrix();
        $result = $matrix->scale3dSelf(2, 10, 20, 30);
        
        $this->assertSame($matrix, $result);
        $this->assertSame(2.0, $matrix->m11);
        $this->assertSame(2.0, $matrix->m22);
        $this->assertSame(2.0, $matrix->m33);
    }

    public function testRotateAxisAngleSelf()
    {
        $matrix = new DOMMatrix();
        $result = $matrix->rotateAxisAngleSelf(1, 1, 1, 45);
        
        $this->assertSame($matrix, $result);
        $this->assertNotEquals(1.0, $matrix->m11);
        $this->assertNotEquals(0.0, $matrix->m12);
    }

    public function testRotateFromVectorSelf()
    {
        $matrix = new DOMMatrix();
        $result = $matrix->rotateFromVectorSelf(1, 1);
        
        $this->assertSame($matrix, $result);
        $this->assertNotEquals(1.0, $matrix->m11);
        $this->assertNotEquals(0.0, $matrix->m12);
    }

    public function testSetMatrixValue()
    {
        $matrix = new DOMMatrix();
        $result = $matrix->setMatrixValue('matrix(2, 0, 0, 2, 10, 20)');
        
        $this->assertSame($matrix, $result);
        $this->assertSame(2.0, $matrix->m11);
        $this->assertSame(0.0, $matrix->m12);
        $this->assertSame(0.0, $matrix->m21);
        $this->assertSame(2.0, $matrix->m22);
        $this->assertSame(10.0, $matrix->m41);
        $this->assertSame(20.0, $matrix->m42);
    }

    public function testSkewXSelf()
    {
        $matrix = new DOMMatrix();
        $result = $matrix->skewXSelf(45);
        
        $this->assertSame($matrix, $result);
        $this->assertEqualsWithDelta(1.0, $matrix->m11, 0.0001);
        $this->assertEqualsWithDelta(0.0, $matrix->m12, 0.0001);
        $this->assertEqualsWithDelta(1.0, $matrix->m21, 0.0001);
        $this->assertEqualsWithDelta(1.0, $matrix->m22, 0.0001);
    }

    public function testSkewYSelf()
    {
        $matrix = new DOMMatrix();
        $result = $matrix->skewYSelf(45);
        
        $this->assertSame($matrix, $result);
        $this->assertEqualsWithDelta(1.0, $matrix->m11, 0.0001);
        $this->assertEqualsWithDelta(1.0, $matrix->m12, 0.0001);
        $this->assertEqualsWithDelta(0.0, $matrix->m21, 0.0001);
        $this->assertEqualsWithDelta(1.0, $matrix->m22, 0.0001);
    }

    public function testToFloat64Array()
    {
        $matrix = new DOMMatrix();
        $array = $matrix->toFloat64Array();
        
        $expected = [
            1, 0, 0, 0,
            0, 1, 0, 0,
            0, 0, 1, 0,
            0, 0, 0, 1
        ];
        
        $this->assertSame($expected, $array);
    }

    public function testPropertyAccess()
    {
        $matrix = new DOMMatrix();
        
        $this->assertSame(1.0, $matrix->m11);
        $this->assertSame(0.0, $matrix->m12);
        $this->assertSame(0.0, $matrix->m13);
        $this->assertSame(0.0, $matrix->m14);
        $this->assertSame(0.0, $matrix->m21);
        $this->assertSame(1.0, $matrix->m22);
        $this->assertSame(0.0, $matrix->m23);
        $this->assertSame(0.0, $matrix->m24);
        $this->assertSame(0.0, $matrix->m31);
        $this->assertSame(0.0, $matrix->m32);
        $this->assertSame(1.0, $matrix->m33);
        $this->assertSame(0.0, $matrix->m34);
        $this->assertSame(0.0, $matrix->m41);
        $this->assertSame(0.0, $matrix->m42);
        $this->assertSame(0.0, $matrix->m43);
        $this->assertSame(1.0, $matrix->m44);
    }

    public function testPropertySet()
    {
        $matrix = new DOMMatrix();
        
        $matrix->m11 = 2.0;
        $matrix->m22 = 3.0;
        $matrix->m41 = 10.0;
        $matrix->m42 = 20.0;
        
        $this->assertSame(2.0, $matrix->m11);
        $this->assertSame(3.0, $matrix->m22);
        $this->assertSame(10.0, $matrix->m41);
        $this->assertSame(20.0, $matrix->m42);
    }

    public function testInvalidPropertySet()
    {
        $this->expectException(\InvalidArgumentException::class);
        $matrix = new DOMMatrix();
        $matrix->invalid = 1.0;
    }
}