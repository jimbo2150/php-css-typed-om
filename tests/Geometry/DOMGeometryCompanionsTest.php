<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\Geometry;

use Error;
use InvalidArgumentException;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMMatrixReadOnly;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMPoint;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMPointReadOnly;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMQuad;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMRect;
use Jimbo2150\PhpCssTypedOm\Geometry\DOMRectReadOnly;
use PHPUnit\Framework\TestCase;

final class DOMGeometryCompanionsTest extends TestCase
{
	public function testDOMPointReadOnlyAndMutableDOMPoint(): void
	{
		$default = new DOMPointReadOnly();
		self::assertSame(0.0, $default->x);
		self::assertSame(0.0, $default->y);
		self::assertSame(0.0, $default->z);
		self::assertSame(1.0, $default->w);
		self::assertSame(['x' => 0.0, 'y' => 0.0, 'z' => 0.0, 'w' => 1.0], $default->toJSON());

		$pt = DOMPointReadOnly::fromPoint(['x' => 3, 'y' => -4.5, 'z' => 2]);
		self::assertSame(3.0, $pt->getX());
		self::assertSame(-4.5, $pt->getY());
		self::assertSame(2.0, $pt->getZ());
		self::assertSame(1.0, $pt->getW());
		self::assertSame(3.0, $pt['x']);
		self::assertNull($pt['unknown']);

		$transformed = $pt->matrixTransform(['a' => 2, 'd' => 2, 'e' => 10, 'f' => 20]);
		self::assertInstanceOf(DOMPoint::class, $transformed);
		self::assertSame(16.0, $transformed->x);
		self::assertSame(11.0, $transformed->y);
		self::assertSame(2.0, $transformed->z);
		self::assertSame(1.0, $transformed->w);
		self::assertSame(16.0, $transformed['x']);

		$mutable = new DOMPoint(1.0, 2.0, 3.0, 4.0);
		$mutable->x = 5.0;
		$mutable->setY(6.0);
		$mutable['z'] = 7.0;
		$mutable['w'] = 2.0;
		self::assertSame(['x' => 5.0, 'y' => 6.0, 'z' => 7.0, 'w' => 2.0], $mutable->toJSON());

		// Identity transform via null / empty array
		$idPt = $mutable->matrixTransform();
		self::assertEquals($mutable->toJSON(), $idPt->toJSON());
		self::assertNotSame($mutable, $idPt);
	}

	public function testDOMPointImmutabilityAndValidation(): void
	{
		$ro = new DOMPointReadOnly(1.0, 2.0);
		$writeProperty = static function (object $target, string $property, float $value): void {
			$target->$property = $value;
		};
		try {
			$writeProperty($ro, 'x', 5.0);
			self::fail('Expected Error when mutating DOMPointReadOnly property.');
		} catch (Error $e) {
			self::assertNotSame('', $e->getMessage());
		}

		try {
			$ro['x'] = 5.0;
			self::fail('Expected InvalidArgumentException on DOMPointReadOnly::offsetSet().');
		} catch (InvalidArgumentException $e) {
			self::assertNotSame('', $e->getMessage());
		}

		foreach ([NAN, INF, -INF] as $nonFinite) {
			try {
				new DOMPoint($nonFinite);
				self::fail('Expected InvalidArgumentException on non-finite coordinate.');
			} catch (InvalidArgumentException $e) {
				self::assertNotSame('', $e->getMessage());
			}
		}

		$this->expectException(InvalidArgumentException::class);
		/** @phpstan-ignore argument.type */
		DOMPoint::fromPoint(['x' => 'bad']);
	}

	public function testDOMRectReadOnlyAndMutableDOMRect(): void
	{
		$default = new DOMRectReadOnly();
		self::assertSame(0.0, $default->top);
		self::assertSame(0.0, $default->right);
		self::assertSame(0.0, $default->bottom);
		self::assertSame(0.0, $default->left);

		// Negative width/height swaps left/right and top/bottom
		$neg = new DOMRectReadOnly(100.0, 200.0, -40.0, -50.0);
		self::assertSame(60.0, $neg->left);
		self::assertSame(100.0, $neg->right);
		self::assertSame(150.0, $neg->top);
		self::assertSame(200.0, $neg->bottom);

		$mutable = DOMRect::fromRect(['x' => 10, 'y' => 20, 'width' => 30, 'height' => 40]);
		$mutable->x = 15.0;
		$mutable->setHeight(50.0);
		self::assertSame(15.0, $mutable->left);
		self::assertSame(45.0, $mutable->right);
		self::assertSame(20.0, $mutable->top);
		self::assertSame(70.0, $mutable->bottom);
		self::assertSame([
			'x' => 15.0,
			'y' => 20.0,
			'width' => 30.0,
			'height' => 50.0,
			'top' => 20.0,
			'right' => 45.0,
			'bottom' => 70.0,
			'left' => 15.0,
		], $mutable->toJSON());

		$writeComputed = static function (object $target, string $property, float $value): void {
			$target->$property = $value;
		};
		try {
			$writeComputed($mutable, 'top', 0.0);
			self::fail('Expected Error writing computed DOMRect::$top.');
		} catch (Error $e) {
			self::assertNotSame('', $e->getMessage());
		}

		$huge = new DOMRectReadOnly(1e308, 0.0, 1e308, 0.0);
		$this->expectException(InvalidArgumentException::class);
		$huge->getRight();
	}

