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

    public function testAddSameUnit()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->add($value2);

        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(15, $result->value);
        $this->assertEquals('px', $result->unit);
    }

    public function testAddDifferentUnit()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'em');
        $result = $value1->add($value2);

        $this->assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum::class, $result);
    }

    public function testSubSameUnit()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->sub($value2);

        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(5, $result->value);
        $this->assertEquals('px', $result->unit);
    }

    public function testSubDifferentUnit()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'em');
        $result = $value1->sub($value2);

        $this->assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum::class, $result);
    }

    public function testClone()
    {
        $value = new CSSUnitValue(10, 'px');
        $clone = $value->clone();

        $this->assertInstanceOf(CSSUnitValue::class, $clone);
        $this->assertEquals(10, $clone->value);
        $this->assertEquals('px', $clone->unit);
        $this->assertNotSame($value, $clone);
    }
}