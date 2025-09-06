<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Traits;

use Jimbo2150\PhpCssTypedOm\TypedOM\Traits\MathOperationsTrait;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class MathOperationsTraitTest extends TestCase
{
	use MathOperationsTrait;

	protected function setUp(): void
    {
        $this->initializeMathOperations(10, 'px');
    }

	public function testAdd()
    {
        $other = new CSSUnitValue(5, 'px');
        $result = $this->add($other);
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(15, $result->value);
        $this->assertEquals('px', $result->unit);
    }

    public function testAddIncompatibleUnits()
    {
        $other = new CSSUnitValue(5, 'em');
        $result = $this->add($other);
        $this->assertNull($result);
    }

	public function testSubtract()
    {
        $other = new CSSUnitValue(5, 'px');
        $result = $this->subtract($other);
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(5, $result->value);
        $this->assertEquals('px', $result->unit);
    }

    public function testSubtractIncompatibleUnits()
    {
        $other = new CSSUnitValue(5, 'em');
        $result = $this->subtract($other);
        $this->assertNull($result);
    }

	public function testMultiply()
    {
        $result = $this->multiply(2.5);
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(25, $result->value);
        $this->assertEquals('px', $result->unit);
    }

 public function testDivide()
    {
        $result = $this->divide(2.5);
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(4, $result->value);
        $this->assertEquals('px', $result->unit);
    }

    public function testDivideByZero()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->divide(0.0);
    }

    public function testTo()
    {
        $result = $this->to('em');
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEqualsWithDelta(0.625, $result->value, 0.0001);
        $this->assertEquals('em', $result->unit);
    }

    public function testToSameUnit()
    {
        $result = $this->to('px');
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertEquals(10, $result->value);
        $this->assertEquals('px', $result->unit);
    }

    public function testToInvalidUnit()
    {
        $this->assertNull($this->to('invalid-unit'));
    }

    public function testGetAndSetNumericValue()
    {
        $this->assertEquals(10, $this->getNumericValue());
        $this->setNumericValue(20.5);
        $this->assertEquals(20.5, $this->getNumericValue());
    }

    public function testGetAndSetUnit()
    {
        $this->assertEquals('px', $this->getUnit());
        $this->setUnit('rem');
        $this->assertEquals('rem', $this->getUnit());
    }

    public function testIsValid()
    {
        $this->assertTrue($this->isValid());
        $this->setNumericValue(INF);
        $this->assertFalse($this->isValid());
        $this->setNumericValue(10);
        $this->setUnit('');
        $this->assertFalse($this->isValid());
    }
}