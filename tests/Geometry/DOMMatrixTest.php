<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Geometry;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMatrixComponent;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSTransformValue;
use PHPUnit\Framework\TestCase;

final class DOMMatrixTest extends TestCase
{
	public function testIdentityAndStringConstruction(): void
	{
		$identity = new DOMMatrixReadOnly();
		self::assertTrue($identity->is2D);
		self::assertTrue($identity->isIdentity);
		self::assertSame(1.0, $identity->a);
		self::assertSame(1.0, $identity->d);
		self::assertSame(0.0, $identity->e);
		self::assertSame('matrix(1, 0, 0, 1, 0, 0)', (string) $identity);
		self::assertCount(16, $identity);
		self::assertSame(1.0, $identity[0]);

		self::assertTrue((new DOMMatrixReadOnly(''))->isIdentity);
		self::assertTrue((new DOMMatrixReadOnly('none'))->isIdentity);

		$fromCss = new DOMMatrix('translate(10px, 20px) scale(2, 3)');
		self::assertTrue($fromCss->is2D);
		self::assertSame(2.0, $fromCss->a);
		self::assertSame(3.0, $fromCss->d);
		self::assertSame(10.0, $fromCss->e);
		self::assertSame(20.0, $fromCss->f);
	}

	public function testReadOnlyImmutabilityAndMutableOperations(): void
	{
		$ro = new DOMMatrixReadOnly([1, 0, 0, 1, 10, 20]);
		$translated = $ro->translate(5, -5);
		self::assertInstanceOf(DOMMatrix::class, $translated);
		self::assertSame(10.0, $ro->e);
		self::assertSame(15.0, $translated->e);
		self::assertSame(15.0, $translated->f);

		try {
			$ro[0] = 2.0;
			self::fail('Expected InvalidArgumentException on read-only matrix offsetSet.');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
			self::assertSame(1.0, $ro[0]);
		}
	}

	public function testReadOnlyMatrixPropertyWriteThrowsError(): void
	{
		$ro = new DOMMatrixReadOnly([1, 0, 0, 1, 10, 20]);
		$this->expectException(Error::class);
		/** @phpstan-ignore assign.propertyReadOnly */
		$ro->a = 2.0;
	}

	public function testDictionaryInitAndAliasConflictValidation(): void
	{
		$fromDict = DOMMatrix::fromMatrix(['a' => 2, 'm11' => 2, 'e' => 15, 'm41' => 15]);
		self::assertTrue($fromDict->is2D);
		self::assertSame(2.0, $fromDict->m11);
		self::assertSame(15.0, $fromDict->m41);

		$this->expectException(InvalidArgumentException::class);
		DOMMatrix::fromMatrix(['a' => 2, 'm11' => 3]);
	}

	public function testIs2DConflictThrows(): void
	{
		$this->expectException(InvalidArgumentException::class);
		DOMMatrix::fromMatrix(['is2D' => true, 'm43' => 5]);
	}

	public function testRotateScaleSkewInvertAndTransformPoint(): void
	{
		// 1-arg rotate rotates around Z-axis preserving is2D
		$rot = (new DOMMatrix())->rotateSelf(90.0);
		self::assertTrue($rot->is2D);
		$pt = $rot->transformPoint(['x' => 1, 'y' => 0]);
		self::assertEqualsWithDelta(0.0, $pt['x'], 1e-9);
		self::assertEqualsWithDelta(1.0, $pt['y'], 1e-9);

		// rotateFromVector
		$rotVec = (new DOMMatrixReadOnly())->rotateFromVector(0.0, 1.0);
		$pt2 = $rotVec->transformPoint(['x' => 1, 'y' => 0]);
		self::assertEqualsWithDelta(0.0, $pt2['x'], 1e-9);
		self::assertEqualsWithDelta(1.0, $pt2['y'], 1e-9);

		// scale defaults scaleY to scaleX
		$scaled = (new DOMMatrixReadOnly())->scale(3.0);
		self::assertSame(3.0, $scaled->a);
		self::assertSame(3.0, $scaled->d);
		self::assertTrue($scaled->is2D);

		// 3D translation marks matrix 3D and round-trips through inverse
		$m3d = (new DOMMatrix())->translateSelf(10, 20, 30)->scaleSelf(2, 4, 5);
		self::assertFalse($m3d->is2D);
		$inv = $m3d->inverse();
		$prod = $m3d->multiply($inv);
		self::assertEqualsWithDelta(1.0, $prod->m11, 1e-9);
		self::assertEqualsWithDelta(0.0, $prod->m41, 1e-9);
		self::assertEqualsWithDelta(0.0, $prod->m43, 1e-9);
		self::assertStringStartsWith('matrix3d(', (string) $m3d);

		// Singular matrix invertSelf is atomic
		$singular = new DOMMatrix([0, 0, 0, 0, 5, 6]);
		try {
			$singular->invertSelf();
			self::fail('Expected singular matrix inversion to throw.');
		} catch (InvalidArgumentException) {
			self::assertSame(5.0, $singular->e);
			self::assertSame(6.0, $singular->f);
		}
	}

