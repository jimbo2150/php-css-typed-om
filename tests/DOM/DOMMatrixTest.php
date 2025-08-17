<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Test\DOM;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrixReadOnly;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class DOMMatrixTest extends TestCase
{
    public function testConstructorDefault(): void
    {
        $matrix = new DOMMatrixReadOnly();
        self::assertTrue($matrix->isIdentity);
        self::assertTrue($matrix->is2D);
    }

    public function testConstructorFromArray2D(): void
    {
        $matrix = new DOMMatrixReadOnly([1, 2, 3, 4, 5, 6]);
        self::assertFalse($matrix->isIdentity);
        self::assertTrue($matrix->is2D);
        self::assertEquals(1.0, $matrix->m11);
        self::assertEquals(2.0, $matrix->m12);
        self::assertEquals(3.0, $matrix->m21);
        self::assertEquals(4.0, $matrix->m22);
        self::assertEquals(5.0, $matrix->m41);
        self::assertEquals(6.0, $matrix->m42);
    }

    public function testConstructorFromArray3D(): void
    {
        $matrix = new DOMMatrixReadOnly(array_fill(0, 16, 1.0));
        self::assertFalse($matrix->isIdentity);
        self::assertFalse($matrix->is2D);
        self::assertEquals(1.0, $matrix->m44);
    }

    public function testConstructorFromString2D(): void
    {
        $matrix = new DOMMatrixReadOnly('matrix(1, 2, 3, 4, 5, 6)');
        self::assertFalse($matrix->isIdentity);
        self::assertTrue($matrix->is2D);
        self::assertEquals(1.0, $matrix->m11);
        self::assertEquals(2.0, $matrix->m12);
        self::assertEquals(3.0, $matrix->m21);
        self::assertEquals(4.0, $matrix->m22);
        self::assertEquals(5.0, $matrix->m41);
        self::assertEquals(6.0, $matrix->m42);
    }

    public function testConstructorFromString3D(): void
    {
    	$matrix = new DOMMatrixReadOnly('matrix3d(1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1)');
    	self::assertTrue($matrix->isIdentity);
    	self::assertTrue($matrix->is2D);
    }

    public function testConstructorFromDOMMatrixReadOnly(): void
    {
        $original = new DOMMatrixReadOnly([1, 2, 3, 4, 5, 6]);
        $copy = new DOMMatrixReadOnly($original);
        self::assertEquals($original, $copy);
    }

    public function testToString2D(): void
    {
        $matrix = new DOMMatrixReadOnly([1, 2, 3, 4, 5, 6]);
        self::assertSame('matrix(1, 2, 3, 4, 5, 6)', $matrix->toString());
    }

    public function testToString3D(): void
    {
        $matrix = new DOMMatrixReadOnly(array_fill(0, 16, 1.0));
        self::assertSame('matrix3d(1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1)', $matrix->toString());
    }

    public function testMultiply(): void
    {
        $matrix1 = new DOMMatrixReadOnly([1, 0, 0, 1, 0, 0]); // Identity 2D
        $matrix2 = new DOMMatrixReadOnly([2, 0, 0, 2, 0, 0]); // Scale by 2
        $result = $matrix1->multiply($matrix2);
        self::assertInstanceOf(DOMMatrix::class, $result);
        self::assertEquals(2.0, $result->m11);
        self::assertEquals(2.0, $result->m22);
    }

    public function testFlipX(): void
    {
        $matrix = new DOMMatrixReadOnly();
        $flipped = $matrix->flipX();
        self::assertInstanceOf(DOMMatrix::class, $flipped);
        self::assertEquals(-1.0, $flipped->m11);
        self::assertEquals(1.0, $flipped->m22);
    }

    public function testFlipY(): void
    {
        $matrix = new DOMMatrixReadOnly();
        $flipped = $matrix->flipY();
        self::assertInstanceOf(DOMMatrix::class, $flipped);
        self::assertEquals(1.0, $flipped->m11);
        self::assertEquals(-1.0, $flipped->m22);
    }

    public function testInverse(): void
    {
        $matrix = new DOMMatrixReadOnly([2, 0, 0, 2, 0, 0]); // Scale by 2
        $inverse = $matrix->inverse();
        self::assertInstanceOf(DOMMatrix::class, $inverse);
        self::assertEquals(0.5, $inverse->m11);
        self::assertEquals(0.5, $inverse->m22);

        $identity = $matrix->multiply($inverse);
        self::assertEqualsWithDelta(1.0, $identity->m11, 0.000001);
        self::assertEqualsWithDelta(1.0, $identity->m22, 0.000001);
    }

    public function testTranslate(): void
    {
        $matrix = new DOMMatrixReadOnly();
        $translated = $matrix->translate(10, 20, 30);
        self::assertInstanceOf(DOMMatrix::class, $translated);
        self::assertEquals(10.0, $translated->m41);
        self::assertEquals(20.0, $translated->m42);
        self::assertEquals(30.0, $translated->m43);
    }

    public function testScale(): void
    {
        $matrix = new DOMMatrixReadOnly();
        $scaled = $matrix->scale(2, 3, 4);
        self::assertInstanceOf(DOMMatrix::class, $scaled);
        self::assertEquals(2.0, $scaled->m11);
        self::assertEquals(3.0, $scaled->m22);
        self::assertEquals(4.0, $scaled->m33);

        $scaledUniform = $matrix->scale(5);
        self::assertEquals(5.0, $scaledUniform->m11);
        self::assertEquals(5.0, $scaledUniform->m22);
        self::assertEquals(1.0, $scaledUniform->m33); // Corrected assertion
    }

    public function testRotate(): void
    {
        $matrix = new DOMMatrixReadOnly();
        $rotated = $matrix->rotate(90, 0, 0); // Rotate around X-axis
        self::assertInstanceOf(DOMMatrix::class, $rotated);
        self::assertEqualsWithDelta(1.0, $rotated->m11, 0.000001);
        self::assertEqualsWithDelta(0.0, $rotated->m12, 0.000001);
        self::assertEqualsWithDelta(0.0, $rotated->m13, 0.000001);
        self::assertEqualsWithDelta(0.0, $rotated->m21, 0.000001);
        self::assertEqualsWithDelta(0.0, $rotated->m22, 0.000001);
        self::assertEqualsWithDelta(1.0, $rotated->m23, 0.000001);
        self::assertEqualsWithDelta(0.0, $rotated->m31, 0.000001);
        self::assertEqualsWithDelta(-1.0, $rotated->m32, 0.000001);
        self::assertEqualsWithDelta(0.0, $rotated->m33, 0.000001);
    }

    public function testFromMatrix(): void
    {
        $original = new DOMMatrixReadOnly([1, 2, 3, 4, 5, 6]);
        $newMatrix = DOMMatrixReadOnly::fromMatrix($original);
        self::assertEquals($original, $newMatrix);
    }

    public function testFromFloat32Array(): void
    {
        $array = [1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 10.0, 20.0, 30.0, 1.0];
        $matrix = DOMMatrixReadOnly::fromFloat32Array($array);
        self::assertEquals(10.0, $matrix->m41);
    }

    public function testFromFloat64Array(): void
    {
        $array = [1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 0.0, 0.0, 0.0, 1.0, 0.0, 10.0, 20.0, 30.0, 1.0];
        $matrix = DOMMatrixReadOnly::fromFloat64Array($array);
        self::assertEquals(10.0, $matrix->m41);
    }

    public function testToFloat32Array(): void
    {
        $matrix = new DOMMatrixReadOnly([1, 2, 3, 4, 5, 6]);
        $array = $matrix->toFloat32Array();
        self::assertIsArray($array);
        self::assertCount(16, $array);
        self::assertEquals(1.0, $array[0]);
    }

    public function testToFloat64Array(): void
    {
        $matrix = new DOMMatrixReadOnly([1, 2, 3, 4, 5, 6]);
        $array = $matrix->toFloat64Array();
        self::assertIsArray($array);
        self::assertCount(16, $array);
        self::assertEquals(1.0, $array[0]);
    }

    // DOMMatrix specific tests

    public function testSetProperty(): void
    {
        $matrix = new DOMMatrix();
        $matrix->m11 = 5.0;
        self::assertEquals(5.0, $matrix->m11);
    }

    public function testInvertSelf(): void
    {
        $matrix = new DOMMatrix([2, 0, 0, 2, 0, 0]);
        $matrix->invertSelf();
        self::assertEquals(0.5, $matrix->m11);
        self::assertEquals(0.5, $matrix->m22);
    }

    public function testMultiplySelf(): void
    {
        $matrix1 = new DOMMatrix([1, 0, 0, 1, 0, 0]);
        $matrix2 = new DOMMatrixReadOnly([2, 0, 0, 2, 0, 0]);
        $matrix1->multiplySelf($matrix2);
        self::assertEquals(2.0, $matrix1->m11);
        self::assertEquals(2.0, $matrix1->m22);
    }

    public function testPreMultiplySelf(): void
    {
        $matrix1 = new DOMMatrix([1, 0, 0, 1, 0, 0]);
        $matrix2 = new DOMMatrixReadOnly([2, 0, 0, 2, 0, 0]);
        $matrix1->preMultiplySelf($matrix2);
        self::assertEquals(2.0, $matrix1->m11);
        self::assertEquals(2.0, $matrix1->m22);
    }

    public function testTranslateSelf(): void
    {
        $matrix = new DOMMatrix();
        $matrix->translateSelf(10, 20, 30);
        self::assertEquals(10.0, $matrix->m41);
        self::assertEquals(20.0, $matrix->m42);
        self::assertEquals(30.0, $matrix->m43);
    }

    public function testScaleSelf(): void
    {
        $matrix = new DOMMatrix();
        $matrix->scaleSelf(2, 3, 4);
        self::assertEquals(2.0, $matrix->m11);
        self::assertEquals(3.0, $matrix->m22);
        self::assertEquals(4.0, $matrix->m33);
    }

    public function testRotateSelf(): void
    {
        $matrix = new DOMMatrix();
        $matrix->rotateSelf(90, 0, 0);
        self::assertEqualsWithDelta(0.0, $matrix->m22, 0.000001);
        self::assertEqualsWithDelta(1.0, $matrix->m23, 0.000001);
        self::assertEqualsWithDelta(-1.0, $matrix->m32, 0.000001);
        self::assertEqualsWithDelta(0.0, $matrix->m33, 0.000001);
    }

    public function testScale3dSelf(): void
    {
        $matrix = new DOMMatrix();
        $matrix->scale3dSelf(5);
        self::assertEquals(5.0, $matrix->m11);
        self::assertEquals(5.0, $matrix->m22);
        self::assertEquals(5.0, $matrix->m33);
    }

    public function testRotateAxisAngleSelf(): void
    {
        $matrix = new DOMMatrix();
        $matrix->rotateAxisAngleSelf(1, 0, 0, 90); // Rotate 90 degrees around X-axis
        self::assertEqualsWithDelta(1.0, $matrix->m11, 0.000001);
        self::assertEqualsWithDelta(0.0, $matrix->m22, 0.000001);
        self::assertEqualsWithDelta(1.0, $matrix->m23, 0.000001); // Corrected assertion
        self::assertEqualsWithDelta(-1.0, $matrix->m32, 0.000001); // Corrected assertion
        self::assertEqualsWithDelta(0.0, $matrix->m33, 0.000001); // Corrected assertion
    }

    public function testRotateFromVectorSelf(): void
    {
        $matrix = new DOMMatrix();
        $matrix->rotateFromVectorSelf(1, 1); // Rotate 45 degrees
        self::assertEqualsWithDelta(cos(deg2rad(45)), $matrix->m11, 0.000001);
        self::assertEqualsWithDelta(sin(deg2rad(45)), $matrix->m12, 0.000001);
    }

    public function testSetMatrixValue(): void
    {
        $matrix = new DOMMatrix();
        $matrix->setMatrixValue('matrix(1, 2, 3, 4, 5, 6)');
        self::assertEquals(1.0, $matrix->m11);
        self::assertEquals(6.0, $matrix->m42);
    }

    public function testSkewXSelf(): void
    {
        $matrix = new DOMMatrix();
        $matrix->skewXSelf(45);
        self::assertEqualsWithDelta(1.0, $matrix->m11, 0.000001);
        self::assertEqualsWithDelta(tan(deg2rad(45)), $matrix->m21, 0.000001);
    }

    public function testSkewYSelf(): void
    {
        $matrix = new DOMMatrix();
        $matrix->skewYSelf(45);
        self::assertEqualsWithDelta(1.0, $matrix->m11, 0.000001);
        self::assertEqualsWithDelta(tan(deg2rad(45)), $matrix->m12, 0.000001);
    }
}