	public function testDOMQuadCreationBoundsCloningAndNoAliasing(): void
	{
		$sourcePoint = new DOMPoint(10.0, 20.0);
		$quad = new DOMQuad($sourcePoint, ['x' => 50, 'y' => 20], ['x' => 50, 'y' => 80], ['x' => 10, 'y' => 80]);
		// Caller point is copied, not aliased
		$sourcePoint->x = 999.0;
		self::assertSame(10.0, $quad->p1->x);

		// [SameObject] live mutable corners
		$quad->p2->x = 70.0;
		$quad->p3->y = 90.0;
		$bounds = $quad->getBounds();
		self::assertInstanceOf(DOMRect::class, $bounds);
		self::assertSame(10.0, $bounds->x);
		self::assertSame(20.0, $bounds->y);
		self::assertSame(60.0, $bounds->width);
		self::assertSame(70.0, $bounds->height);

		// fromRect winding order
		$fromRect = DOMQuad::fromRect(new DOMRectReadOnly(10.0, 20.0, 30.0, 40.0));
		self::assertSame(['x' => 10.0, 'y' => 20.0, 'z' => 0.0, 'w' => 1.0], $fromRect->p1->toJSON());
		self::assertSame(['x' => 40.0, 'y' => 20.0, 'z' => 0.0, 'w' => 1.0], $fromRect->p2->toJSON());
		self::assertSame(['x' => 40.0, 'y' => 60.0, 'z' => 0.0, 'w' => 1.0], $fromRect->p3->toJSON());
		self::assertSame(['x' => 10.0, 'y' => 60.0, 'z' => 0.0, 'w' => 1.0], $fromRect->p4->toJSON());

		// Clone independence
		$cloned = clone $fromRect;
		$cloned->p1->x = -5.0;
		self::assertSame(10.0, $fromRect->p1->x);
		self::assertSame(-5.0, $cloned->getBounds()->left);

		// DOMMatrixReadOnly::transformPoint accepts DOMPointReadOnly and returns DOMPoint
		$matrix = (new DOMMatrix())->translateSelf(5.0, 10.0);
		$out = $matrix->transformPoint(new DOMPointReadOnly(1.0, 2.0));
		self::assertInstanceOf(DOMPoint::class, $out);
		self::assertSame(6.0, $out->x);
		self::assertSame(12.0, $out->y);
		self::assertSame(6.0, $out['x']);
		self::assertSame(12.0, $out['y']);

		// DOMQuad::fromRect handles negative dimensions and guards overflow in getBounds()
		$negQuad = DOMQuad::fromRect(['x' => 50, 'y' => 60, 'width' => -20, 'height' => -30]);
		self::assertSame(['x' => 50.0, 'y' => 60.0, 'z' => 0.0, 'w' => 1.0], $negQuad->p1->toJSON());
		self::assertSame(['x' => 30.0, 'y' => 60.0, 'z' => 0.0, 'w' => 1.0], $negQuad->p2->toJSON());
		self::assertSame(['x' => 30.0, 'y' => 30.0, 'z' => 0.0, 'w' => 1.0], $negQuad->p3->toJSON());
		self::assertSame(['x' => 50.0, 'y' => 30.0, 'z' => 0.0, 'w' => 1.0], $negQuad->p4->toJSON());
		$negBounds = $negQuad->getBounds();
		self::assertSame(30.0, $negBounds->left);
		self::assertSame(50.0, $negBounds->right);
		self::assertSame(30.0, $negBounds->top);
		self::assertSame(60.0, $negBounds->bottom);

		$overflowQuad = new DOMQuad(['x' => -1e308, 'y' => 0], ['x' => 1e308, 'y' => 0], ['x' => 1e308, 'y' => 10], ['x' => -1e308, 'y' => 10]);
		$this->expectException(InvalidArgumentException::class);
		$overflowQuad->getBounds();
	}
}
