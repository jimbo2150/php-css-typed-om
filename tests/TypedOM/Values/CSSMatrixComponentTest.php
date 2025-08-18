<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\DOM\DOMMatrix;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMatrixComponent;
use PHPUnit\Framework\TestCase;

class CSSMatrixComponentTest extends TestCase
{
	public function testToString2D()
	{
		$matrix = new DOMMatrix([1, 2, 3, 4, 5, 6]);
		$matrixComponent = new CSSMatrixComponent($matrix);
		$this->assertEquals('matrix(1, 2, 3, 4, 5, 6)', $matrixComponent->toString());
	}

	public function testToString3D()
	{
		$matrix = new DOMMatrix([1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16]);
		$matrixComponent = new CSSMatrixComponent($matrix);
		$this->assertEquals('matrix3d(1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16)', $matrixComponent->toString());
	}
}
