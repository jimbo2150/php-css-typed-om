<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM;

use Jimbo2150\PhpCssTypedOm\CSS;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitEnum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use PHPUnit\Framework\TestCase;
use BadMethodCallException;
use InvalidArgumentException;

class CSSTest extends TestCase
{
    public function testValidStaticUnitMethods()
    {
		$px = CSS::px("10");
        $this->assertInstanceOf(CSSUnitValue::class, $px);
		$this->assertEquals(new CSSUnitValue(10, CSSUnitEnum::LENGTH_px), $px);
		$num = CSS::number("35.42");
		$this->assertEquals(new CSSUnitValue(35.42, CSSUnitEnum::NUMBER), $num);
        $this->assertEquals(new CSSUnitValue(10, CSSUnitEnum::LENGTH_px), CSS::px("10"));
        $this->assertEquals(new CSSUnitValue(1.5, CSSUnitEnum::LENGTH_em), CSS::em("1.5"));
        $this->assertEquals(new CSSUnitValue(50, CSSUnitEnum::PERCENT), CSS::percent("50"));
    }

    public function testInvalidStaticUnitMethod()
    {
        $this->expectException(BadMethodCallException::class);
        CSS::invalidUnit("10");
    }

    public function testStaticUnitMethodWithNoArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        CSS::px();
    }

    public function testStaticUnitMethodWithNonNumericArgument()
    {
        $this->expectException(InvalidArgumentException::class);
        CSS::px('abc');
    }
}
