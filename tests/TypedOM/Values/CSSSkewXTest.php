<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewX;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSSkewXTest extends TestCase
{
	public function testToString()
	{
		$skewX = new CSSSkewX(new CSSUnitValue(30, 'deg'));
		$this->assertEquals('skewX(30deg)', $skewX->toString());
	}
}
