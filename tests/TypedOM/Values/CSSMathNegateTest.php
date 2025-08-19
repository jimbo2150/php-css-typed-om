<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSMathNegate class.
 */
class CSSMathNegateTest extends TestCase
{
    public function testConstructor()
    {
        $value = new CSSUnitValue(10, 'px');
        
        $negate = new CSSMathNegate($value);
        $this->assertInstanceOf(CSSMathNegate::class, $negate);
    }

    public function testGetValues()
    {
        $value = new CSSUnitValue(10, 'px');
        
        $negate = new CSSMathNegate($value);
        $values = $negate->getValues();
        
        $this->assertCount(1, $values);
        $this->assertSame($value, $values[0]);
    }

    public function testToString()
    {
        $value = new CSSUnitValue(10, 'px');
        
        $negate = new CSSMathNegate($value);
        $this->assertSame('calc(-10px)', $negate->toString());
    }

    public function testIsValid()
    {
        $value = new CSSUnitValue(10, 'px');
        
        $negate = new CSSMathNegate($value);
        $this->assertTrue($negate->isValid());
    }

    public function testClone()
    {
        $value = new CSSUnitValue(10, 'px');
        
        $negate = new CSSMathNegate($value);
        $cloned = $negate->clone();
        
        $this->assertInstanceOf(CSSMathNegate::class, $cloned);
        $this->assertNotSame($negate, $cloned);
    }

    public function testToUnit()
    {
        $value = new CSSUnitValue(10, 'px');
        
        $negate = new CSSMathNegate($value);
        $result = $negate->to('px');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(-10.0, $result->value);
        $this->assertSame('px', $result->unit);
    }

    public function testToUnitWithIncompatibleUnits()
    {
        $value = new CSSUnitValue(10, 'px');
        
        $negate = new CSSMathNegate($value);
        $result = $negate->to('em');
        
        $this->assertNull($result);
    }

    public function testNegativeValue()
    {
        $value = new CSSUnitValue(-5, 'px');
        
        $negate = new CSSMathNegate($value);
        $result = $negate->to('px');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(5.0, $result->value);
    }

    public function testZeroValue()
    {
        $value = new CSSUnitValue(0, 'px');
        
        $negate = new CSSMathNegate($value);
        $result = $negate->to('px');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(0.0, $result->value);
    }
}
