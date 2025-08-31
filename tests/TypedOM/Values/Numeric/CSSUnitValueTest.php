<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSUnitValueTest extends TestCase
{
    public function testToStringWithPxUnit()
    {
        $value = new CSSUnitValue(10, 'px');
        $this->assertEquals('10px', (string)$value);
    }

    public function testToStringWithFloatValue()
    {
        $value = new CSSUnitValue(10.5, 'px');
        $this->assertEquals('10.5px', (string)$value);
    }

    public function testToStringWithEmUnit()
    {
        $value = new CSSUnitValue(2, 'em');
        $this->assertEquals('2em', (string)$value);
    }

    public function testToStringWithPercentUnit()
    {
        $value = new CSSUnitValue(50, 'percent');
        $this->assertEquals('50%', (string)$value);
    }

    public function testToStringWithNumberUnit()
    {
        $value = new CSSUnitValue(5, 'number');
        $this->assertEquals('5', (string)$value);
    }

    public function testToStringWithVwUnit()
    {
        $value = new CSSUnitValue(100, 'vw');
        $this->assertEquals('100vw', (string)$value);
    }

    public function testToStringWithDegUnit()
    {
        $value = new CSSUnitValue(90, 'deg');
        $this->assertEquals('90deg', (string)$value);
    }

    public function testStringableInterface()
    {
        $value = new CSSUnitValue(10, 'px');
        $this->assertEquals('10px', (string)$value);
    }

    public function testStringableInterfaceWithFloat()
    {
        $value = new CSSUnitValue(10.5, 'px');
        $this->assertEquals('10.5px', (string)$value);
    }
}