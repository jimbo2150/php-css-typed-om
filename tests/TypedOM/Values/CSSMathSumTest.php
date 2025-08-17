<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathSum;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSMathSumTest extends TestCase
{
    public function testToString()
    {
        $sum = new CSSMathSum(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, '%')
        );
        $this->assertEquals('calc(10px + 5%)', $sum->toString());
    }

    public function testToStringWithThreeValues()
    {
        $sum = new CSSMathSum(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, '%'),
            new CSSUnitValue(2, 'em')
        );
        $this->assertEquals('calc(10px + 5% + 2em)', $sum->toString());
    }

    public function testGetValues()
    {
        $values = [
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, '%')
        ];
        $sum = new CSSMathSum(...$values);
        $this->assertEquals($values, $sum->getValues());
    }

    public function testIsValid()
    {
        $sum = new CSSMathSum(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, '%')
        );
        $this->assertTrue($sum->isValid());
    }

    public function testClone()
    {
        $sum = new CSSMathSum(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, '%')
        );
        $clone = $sum->clone();
        $this->assertInstanceOf(CSSMathSum::class, $clone);
        $this->assertNotSame($sum, $clone);
        $this->assertEquals($sum->toString(), $clone->toString());
    }

    public function testConstructorWithNoValues()
    {
        $this->expectException(\InvalidArgumentException::class);
        new CSSMathSum();
    }
}
