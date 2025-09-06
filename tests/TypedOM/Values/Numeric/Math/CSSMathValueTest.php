<?php

declare(strict_types=1);

namespace Jimbo2150\PhpCssTypedOm\Tests\TypedOM\Values\Numeric\Math;

use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\CSSUnitValue;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathInvert;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMax;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathMin;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathNegate;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathProduct;
use Jimbo2150\PhpCssTypedOm\TypedOM\Values\Numeric\Math\CSSMathSum;
use PHPUnit\Framework\TestCase;

class CSSMathValueTest extends TestCase
{
    public function testCSSMathInvertToString()
    {
        $mathInvert = new CSSMathInvert(new CSSUnitValue(10, 'px'));
        $this->assertEquals('calc(1 / 10px)', (string)$mathInvert);
    }

    public function testCSSMathNegateToString()
    {
        $mathNegate = new CSSMathNegate(new CSSUnitValue(10, 'px'));
        $this->assertEquals('calc(-10px)', (string)$mathNegate);
    }

    public function testCSSMathSumToString()
    {
        $mathSum = new CSSMathSum([new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')]);
        $this->assertEquals('calc(10px + 20px)', (string)$mathSum);
    }

    public function testCSSMathProductToString()
    {
        $mathProduct = new CSSMathProduct([new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')]);
        $this->assertEquals('calc(10px * 20px)', (string)$mathProduct);
    }

    public function testCSSMathMinToString()
    {
        $mathMin = new CSSMathMin([new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')]);
        $this->assertEquals('min(10px, 20px)', (string)$mathMin);
    }

    public function testCSSMathMaxToString()
    {
        $mathMax = new CSSMathMax([new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')]);
        $this->assertEquals('max(10px, 20px)', (string)$mathMax);
    }

    public function testClone()
    {
        $mathSum = new CSSMathSum([new CSSUnitValue(10, 'px'), new CSSUnitValue(20, 'px')]);
        $clone = $mathSum->clone();
        $this->assertNotSame($mathSum, $clone);
        $this->assertNotSame($mathSum->values, $clone->values);
    }
}