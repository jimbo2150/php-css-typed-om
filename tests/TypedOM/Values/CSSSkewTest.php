<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkew;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSSkewTest extends TestCase
{
	public function testToString()
	{
		$skew = new CSSSkew(
			new CSSUnitValue(10, 'deg'),
			new CSSUnitValue(20, 'deg')
		);
		$this->assertEquals('skew(10deg, 20deg)', $skew->toString());
	}

	public function testToMatrix()
	{
		$skew = new CSSSkew(
			new CSSUnitValue(10, 'deg'),
			new CSSUnitValue(20, 'deg')
		);
		
		$matrix = $skew->toMatrix();
		
		$this->assertInstanceOf(\Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix::class, $matrix);
		
		// Check that the matrix represents a skew transformation
		// For skew(10deg, 20deg), the matrix should be:
		// [1, tan(20deg), 0, 0]
		// [tan(10deg), 1, 0, 0]
		// [0, 0, 1, 0]
		// [0, 0, 0, 1]
		
		$expectedTan10 = tan(deg2rad(10));
		$expectedTan20 = tan(deg2rad(20));
		
		$this->assertEqualsWithDelta(1.0, $matrix->a, 0.0001);
		$this->assertEqualsWithDelta($expectedTan20, $matrix->b, 0.0001);
		$this->assertEqualsWithDelta($expectedTan10, $matrix->c, 0.0001);
		$this->assertEqualsWithDelta(1.0, $matrix->d, 0.0001);
		$this->assertEqualsWithDelta(0.0, $matrix->e, 0.0001);
		$this->assertEqualsWithDelta(0.0, $matrix->f, 0.0001);
	}

	public function testToMatrixWithZeroValues()
	{
		$skew = new CSSSkew(
			new CSSUnitValue(0, 'deg'),
			new CSSUnitValue(0, 'deg')
		);
		
		$matrix = $skew->toMatrix();
		
		// Should be identity matrix for zero skew
		$this->assertEqualsWithDelta(1.0, $matrix->a, 0.0001);
		$this->assertEqualsWithDelta(0.0, $matrix->b, 0.0001);
		$this->assertEqualsWithDelta(0.0, $matrix->c, 0.0001);
		$this->assertEqualsWithDelta(1.0, $matrix->d, 0.0001);
		$this->assertEqualsWithDelta(0.0, $matrix->e, 0.0001);
		$this->assertEqualsWithDelta(0.0, $matrix->f, 0.0001);
	}
}
