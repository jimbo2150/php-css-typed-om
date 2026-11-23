<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSUnitValueTest extends TestCase
{
    public function testToStringWithPxUnit(): void
    {
        $value = new CSSUnitValue(10, 'px');
        self::assertInstanceOf(\Stringable::class, $value);
        self::assertSame('10px', (string)$value);
    }

    public function testToStringWithFloatValue(): void
    {
        $value = new CSSUnitValue(10.5, 'px');
        self::assertSame('10.5px', (string)$value);
    }

    public function testToStringWithEmUnit(): void
    {
        $value = new CSSUnitValue(2, 'em');
        self::assertSame('2em', (string)$value);
    }

    public function testToStringWithPercentUnit(): void
    {
        $value = new CSSUnitValue(50, 'percent');
        self::assertSame('50%', (string)$value);
    }

    public function testToStringWithNumberUnit(): void
    {
        $value = new CSSUnitValue(5, 'number');
        self::assertSame('5', (string)$value);
    }

    public function testToStringWithVwUnit(): void
    {
        $value = new CSSUnitValue(100, 'vw');
        self::assertSame('100vw', (string)$value);
    }

    public function testToStringWithDegUnit(): void
    {
        $value = new CSSUnitValue(90, 'deg');
        self::assertSame('90deg', (string)$value);
    }

    public function testAddSameUnit(): void
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->add($value2);

        self::assertInstanceOf(CSSUnitValue::class, $result);
        self::assertSame(15.0, $result->value);
        self::assertSame('px', $result->unit);
    }

    public function testAddDifferentUnit(): void
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'em');
        $result = $value1->add($value2);

        self::assertInstanceOf(\Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum::class, $result);
    }

    public function testSubSameUnit(): void
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(5, 'px');
        $result = $value1->sub($value2);

        self::assertInstanceOf(CSSUnitValue::class, $result);
        self::assertSame(5.0, $result->value);
        self::assertSame('px', $result->unit);
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
