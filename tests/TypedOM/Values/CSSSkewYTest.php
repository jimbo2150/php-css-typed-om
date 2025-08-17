<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSSkewY;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSSkewYTest extends TestCase
{
	public function testToString()
	{
		$skewY = new CSSSkewY(new CSSUnitValue(45, 'deg'));
		$this->assertEquals('skewY(45deg)', $skewY->toString());
	}
}
