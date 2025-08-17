<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSMathProductTest extends TestCase
{
    public function testToString()
    {
        $prod = new CSSMathProduct(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, 'number')
        );
        $this->assertEquals('calc(10px * 5)', $prod->toString());
    }

    public function testToStringWithThreeValues()
    {
        $prod = new CSSMathProduct(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, 'number'),
            new CSSUnitValue(2, 'number')
        );
        $this->assertEquals('calc(10px * 5 * 2)', $prod->toString());
    }

    public function testGetValues()
    {
        $values = [
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, 'number')
        ];
        $prod = new CSSMathProduct(...$values);
        $this->assertEquals($values, $prod->getValues());
    }

    public function testIsValid()
    {
        $prod = new CSSMathProduct(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, 'number')
        );
        $this->assertTrue($prod->isValid());
    }

    public function testClone()
    {
        $prod = new CSSMathProduct(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, 'number')
        );
        $clone = $prod->clone();
        $this->assertInstanceOf(CSSMathProduct::class, $clone);
        $this->assertNotSame($prod, $clone);
        $this->assertEquals($prod->toString(), $clone->toString());
    }

    public function testConstructorWithNoValues()
    {
        $this->expectException(\InvalidArgumentException::class);
        new CSSMathProduct();
    }
}
