<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSColorValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSKeywordValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSStyleValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSStyleValueTest extends TestCase
{
    public function testParseNumericValue()
    {
        $value = CSSStyleValue::parse('10px');
        $this->assertInstanceOf(CSSUnitValue::class, $value);
        $this->assertEquals('10px', $value->toString());
        $this->assertEquals('unit', $value->getType());
    }

    public function testParseColorValueHex()
    {
        $value = CSSStyleValue::parse('#FF0000');
        $this->assertInstanceOf(CSSColorValue::class, $value);
        $this->assertEquals('#FF0000', $value->toString());
        $this->assertEquals('color', $value->getType());
    }

    public function testParseColorValueRgb()
    {
        $value = CSSStyleValue::parse('rgb(255, 0, 0)');
        $this->assertInstanceOf(CSSColorValue::class, $value);
        $this->assertEquals('rgb(255, 0, 0)', $value->toString());
        $this->assertEquals('color', $value->getType());
    }

    public function testParseKeywordValue()
    {
        $value = CSSStyleValue::parse('auto');
        $this->assertInstanceOf(CSSKeywordValue::class, $value);
        $this->assertEquals('auto', $value->toString());
        $this->assertEquals('keyword', $value->getType());
    }

    public function testToStringAbstractMethod()
    {
        // Test toString() through a concrete subclass
        $value = new CSSUnitValue(10, 'px');
        $this->assertEquals('10px', $value->toString());
    }

    public function testGetType()
    {
        $value = new CSSUnitValue(10, 'px');
        $this->assertEquals('unit', $value->getType());
    }

    public function testIsValidAbstractMethod()
    {
        // Test isValid() through a concrete subclass
        $value = new CSSUnitValue(10, 'px');
        $this->assertTrue($value->isValid());
    }

    public function testCloneAbstractMethod()
    {
        // Test clone() through a concrete subclass
        $value = new CSSUnitValue(10, 'px');
        $clonedValue = $value->clone();
        $this->assertNotSame($value, $clonedValue);
        $this->assertEquals($value->toString(), $clonedValue->toString());
        $this->assertEquals($value->getType(), $clonedValue->getType());
    }

    public function testParse()
    {
        // Test that CSSStyleValue::parse calls createFromCssText
        $value = CSSStyleValue::parse('20px');
        $this->assertInstanceOf(CSSUnitValue::class, $value);
        $this->assertEquals('20px', $value->toString());
    }

    public function testParseAll()
    {
        // Test with multiple values
        $values = CSSStyleValue::parseAll('10px solid red');
        $this->assertIsArray($values);
        $this->assertCount(3, $values);
        $this->assertInstanceOf(CSSUnitValue::class, $values[0]);
        $this->assertEquals('10px', $values[0]->toString());
        $this->assertInstanceOf(CSSKeywordValue::class, $values[1]);
        $this->assertEquals('solid', $values[1]->toString());
        $this->assertInstanceOf(CSSColorValue::class, $values[2]);
        $this->assertEquals('red', $values[2]->toString());

        // Test with single value
        $values = CSSStyleValue::parseAll('auto');
        $this->assertIsArray($values);
        $this->assertCount(1, $values);
        $this->assertInstanceOf(CSSKeywordValue::class, $values[0]);
        $this->assertEquals('auto', $values[0]->toString());

        // Test with empty string
        $values = CSSStyleValue::parseAll('');
        $this->assertIsArray($values);
        $this->assertCount(0, $values);

        // Test with whitespace only
        $values = CSSStyleValue::parseAll('   ');
        $this->assertIsArray($values);
        $this->assertCount(0, $values);
    }
}
