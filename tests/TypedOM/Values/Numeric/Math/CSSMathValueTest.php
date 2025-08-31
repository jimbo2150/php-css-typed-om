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
    public function testCSSMathSumToString()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        $sum = new CSSMathSum([$value1, $value2]);
        $this->assertEquals('calc(10px + 20px)', (string)$sum);
    }

    public function testCSSMathProductToString()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(2, 'number');
        $product = new CSSMathProduct([$value1, $value2]);
        $this->assertEquals('calc(10px * 2)', (string)$product);
    }

    public function testCSSMathMinToString()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        $min = new CSSMathMin([$value1, $value2]);
        $this->assertEquals('min(10px, 20px)', (string)$min);
    }

    public function testCSSMathMaxToString()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        $max = new CSSMathMax([$value1, $value2]);
        $this->assertEquals('max(10px, 20px)', (string)$max);
    }

    public function testCSSMathInvertToString()
    {
        $value = new CSSUnitValue(10, 'px');
        $invert = new CSSMathInvert([$value]);
        $this->assertEquals('calc(1 / 10px)', (string)$invert);
    }

    public function testCSSMathNegateToString()
    {
        $value = new CSSUnitValue(10, 'px');
        $negate = new CSSMathNegate([$value]);
        $this->assertEquals('calc(-10px)', (string)$negate);
    }

    public function testCSSMathSumWithMultipleValues()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        $value3 = new CSSUnitValue(5, 'em');
        $sum = new CSSMathSum([$value1, $value2, $value3]);
        $this->assertEquals('calc(10px + 20px + 5em)', (string)$sum);
    }

    public function testCSSMathMinWithMultipleValues()
    {
        $value1 = new CSSUnitValue(10, 'px');
        $value2 = new CSSUnitValue(20, 'px');
        $value3 = new CSSUnitValue(5, 'em');
        $min = new CSSMathMin([$value1, $value2, $value3]);
        $this->assertEquals('min(10px, 20px, 5em)', (string)$min);
    }
}