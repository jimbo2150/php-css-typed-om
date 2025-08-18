<?php

declare(strict_types=1);

namespace Tests\DOM;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrixReadOnly;
use PHPUnit\Framework\TestCase;

/**
 * Tests for DOMMatrixReadOnly class.
 */
class DOMMatrixReadOnlyTest extends TestCase
{
    public function testConstructorWithNull()
    {
        $matrix = new DOMMatrixReadOnly();
        
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
        $this->assertSame(0, $matrix->m41);
        $this->assertSame(0, $matrix->m42);
        $this->assertSame(0, $matrix->m43);
        $this->assertSame(1, $matrix->m44);
    }

    public function testConstructorWithString()
    {
        $matrix = new DOMMatrixReadOnly('matrix(1, 0, 0, 1, 10, 20)');
        
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
        $this->assertSame(10.0, $matrix->m41);
        $this->assertSame(20.0, $matrix->m42);
        $this->assertSame(0.0, $matrix->m43);
        $this->assertSame(1.0, $matrix->m44);
    }

    public function testConstructorWithArray()
    {
        $values = [
            1, 0, 0, 0,
            0, 2, 0, 0,
            0, 0, 3, 0,
            10, 20, 30, 1
        ];
        
        $matrix = new DOMMatrixReadOnly($values);
        
        $this->assertSame(1.0, $matrix->m11);
        $this->assertSame(2.0, $matrix->m22);
        $this->assertSame(3.0, $matrix->m33);
        $this->assertSame(10.0, $matrix->m41);
        $this->assertSame(20.0, $matrix->m42);
        $this->assertSame(30.0, $matrix->m43);
    }

    public function testToString()
    {
        $matrix = new DOMMatrixReadOnly();
        $this->assertSame('matrix3d(1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1)', $matrix->toString());
        
        $matrix = new DOMMatrixReadOnly('matrix(1, 0, 0, 1, 10, 20)');
        $this->assertSame('matrix(1, 0, 0, 1, 10, 20)', $matrix->toString());
    }

    public function testMultiply()
    {
        $matrix1 = new DOMMatrixReadOnly('matrix(2, 0, 0, 2, 0, 0)');
        $matrix2 = new DOMMatrixReadOnly('matrix(1, 0, 0, 1, 10, 20)');
        
        $result = $matrix1->multiply($matrix2);
        
        $this->assertSame(2.0, $result->m11);
        $this->assertSame(0.0, $result->m12);
        $this->assertSame(0.0, $result->m21);
        $this->assertSame(2.0, $result->m22);
        $this->assertSame(20.0, $result->m41);
        $this->assertSame(40.0, $result->m42);
    }

    public function testFlipX()
    {
        $matrix = new DOMMatrixReadOnly('matrix(1, 0, 0, 1, 10, 20)');
        $flipped = $matrix->flipX();
        
        $this->assertSame(-1.0, $flipped->m11);
        $this->assertSame(0.0, $flipped->m12);
        $this->assertSame(0.0, $flipped->m21);
        $this->assertSame(1.0, $flipped->m22);
        $this->assertSame(-10.0, $flipped->m41);
        $this->assertSame(20.0, $flipped->m42);
    }

    public function testFlipY()
    {
        $matrix = new DOMMatrixReadOnly('matrix(1, 0, 0, 1, 10, 20)');
        $flipped = $matrix->flipY();
        
        $this->assertSame(1.0, $flipped->m11);
        $this->assertSame(0.0, $flipped->m12);
        $this->assertSame(0.0, $flipped->m21);
        $this->assertSame(-1.0, $flipped->m22);
        $this->assertSame(10.0, $flipped->m41);
        $this->assertSame(-20.0, $flipped->m42);
    }

    public function testInverse()
    {
        $matrix = new DOMMatrixReadOnly('matrix(2, 0, 0, 2, 10, 20)');
        $inverse = $matrix->inverse();
        
        $this->assertSame(0.5, $inverse->m11);
        $this->assertSame(0.0, $inverse->m12);
        $this->assertSame(0.0, $inverse->m21);
        $this->assertSame(0.5, $inverse->m22);
        $this->assertSame(-5.0, $inverse->m41);
        $this->assertSame(-10.0, $inverse->m42);
    }

    public function testTranslate()
    {
        $matrix = new DOMMatrixReadOnly();
        $translated = $matrix->translate(10, 20, 30);
        
        $this->assertSame(1.0, $translated->m11);
        $this->assertSame(0.0, $translated->m12);
        $this->assertSame(0.0, $translated->m13);
        $this->assertSame(0.0, $translated->m14);
        $this->assertSame(0.0, $translated->m21);
        $this->assertSame(1.0, $translated->m22);
        $this->assertSame(0.0, $translated->m23);
        $this->assertSame(0.0, $translated->m24);
        $this->assertSame(0.0, $translated->m31);
        $this->assertSame(0.0, $translated->m32);
        $this->assertSame(1.0, $translated->m33);
        $this->assertSame(0.0, $translated->m34);
        $this->assertSame(10.0, $translated->m41);
        $this->assertSame(20.0, $translated->m42);
        $this->assertSame(30.0, $translated->m43);
        $this->assertSame(1.0, $translated->m44);
    }

