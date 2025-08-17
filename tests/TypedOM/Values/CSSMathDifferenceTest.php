<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSMathDifference;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\CSSUnitValue;
use PHPUnit\Framework\TestCase;

class CSSMathDifferenceTest extends TestCase
{
    public function testToString()
    {
        $diff = new CSSMathDifference(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, '%')
        );
        $this->assertEquals('calc(10px - 5%)', $diff->toString());
    }

    public function testToStringWithThreeValues()
    {
        $diff = new CSSMathDifference(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, '%'),
            new CSSUnitValue(2, 'em')
        );
        $this->assertEquals('calc(10px - 5% - 2em)', $diff->toString());
    }

    // public function testGetValues()
    // {
    //     $values = [
    //         new CSSUnitValue(10, 'px'),
    //         new CSSUnitValue(5, '%')
    //     ];
    //     $diff = new CSSMathDifference(...$values);
    //     // $this->assertEquals($values, $diff->getValues());
    // }

    public function testIsValid()
    {
        $diff = new CSSMathDifference(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, '%')
        );
        $this->assertTrue($diff->isValid());
    }

    public function testClone()
    {
        $diff = new CSSMathDifference(
            new CSSUnitValue(10, 'px'),
            new CSSUnitValue(5, '%')
        );
        $clone = $diff->clone();
        $this->assertInstanceOf(CSSMathDifference::class, $clone);
        $this->assertNotSame($diff, $clone);
        $this->assertEquals($diff->toString(), $clone->toString());
    }

    public function testConstructorWithNoValues()
    {
        $this->expectException(\InvalidArgumentException::class);
        new CSSMathDifference();
    }
}
