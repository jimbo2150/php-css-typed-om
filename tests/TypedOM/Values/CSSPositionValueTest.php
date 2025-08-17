<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSPositionValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSPositionValueTest extends TestCase
{
	public function testToString()
	{
		$position = new CSSPositionValue(
			new CSSUnitValue(10, 'px'),
			new CSSUnitValue(20, '%')
		);
		$this->assertEquals('10px 20%', $position->toString());
	}

	public function testIsValid()
	{
		$validPosition = new CSSPositionValue(
			new CSSUnitValue(10, 'px'),
			new CSSUnitValue(20, '%')
		);
		$this->assertTrue($validPosition->isValid());

		// Assuming an invalid CSSUnitValue would make the position invalid
		// For example, if CSSUnitValue(NAN, 'px') was possible and isValid() returned false
	}
}
