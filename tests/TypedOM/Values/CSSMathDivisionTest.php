<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathDivision;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSMathDivisionTest extends TestCase
{
	public function testToString()
	{
		$div = new CSSMathDivision(
			new CSSUnitValue(100, 'px'),
			new CSSUnitValue(2, 'number')
		);
		$this->assertEquals('calc(100px / 2)', $div->toString());
	}
}