	public function testMatrixComponentIntegrationAndLiveIs2D(): void
	{
		$domMatrix = new DOMMatrix([1, 0, 0, 0, 0, 1, 0, 0, 0, 0, 1, 0, 10, 20, 30, 1]);
		$comp = new CSSMatrixComponent($domMatrix);
		self::assertFalse($comp->is2D);
		self::assertSame('matrix3d', $comp->getFunctionName());
		self::assertInstanceOf(DOMMatrix::class, $comp->matrix);
		self::assertSame(30.0, $comp->toMatrix()->m43);

		// Switching is2D updates functionName, __toString(), and flattens toMatrix()
		$comp->is2D = true;
		self::assertSame('matrix', $comp->getFunctionName());
		self::assertSame('matrix(1, 0, 0, 1, 10, 20)', (string) $comp);
		$flattened = $comp->toMatrix();
		self::assertTrue($flattened->is2D);
		self::assertSame(0.0, $flattened->m43);

		// Transform list preserves 3D flag when any component is 3D
		$transform = CSSStyleValue::parse('transform', 'translate(10px, 0px) rotateZ(90deg)');
		self::assertInstanceOf(CSSTransformValue::class, $transform);
		$composed = $transform->toMatrix();
		self::assertInstanceOf(DOMMatrix::class, $composed);
		self::assertFalse($composed->is2D);
		self::assertEqualsWithDelta(10.0, $composed->m41, 1e-9);
	}

	public function testRejectsCssInjectionInMatrixString(): void
	{
		$this->expectException(InvalidArgumentException::class);
		new DOMMatrix('translate(10px); body { color: red }');
	}

	public function testFlipXFlipYAndAtomicSelfMutationOnOverflow(): void
	{
		$base = new DOMMatrix([2, 0, 0, 3, 10, 20]);
		$flippedX = $base->flipX();
		$flippedY = $base->flipY();

		self::assertSame(-2.0, $flippedX->a);
		self::assertSame(3.0, $flippedX->d);
		self::assertSame(10.0, $flippedX->e);
		self::assertSame(2.0, $flippedY->a);
		self::assertSame(-3.0, $flippedY->d);

		$huge = new DOMMatrix([1e308, 0, 0, 1e308, 0, 0]);
		try {
			$huge->scaleSelf(10.0, 10.0, 1.0, 5.0, 6.0, 0.0);
			self::fail('Expected scaleSelf overflow to throw.');
		} catch (InvalidArgumentException) {
			self::assertSame(1e308, $huge->a);
			self::assertSame(0.0, $huge->e);
			self::assertSame(0.0, $huge->f);
		}
	}

	public function testExtremeAxisRotation3DOperationsAndSerialization(): void
	{
		$hugeRot = (new DOMMatrix())->rotateAxisAngle(1e200, 0.0, 0.0, 90.0);
		self::assertEqualsWithDelta(0.0, $hugeRot->m22, 1e-12);
		self::assertEqualsWithDelta(1.0, $hugeRot->m23, 1e-12);

		$tinyRot = (new DOMMatrix())->rotateAxisAngle(0.0, 1e-200, 0.0, 90.0);
		self::assertEqualsWithDelta(0.0, $tinyRot->m11, 1e-12);
		self::assertEqualsWithDelta(-1.0, $tinyRot->m13, 1e-12);

		$m3d = (new DOMMatrix())
			->translate(1.0, 2.0, 3.0)
			->scale(2.0, 3.0, 4.0)
			->skewX(15.0)
			->skewY(10.0);
		self::assertFalse($m3d->is2D);
		$array = $m3d->toFloat64Array();
		self::assertCount(16, $array);
		$json = $m3d->toJSON();
		self::assertSame($m3d->m41, $json['m41']);
		self::assertFalse($json['is2D']);
	}

	public function testTinyScaleInversionAndFromMatrixIsIdentityHandling(): void
	{
		$tiny = (new DOMMatrix())->scaleSelf(1e-8);
		$inv = $tiny->invertSelf();
		self::assertEqualsWithDelta(1e8, $inv->a, 1e-4);

		$fromDict = DOMMatrixReadOnly::fromMatrix(['m33' => 1, 'isIdentity' => true]);
		self::assertTrue($fromDict->is2D);
		self::assertTrue($fromDict->isIdentity);

		$this->expectException(InvalidArgumentException::class);
		DOMMatrixReadOnly::fromMatrix(['isIdentity' => 'yes']);
	}
}
