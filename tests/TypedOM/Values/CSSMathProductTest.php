<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSMathProduct class.
 */
class CSSMathProductTest extends TestCase
{
    public function testConstructor()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, '');
        
        $product = new CSSMathProduct($value1, $value2);
        $this->assertInstanceOf(CSSMathProduct::class, $product);
    }

    public function testGetValues()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, '');
        
        $product = new CSSMathProduct($value1, $value2);
        $values = $product->getValues();
        
        $this->assertCount(2, $values);
        $this->assertSame($value1, $values[0]);
        $this->assertSame($value2, $values[1]);
    }

    public function testToString()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, '');
        
        $product = new CSSMathProduct($value1, $value2);
        $this->assertSame('calc(10px * 2)', $product->toString());
    }

    public function testToStringWithMultipleValues()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, '');
        $value3 = new CSSUnitValue(3, '');
        
        $product = new CSSMathProduct($value1, $value2, $value3);
        $this->assertSame('calc(10px * 2 * 3)', $product->toString());
    }

    public function testIsValid()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, '');
        
        $product = new CSSMathProduct($value1, $value2);
        $this->assertTrue($product->isValid());
    }

    public function testClone()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, '');
        
        $product = new CSSMathProduct($value1, $value2);
        $cloned = $product->clone();
        
        $this->assertInstanceOf(CSSMathProduct::class, $cloned);
        $this->assertNotSame($product, $cloned);
    }

    public function testToUnit()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, '');
        
        $product = new CSSMathProduct($value1, $value2);
        $result = $product->to('px');
        
        $this->assertInstanceOf(CSSUnitValue::class, $result);
        $this->assertSame(20.0, $result->value);
        $this->assertSame('px', $result->unit);
    }

    public function testToUnitWithIncompatibleUnits()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, 'em');
        
        $product = new CSSMathProduct($value1, $value2);
        $result = $product->to('px');
        
        $this->assertNull($result);
    }

    public function testSingleValue()
    {
        $value = new CSSUnitValue(10, 'px');
        
        $product = new CSSMathProduct($value);
        $this->assertSame('calc(10px)', $product->toString());
    }

    public function testEmptyProduct()
    {
        $product = new CSSMathProduct();
        $this->assertSame('calc()', $product->toString());
    }
}