    public function testScale()
    {
        $matrix = new DOMMatrixReadOnly();
        $scaled = $matrix->scale(2, 3, 4);
        
        $this->assertSame(2.0, $scaled->m11);
        $this->assertSame(0.0, $scaled->m12);
        $this->assertSame(0.0, $scaled->m13);
        $this->assertSame(0.0, $scaled->m14);
        $this->assertSame(0.0, $scaled->m21);
        $this->assertSame(3.0, $scaled->m22);
        $this->assertSame(0.0, $scaled->m23);
        $this->assertSame(0.0, $scaled->m24);
        $this->assertSame(0.0, $scaled->m31);
        $this->assertSame(0.0, $scaled->m32);
        $this->assertSame(4.0, $scaled->m33);
        $this->assertSame(0.0, $scaled->m34);
        $this->assertSame(0.0, $scaled->m41);
        $this->assertSame(0.0, $scaled->m42);
        $this->assertSame(0.0, $scaled->m43);
        $this->assertSame(1.0, $scaled->m44);
    }

    public function testRotate()
    {
        $matrix = new DOMMatrixReadOnly();
        $rotated = $matrix->rotate(45);
        
        $this->assertEqualsWithDelta(0.7071, $rotated->m11, 0.0001);
        $this->assertEqualsWithDelta(0.7071, $rotated->m12, 0.0001);
        $this->assertEqualsWithDelta(-0.7071, $rotated->m21, 0.0001);
        $this->assertEqualsWithDelta(0.7071, $rotated->m22, 0.0001);
    }

    public function testFromMatrix()
    {
        $original = new DOMMatrixReadOnly('matrix(2, 0, 0, 2, 10, 20)');
        $fromMatrix = DOMMatrixReadOnly::fromMatrix($original);
        
        $this->assertSame(2.0, $fromMatrix->m11);
        $this->assertSame(0.0, $fromMatrix->m12);
        $this->assertSame(10.0, $fromMatrix->m41);
        $this->assertSame(20.0, $fromMatrix->m42);
    }

    public function testFromFloat32Array()
    {
        $values = [
            1, 0, 0, 0,
            0, 2, 0, 0,
            0, 0, 3, 0,
            10, 20, 30, 1
        ];
        
        $matrix = DOMMatrixReadOnly::fromFloat32Array($values);
        
        $this->assertSame(1.0, $matrix->m11);
        $this->assertSame(2.0, $matrix->m22);
        $this->assertSame(3.0, $matrix->m33);
        $this->assertSame(10.0, $matrix->m41);
        $this->assertSame(20.0, $matrix->m42);
        $this->assertSame(30.0, $matrix->m43);
    }

    public function testFromFloat64Array()
    {
        $values = [
            1, 0, 0, 0,
            0, 2, 0, 0,
            0, 0, 3, 0,
            10, 20, 30, 1
        ];
        
        $matrix = DOMMatrixReadOnly::fromFloat64Array($values);
        
        $this->assertSame(1.0, $matrix->m11);
        $this->assertSame(2.0, $matrix->m22);
        $this->assertSame(3.0, $matrix->m33);
        $this->assertSame(10.0, $matrix->m41);
        $this->assertSame(20.0, $matrix->m42);
        $this->assertSame(30.0, $matrix->m43);
    }

    public function testToFloat32Array()
    {
        $matrix = new DOMMatrixReadOnly();
        $array = $matrix->toFloat32Array();
        
        $expected = [
            1, 0, 0, 0,
            0, 1, 0, 0,
            0, 0, 1, 0,
            0, 0, 0, 1
        ];
        
        $this->assertSame($expected, $array);
    }

    public function testToFloat64Array()
    {
        $matrix = new DOMMatrixReadOnly();
        $array = $matrix->toFloat64Array();
        
        $expected = [
            1, 0, 0, 0,
            0, 1, 0, 0,
            0, 0, 1, 0,
            0, 0, 0, 1
        ];
        
        $this->assertSame($expected, $array);
    }

    public function testInvalidConstructor()
    {
        $this->expectException(\InvalidArgumentException::class);
        new DOMMatrixReadOnly('invalid matrix string');
    }

    public function testInvalidArrayLength()
    {
        $this->expectException(\InvalidArgumentException::class);
        new DOMMatrixReadOnly([1, 2, 3]);
    }
}