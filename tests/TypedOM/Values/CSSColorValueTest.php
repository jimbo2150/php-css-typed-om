<?php

declare(strict_types=1);

namespace Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSColorValue;
use PHPUnit\Framework\TestCase;

/**
 * Tests for CSSColorValue class.
 */
class CSSColorValueTest extends TestCase
{
    public function testConstructor()
    {
        $color = new CSSColorValue('red');
        $this->assertInstanceOf(CSSColorValue::class, $color);
    }

    public function testParse()
    {
        $color = CSSColorValue::parse('blue');
        $this->assertInstanceOf(CSSColorValue::class, $color);
        $this->assertSame('blue', $color->toString());
    }

    public function testToString()
    {
        $color = new CSSColorValue('rgb(255, 0, 0)');
        $this->assertSame('rgb(255, 0, 0)', $color->toString());
    }

    public function testIsValid()
    {
        $validColor = new CSSColorValue('#ff0000');
        $this->assertTrue($validColor->isValid());

        $invalidColor = new CSSColorValue('invalid-color');
        $this->assertFalse($invalidColor->isValid());
    }

    public function testClone()
    {
        $color = new CSSColorValue('green');
        $cloned = $color->clone();
        
        $this->assertInstanceOf(CSSColorValue::class, $cloned);
        $this->assertNotSame($color, $cloned);
        $this->assertSame($color->toString(), $cloned->toString());
    }

    public function testParseInvalidColor()
    {
        $this->expectException(\InvalidArgumentException::class);
        CSSColorValue::parse('not-a-color');
    }
}